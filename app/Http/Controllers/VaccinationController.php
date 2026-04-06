<?php

namespace App\Http\Controllers;

// تم التعديل: استخدام FromQuery وWithMapping لتقليل استهلاك الذاكرة أثناء تصدير التطعيمات
use Maatwebsite\Excel\Concerns\FromQuery;
// تم التعديل: تحديد حجم الـ chunk أثناء التصدير الكبير
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
// تم التعديل: تجهيز الصفوف أثناء القراءة دون تحميل النتيجة كاملة في الذاكرة
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Vaccination;
use App\Services\CustomerVisitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
// تم الإضافة: استخدام الكاش لمسح مؤشرات التطعيمات في لوحة التحكم بعد التحديث
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VaccinationController extends Controller
{
    // تم الإضافة: حقن CustomerVisitService لإعادة استخدام منطق تطبيع الهاتف
    public function __construct(
        private readonly CustomerVisitService $visitService,
    ) {}

    public function complete(Vaccination $vaccination): JsonResponse
    {
        $vaccination->update(['is_completed' => true]);

        // تم الإضافة: تحديث كاش التطعيمات القادمة بعد إكمال الموعد الحالي
        Cache::forget(dashboardKey('upcoming_vaccinations'));
        // تم الإضافة: تحديث كاش إجمالي التطعيمات بعد تعديل حالة السجل
        Cache::forget('dashboard.total_vaccinations');

        return response()->json(['success' => true]);
    }

    public function reschedule(Request $request, Vaccination $vaccination): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'next_dose_date' => ['required', 'date', 'after:today'],
        ], [
            'next_dose_date.required' => 'تاريخ الموعد القادم مطلوب.',
            'next_dose_date.date'     => 'تاريخ الموعد القادم غير صالح.',
            'next_dose_date.after'    => 'يجب أن يكون تاريخ الموعد القادم بعد اليوم.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $validated = $validator->validated();

        // 1. Mark the current vaccination record as completed
        $vaccination->update(['is_completed' => true]);

        // 2. Create a new pending vaccination record for the upcoming dose
        Vaccination::create([
            'customer_id'      => $vaccination->customer_id,
            'product_id'       => $vaccination->product_id,
            'invoice_id'       => $vaccination->invoice_id,
            'vaccination_date' => today(),
            'next_dose_date'   => $validated['next_dose_date'],
            'is_completed'     => false,
        ]);

        // تم الإضافة: تحديث كاش التطعيمات القادمة بعد إعادة الجدولة وإنشاء موعد جديد
        Cache::forget(dashboardKey('upcoming_vaccinations'));
        // تم الإضافة: تحديث كاش إجمالي التطعيمات لأن إعادة الجدولة أنشأت سجلًا إضافيًا
        Cache::forget('dashboard.total_vaccinations');

        return response()->json(['success' => true]);
    }

    public function index(Request $request): View
    {
        $query = Vaccination::query()->with(['customer', 'product', 'invoice']);
        $search = trim((string) $request->input('q', ''));
        $filter = $request->string('filter')->toString();
        $isCompleted = (string) $request->input('is_completed', '0');
        $sort = (string) $request->input('sort', 'latest');

        // Move the Blade search logic into the controller without changing the UI inputs.
        if ($search !== '') {
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        // Move the Blade completion filter into the controller using the same defaults.
        if ($isCompleted !== 'all') {
            $query->where('is_completed', $isCompleted === '1');
        }

        // Move the Blade sort logic into the controller without changing the selected values.
        if ($sort === 'oldest') {
            $query->orderBy('vaccination_date', 'asc')->orderBy('id', 'asc');
        } elseif ($sort === 'upcoming') {
            // تم التعديل: إضافة ترتيب ثابت بـ id لضمان نتائج مستقرة في التصدير المعتمد على query
            $query->orderByRaw('next_dose_date IS NULL, next_dose_date ASC')->orderBy('id', 'asc');
        } else {
            $query->orderBy('vaccination_date', 'desc')->orderBy('id', 'desc');
        }

        $vaccinations = $query->paginate(15)->withQueryString();
        $threeDaysUpcoming = Vaccination::query()
            // تم التعديل: تحديد أعمدة التطعيمات المطلوبة فقط لنافذة مواعيد 3 أيام
            ->select([
                'id',
                'customer_id',
                'product_id',
                'next_dose_date',
                'vaccination_date',
                'is_completed',
            ])
            // تم التعديل: تحميل أعمدة العميل والمنتج اللازمة فقط بشكل آمن للواجهة الحالية
            ->with(['customer:id,name,phone,animal_type', 'product:id,name'])
            ->where('is_completed', false)
            ->whereNotNull('next_dose_date')
            ->whereDate('next_dose_date', '>=', today())
            ->whereDate('next_dose_date', '<=', today()->addDays(3))
            ->orderBy('next_dose_date', 'asc')
            ->get();

        $customerVaccinationsMap = [];
        foreach ($vaccinations as $vacc) {
            $customerId = $vacc->customer->id;

            if (! isset($customerVaccinationsMap[$customerId])) {
                $customerVaccinationsMap[$customerId] = [
                    'name' => $vacc->customer->name,
                    'animal_type' => $vacc->customer->animal_type,
                    'phone' => $vacc->customer->phone,
                    'vaccinations' => [],
                ];
            }

            $customerVaccinationsMap[$customerId]['vaccinations'][] = [
                'name' => $vacc->product->name ?? '—',
                'vaccination_date' => $vacc->vaccination_date?->format('Y-m-d') ?? '—',
                'next_dose_date' => $vacc->next_dose_date?->format('Y-m-d') ?? null,
                'is_completed' => $vacc->is_completed,
                'status' => $vacc->is_completed
                    ? 'done'
                    : ($vacc->next_dose_date
                        ? ($vacc->next_dose_date->lt(today())
                            ? 'late'
                            : ($vacc->next_dose_date->lte(today()->addDays(7))
                                ? 'soon'
                                : 'ok'))
                        : 'ok'),
            ];
        }

        return view('vaccinations.index', [
            'vaccinations' => $vaccinations,
            'q' => $search,
            'filter' => $filter,
            'isCompleted' => $isCompleted,
            'sort' => $sort,
            'threeDaysUpcoming' => $threeDaysUpcoming,
            'customerVaccinationsMap' => $customerVaccinationsMap,
        ]);
    }

    /**
     * تم الإضافة: تصدير التطعيمات المحددة أو كل نتائج التصفية الحالية إلى Excel.
     */
    public function exportExcel(Request $request): BinaryFileResponse
    {
        // تم التعديل: بناء Query مباشر للتصدير بدل تحميل كل التطعيمات في الذاكرة دفعة واحدة
        $exportQuery = $request->boolean('select_all_ids')
            ? $this->vaccinationsExportQuery($request)
                // تم التعديل: تحديد الأعمدة المطلوبة فقط مع الإبقاء على المفاتيح اللازمة للعلاقات
                ->select(['id', 'customer_id', 'product_id', 'next_dose_date'])
            : Vaccination::query()
                // تم التعديل: الإبقاء على العلاقات اللازمة لعرض بيانات العميل والتطعيم داخل التصدير
                ->with(['customer', 'product'])
                // تم التعديل: قصر التصدير على المعرّفات المحددة فقط
                ->whereKey($this->selectedIds($request))
                // تم التعديل: تحديد الأعمدة المطلوبة فقط مع الإبقاء على المفاتيح اللازمة للعلاقات
                ->select(['id', 'customer_id', 'product_id', 'next_dose_date'])
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

                // تم التعديل: تجهيز صف التصدير أثناء القراءة دون تجميع النتائج بالكامل في الذاكرة
                public function map($vaccination): array
                {
                    return [
                        $vaccination->customer?->name ?? '',
                        $this->visitService->normalizePhone((string) ($vaccination->customer?->phone ?? '')),
                        $vaccination->customer?->animal_type ?? '',
                        $vaccination->product?->name ?? '',
                        $vaccination->next_dose_date?->format('Y-m-d') ?? '',
                    ];
                }

                // تم التعديل: الإبقاء على نفس عناوين ملف Excel
                public function headings(): array
                {
                    return ['الاسم', 'رقم الهاتف', 'نوع الحيوان', 'اسم التطعيم', 'موعد الجرعة القادمة'];
                }

                // تم التعديل: تحديد حجم الـ chunk لتقليل استهلاك الذاكرة أثناء التصدير
                public function chunkSize(): int
                {
                    return 1000;
                }
            },
            'vaccinations-export-'.now()->format('Y-m-d_H-i-s').'.xlsx'
        );
    }

    /**
     * تم الإضافة: جلب أرقام الهواتف المحددة أو كل نتائج التصفية الحالية مع إزالة التكرار.
     */
    public function getPhones(Request $request): JsonResponse
    {
        // تم الإضافة: إعادة استخدام نفس استعلام التصفية الحالي قبل تجميع الهواتف
        $phones = ($request->boolean('select_all_ids')
            ? $this->vaccinationsExportQuery($request)->get()->pluck('customer.phone')
            : Vaccination::query()
                ->with('customer')
                ->whereKey($this->selectedIds($request))
                ->get()
                ->pluck('customer.phone'))
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
     * تم الإضافة: توحيد فلاتر صفحة التطعيمات لاستخدامها في التصدير وجلب الأرقام.
     */
    private function vaccinationsExportQuery(Request $request)
    {
        $query = Vaccination::query()->with(['customer', 'product']);

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $isCompleted = (string) $request->input('is_completed', '0');
        if ($isCompleted !== 'all') {
            $query->where('is_completed', $isCompleted === '1');
        }

        $sort = (string) $request->input('sort', 'latest');
        if ($sort === 'oldest') {
            $query->orderBy('vaccination_date', 'asc')->orderBy('id', 'asc');
        } elseif ($sort === 'upcoming') {
            // تم التعديل: إضافة ترتيب ثابت بـ id لضمان نتائج مستقرة في التصدير المعتمد على query
            $query->orderByRaw('next_dose_date IS NULL, next_dose_date ASC')->orderBy('id', 'asc');
        } else {
            $query->orderBy('vaccination_date', 'desc')->orderBy('id', 'desc');
        }

        return $query;
    }

    /**
     * تم الإضافة: قراءة المعرّفات المحددة من الطلب بصيغة موحدة وآمنة.
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
