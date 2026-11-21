<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerVisitRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\VaccineBatch;
// اجمع تغييرات الحذف الطبي داخل خدمة واحدة.
use App\Services\CustomerDeletionService;
use App\Services\CustomerVisitService;
use Illuminate\Http\JsonResponse;
// أعد التوجيه إلى قائمة العملاء بعد نجاح الحذف.
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerVisitService $visitService,
    ) {}

    public function index(Request $request)
    {

        $q = trim((string) $request->input('q', ''));

        $customers = $this->customersIndexQuery($request)
            ->withCount('vaccinations')

            ->with('latestVaccination')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('customers.index', compact('customers', 'q'));
    }

    public function show(Customer $customer)
    {
        $customer->load([
            'animals' => fn ($q) => $q->orderBy('name'),
            // استبعد الفواتير المخفية من بطاقات الحيوانات ومن رصيد العميل وأرشيفه.
            'animals.invoices' => fn ($q) => $q->visible()->latest()->select(
                'id', 'invoice_number', 'animal_id', 'customer_id', 'created_at', 'total', 'status', 'diagnosis', 'treatment'
            ),
            // حمّل مفاتيح البنود وأسماء المنتجات فقط لكل بطاقات فواتير الحيوانات.
            'animals.invoices.items' => fn ($q) => $q->select('invoice_id', 'product_id')->orderBy('id'),
            'animals.invoices.items.product:id,name',
            'animals.vaccinations' => fn ($q) => $q->latest('vaccination_date'),
            'animals.vaccinations.product:id,name',
            // يُستخدم هذا التحميل كذلك لملخص الرصيد ونوافذ سجل الدفعات.
            'invoices' => fn ($q) => $q->visible()->latest('created_at'),
            'invoices.vaccinations.product',
            'invoices.payments',
        ]);

        // حمّل أسماء أصناف الأرشيف مسبقًا مع استمرار استبعاد الفواتير المخفية.
        $orphanInvoices = $customer->invoices()->visible()->whereNull('animal_id')->latest()->with([
            'items' => fn ($q) => $q->select('invoice_id', 'product_id')->orderBy('id'),
            'items.product:id,name',
        ])->get();
        $orphanVaccinations = $customer->vaccinations()->whereNull('animal_id')->latest('vaccination_date')
            ->with('product:id,name')->get();

        // احسب كل السجلات المتأثرة حتى لو كانت إحدى الفواتير مخفية مسبقًا.
        if (request()->user()->can('delete', $customer)) {
            $customer->loadCount(['invoices', 'animals', 'vaccinations']);
        }

        return view('customers.show', compact('customer', 'orphanInvoices', 'orphanVaccinations'));
    }

    // اطلب موافقة صريحة على الخادم قبل طلب المسح النهائي للسجل الطبي.
    public function destroy(Request $request, Customer $customer, CustomerDeletionService $deletion): RedirectResponse
    {
        $request->validateWithBag('customerDeletion', [
            'confirm_delete' => ['accepted'],
        ], [
            'confirm_delete.accepted' => __('customers.history_deletion.acknowledgement_required'),
        ]);

        $deletion->deleteCustomerWithHistory($customer);

        return redirect()->route('customers.index')->with('success', __('customers.history_deletion.success'));
    }

    // عرض فورم تعديل بيانات العميل (الاسم/التليفون/العنوان/نوع الحيوان/الملاحظات)
    public function edit(Customer $customer)
    {
        return view('customers.edit', compact('customer'));
    }

    // حفظ التعديلات على بيانات العميل بعد التحقق من صحة البيانات وتطبيع رقم التليفون
    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $customer->update($request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', __('customers.messages.updated'));
    }

    public function search(Request $request)
    {
        $q = trim($request->input('q', ''));

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $results = Customer::query()
            // اجمع شروط البحث حتى يظل شرط الحذف المنطقي مطبّقًا على كل النتائج.
            ->where(fn ($query) => $query->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'phone', 'address', 'animal_type']);

        return response()->json($results);
    }

    public function lookupForVisit(Request $request): JsonResponse
    {
        $phone = trim($request->input('phone', ''));
        if (empty($phone)) {
            return response()->json(null);
        }

        $normalizedPhone = $this->visitService->normalizePhone($phone);

        // Build all possible phone format variants to match legacy data
        // e.g. normalized = 201012345678, local = 1012345678, withZero = 01012345678
        $localPhone = substr($normalizedPhone, 2);       // strip country code '20'
        $withZero = '0'.$localPhone;                  // add leading zero

        $customer = Customer::query()
            ->whereIn('phone', [$normalizedPhone, $localPhone, $withZero])
            ->with(['animals' => function ($q) {
                $q->select('id', 'customer_id', 'name', 'species')
                    ->where('is_active', true);
            }])
            ->first();

        if (! $customer) {
            return response()->json(null);
        }

        return response()->json([
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'animals' => $customer->animals,
            'active_animals_count' => $customer->animals->count(),
        ]);
    }

    public function create()
    {

        // البحث عن خدمة "كشف" بالاسم بالتحديد بدل آخر خدمة مضافة (تصليح الخلط في المنتج الافتراضي)
        $consultationProduct = Product::query()
            ->active()
            ->where('type', 'service')
            ->whereRaw('TRIM(name) = ?', ['كشف'])
            ->first();

        $vaccines = Product::query()
            ->active()
            ->where('type', 'vaccination')
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'stock_status', 'quantity', 'track_stock']);

        $today = now()->toDateString();
        $vaccineUsableQty = VaccineBatch::query()
            ->where('expiry_date', '>=', $today)
            ->where('quantity_remaining', '>', 0)
            ->groupBy('product_id')
            ->select('product_id', DB::raw('SUM(quantity_remaining) as usable_qty'))
            ->pluck('usable_qty', 'product_id');

        $products = Product::query()
            ->active()
            ->whereIn('type', ['product', 'service', 'vaccination'])
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'type', 'stock_status', 'quantity', 'track_stock']);

        return view('customers.create', compact('consultationProduct', 'vaccines', 'vaccineUsableQty', 'products'));
    }

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

    public function exportExcel(Request $request): BinaryFileResponse
    {

        $exportQuery = $request->boolean('select_all_ids')
            ? $this->customersIndexQuery($request)

                ->select(['id', 'name', 'phone', 'animal_type'])

                ->orderBy('id')
            : Customer::query()

                ->whereKey($this->selectedIds($request))

                ->select(['id', 'name', 'phone', 'animal_type'])

                ->orderBy('id');

        return Excel::download(
            new class($exportQuery, $this->visitService) implements FromQuery, WithCustomChunkSize, WithCustomCsvSettings, WithHeadings, WithMapping
            {
                public function __construct(
                    private readonly \Illuminate\Database\Eloquent\Builder $query,
                    private readonly CustomerVisitService $visitService,
                ) {}

                public function query(): \Illuminate\Database\Eloquent\Builder
                {
                    return $this->query;
                }

                public function map($customer): array
                {
                    return [
                        $customer->name,
                        '="'.$this->visitService->normalizePhone((string) $customer->phone).'"',
                        $customer->animal_type,
                    ];
                }

                public function headings(): array
                {
                    return ['الاسم', 'رقم الهاتف', 'نوع الحيوان'];
                }

                public function chunkSize(): int
                {
                    return 1000;
                }

                public function getCsvSettings(): array
                {
                    return [
                        'use_bom' => true,
                        'output_encoding' => 'UTF-8',
                    ];
                }
            },
            'customers-export-'.now()->format('Y-m-d_H-i-s').'.csv',
            \Maatwebsite\Excel\Excel::CSV
        );
    }

    public function getPhones(Request $request): JsonResponse
    {

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
