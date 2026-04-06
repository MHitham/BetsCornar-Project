<?php

namespace App\Http\Controllers;

// تم التعديل: استخدام FromQuery وWithMapping لتقليل استهلاك الذاكرة أثناء التصدير
use Maatwebsite\Excel\Concerns\FromQuery;
// تم التعديل: تحديد حجم الـ chunk أثناء التصدير الكبير
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
// تم التعديل: تجهيز الصفوف أثناء القراءة دون تحميلها كلها في الذاكرة
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Requests\StoreCustomerVisitRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\VaccineBatch;
use App\Services\CustomerVisitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerVisitService $visitService,
    ) {}

    /**
     * Show all customers with optional search by name or phone.
     */
    public function index(Request $request)
    {
        // تم التعديل: توحيد استعلام الفهرس مع استعلامات التصدير والجلب
        $q = trim((string) $request->input('q', ''));

        $customers = $this->customersIndexQuery($request)
            ->withCount('vaccinations')
            // تم الإضافة: استخدام latestVaccination بدل limit(1) داخل eager loading
            ->with('latestVaccination')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('customers.index', compact('customers', 'q'));
    }

    /**
     * تم الإضافة: عرض السجل الطبي الكامل للعميل (التايم لاين)
     */
    public function show(Customer $customer)
    {
        // تم الإضافة: تحميل كل فواتير العميل مع البنود والتطعيمات بترتيب الأحدث أولًا
        $customer->load([
            'invoices' => function ($query) {
                $query->latest('created_at');
            },
            'invoices.items.product',
            'invoices.vaccinations.product',
        ]);

        return view('customers.show', compact('customer'));
    }

    /**
     * تم الإضافة: بحث AJAX عن العملاء بالاسم أو الهاتف (للـ Quick Sale)
     */
    public function search(Request $request)
    {
        $q = trim($request->input('q', ''));

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $results = Customer::query()
            ->where('name', 'like', "%{$q}%")
            ->orWhere('phone', 'like', "%{$q}%")
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'phone']);

        return response()->json($results);
    }

    /**
     * Show the customer visit form.
     */
    public function create()
    {
        // Pre-load the consultation service product
        $consultationProduct = Product::query()
            ->active()
            ->where('type', 'service')
            ->orderByDesc('id')
            ->first();

        // All active vaccine products (including out-of-stock so we can show them disabled)
        $vaccines = Product::query()
            ->active()
            ->where('type', 'vaccination')
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'stock_status', 'quantity', 'track_stock']);

        // Compute usable batch qty per vaccine product
        // (batches not expired AND quantity_remaining > 0)
        $today = now()->toDateString();
        $vaccineUsableQty = VaccineBatch::query()
            ->where('expiry_date', '>=', $today)
            ->where('quantity_remaining', '>', 0)
            ->groupBy('product_id')
            ->select('product_id', DB::raw('SUM(quantity_remaining) as usable_qty'))
            ->pluck('usable_qty', 'product_id');

        // All active selectable products for additional items
        $products = Product::query()
            ->active()
            ->whereIn('type', ['product', 'service', 'vaccination'])
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'type', 'stock_status', 'quantity', 'track_stock']);

        return view('customers.create', compact('consultationProduct', 'vaccines', 'vaccineUsableQty', 'products'));
    }

    /**
     * Save the customer visit (transactional).
     */
    public function store(StoreCustomerVisitRequest $request)
    {
        try {
            $invoice = $this->visitService->saveVisit($request->validated());

            return redirect()
                ->route('customers.index')
                ->with('success', __('customers.messages.created'));
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->withErrors(['vaccine' => $e->getMessage()]);
        }
    }

    /**
     * تم الإضافة: تصدير العملاء المحددين أو كل نتائج البحث الحالية إلى ملف Excel.
     */
    public function exportExcel(Request $request): BinaryFileResponse
    {
        // تم التعديل: بناء Query مباشر للتصدير بدل تحميل كل العملاء في الذاكرة دفعة واحدة
        $exportQuery = $request->boolean('select_all_ids')
            ? $this->customersIndexQuery($request)
                // تم التعديل: تحديد الأعمدة المطلوبة فقط للتصدير
                ->select(['id', 'name', 'phone', 'animal_type'])
                // تم التعديل: استخدام ترتيب ثابت بـ id لضمان chunking آمن
                ->orderBy('id')
            : Customer::query()
                // تم التعديل: قصر التصدير على المعرّفات المحددة فقط
                ->whereKey($this->selectedIds($request))
                // تم التعديل: تحديد الأعمدة المطلوبة فقط للتصدير
                ->select(['id', 'name', 'phone', 'animal_type'])
                // تم التعديل: استخدام ترتيب ثابت بـ id لضمان chunking آمن
                ->orderBy('id');

        // تم التعديل: إنشاء Export قائم على Query لتفادي استهلاك الذاكرة في الملفات الكبيرة
        return Excel::download(
            new class($exportQuery, $this->visitService) implements FromQuery, WithHeadings, WithMapping, WithCustomChunkSize {
                public function __construct(
                    private readonly \Illuminate\Database\Eloquent\Builder $query,
                    private readonly CustomerVisitService $visitService,
                ) {
                }

                // تم التعديل: إعادة الـ query مباشرة إلى Laravel Excel ليعالجها على دفعات
                public function query(): \Illuminate\Database\Eloquent\Builder
                {
                    return $this->query;
                }

                // تم التعديل: تجهيز صف التصدير أثناء القراءة دون تجميع كل النتائج مسبقًا
                public function map($customer): array
                {
                    return [
                        $customer->name,
                        $this->visitService->normalizePhone((string) $customer->phone),
                        $customer->animal_type,
                    ];
                }

                // تم التعديل: الإبقاء على نفس عناوين ملف Excel
                public function headings(): array
                {
                    return ['الاسم', 'رقم الهاتف', 'نوع الحيوان'];
                }

                // تم التعديل: تحديد حجم الـ chunk لتقليل استهلاك الذاكرة أثناء التصدير
                public function chunkSize(): int
                {
                    return 1000;
                }
            },
            'customers-export-'.now()->format('Y-m-d_H-i-s').'.xlsx'
        );
    }

    /**
     * تم الإضافة: جلب أرقام العملاء المحددين أو كل نتائج البحث الحالية للنسخ إلى الحافظة.
     */
    public function getPhones(Request $request): JsonResponse
    {
        // تم الإضافة: إزالة التكرار من الأرقام قبل إرسالها للواجهة
        $phones = ($request->boolean('select_all_ids')
            ? $this->customersIndexQuery($request)->pluck('phone')
            : Customer::query()->whereKey($this->selectedIds($request))->pluck('phone'))
            ->filter()
            ->map(fn ($phone) => $this->visitService->normalizePhone((string) $phone))
            ->filter()
            ->unique()
            ->values();

        return response()->json([
            'phones' => $phones,
        ]);
    }

    /**
     * تم الإضافة: توحيد منطق البحث لاستخدامه في الفهرس والتصدير وجلب الأرقام.
     */
    private function customersIndexQuery(Request $request)
    {
        $q = trim((string) $request->input('q', ''));

        return Customer::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function ($innerQuery) use ($q) {
                    $innerQuery->where('name', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            });
    }

    /**
     * تم الإضافة: قراءة المعرّفات المحددة من الطلب بصيغة آمنة وموحدة.
     */
    private function selectedIds(Request $request): array
    {
        return collect($request->input('ids', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}