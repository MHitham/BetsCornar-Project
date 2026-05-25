<?php

namespace App\Http\Controllers;

use App\Models\Vaccination;
use App\Services\CustomerVisitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VaccinationController extends Controller
{
    public function __construct(
        private readonly CustomerVisitService $visitService,
    ) {}

    public function complete(Vaccination $vaccination): JsonResponse
    {
        $vaccination->update(['is_completed' => true]);

        Cache::forget(dashboardKey('upcoming_vaccinations'));

        Cache::forget('dashboard.total_vaccinations');

        return response()->json(['success' => true]);
    }

    public function reschedule(Request $request, Vaccination $vaccination): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'next_dose_date' => ['required', 'date', 'after:today'],
        ], [
            'next_dose_date.required' => 'تاريخ الموعد القادم مطلوب.',
            'next_dose_date.date' => 'تاريخ الموعد القادم غير صالح.',
            'next_dose_date.after' => 'يجب أن يكون تاريخ الموعد القادم بعد اليوم.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $validated = $validator->validated();

        $vaccination->update(['is_completed' => true]);

        Vaccination::create([
            'customer_id' => $vaccination->customer_id,
            'product_id' => $vaccination->product_id,
            'invoice_id' => $vaccination->invoice_id,
            'vaccination_date' => today(),
            'next_dose_date' => $validated['next_dose_date'],
            'is_completed' => false,
        ]);

        Cache::forget(dashboardKey('upcoming_vaccinations'));

        Cache::forget('dashboard.total_vaccinations');

        return response()->json(['success' => true]);
    }

    public function index(Request $request): View
    {
        $query = Vaccination::query()->with(['customer', 'product', 'invoice']);
        $search = trim((string) $request->input('q', ''));
        $filter = $request->string('filter')->toString();
        $dateFilter = (string) $request->input('date', '');

        $defaultCompleted = $dateFilter !== '' ? 'all' : '0';
        $isCompleted = (string) $request->input('is_completed', $defaultCompleted);
        $sort = (string) $request->input('sort', 'latest');

        if ($search !== '') {
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        if ($isCompleted !== 'all') {
            $query->where('is_completed', $isCompleted === '1');
        }

        if ($dateFilter !== '') {
            $query->whereDate('next_dose_date', $dateFilter);
        }

        if ($sort === 'oldest') {
            $query->orderBy('vaccination_date', 'asc')->orderBy('id', 'asc');
        } elseif ($sort === 'upcoming') {

            $query->orderByRaw('next_dose_date IS NULL, next_dose_date ASC')->orderBy('id', 'asc');
        } else {
            $query->orderBy('vaccination_date', 'desc')->orderBy('id', 'desc');
        }

        $vaccinations = $query->paginate(15)->withQueryString();
        $threeDaysUpcoming = Vaccination::query()

            ->select([
                'id',
                'customer_id',
                'product_id',
                'next_dose_date',
                'vaccination_date',
                'is_completed',
            ])

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
            'dateFilter' => $dateFilter,
            'threeDaysUpcoming' => $threeDaysUpcoming,
            'customerVaccinationsMap' => $customerVaccinationsMap,
        ]);
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {

        $exportQuery = $request->boolean('select_all_ids')
            ? $this->vaccinationsExportQuery($request)

                ->select(['id', 'customer_id', 'product_id', 'next_dose_date'])
            : Vaccination::query()

                ->with(['customer', 'product'])

                ->whereKey($this->selectedIds($request))

                ->select(['id', 'customer_id', 'product_id', 'next_dose_date'])

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

                public function map($vaccination): array
                {
                    return [
                        $vaccination->customer?->name ?? '',
                        '="'.$this->visitService->normalizePhone((string) ($vaccination->customer?->phone ?? '')).'"',
                        $vaccination->customer?->animal_type ?? '',
                        $vaccination->product?->name ?? '',
                        $vaccination->next_dose_date?->format('Y-m-d') ?? '',
                    ];
                }

                public function headings(): array
                {
                    return ['الاسم', 'رقم الهاتف', 'نوع الحيوان', 'اسم التطعيم', 'موعد الجرعة القادمة'];
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
            'vaccinations-export-'.now()->format('Y-m-d_H-i-s').'.csv',
            \Maatwebsite\Excel\Excel::CSV
        );
    }

    public function getPhones(Request $request): JsonResponse
    {

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

        $dateFilter = (string) $request->input('date', '');
        if ($dateFilter !== '') {
            $query->whereDate('next_dose_date', $dateFilter);
        }

        $sort = (string) $request->input('sort', 'latest');
        if ($sort === 'oldest') {
            $query->orderBy('vaccination_date', 'asc')->orderBy('id', 'asc');
        } elseif ($sort === 'upcoming') {

            $query->orderByRaw('next_dose_date IS NULL, next_dose_date ASC')->orderBy('id', 'asc');
        } else {
            $query->orderBy('vaccination_date', 'desc')->orderBy('id', 'desc');
        }

        return $query;
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
