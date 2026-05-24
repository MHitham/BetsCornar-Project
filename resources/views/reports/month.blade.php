@extends('layouts.app')

@section('title', "تقرير {$monthName} {$year}")
@section('page-title', "تقرير {$monthName} {$year}")

@section('content')

{{-- Header + Back --}}
<div class="card mb-4 border-0 shadow-sm report-card" style="background: linear-gradient(135deg, rgba(13,110,253,0.06) 0%, rgba(25,135,84,0.04) 100%);">
    <div class="card-body py-3 px-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h5 class="fw-bold mb-1" style="color: #2d3748;">
                    <i class="bi bi-calendar2-month-fill me-2" style="color: #0d6efd;"></i>
                    تقرير شهر {{ $monthName }} — {{ $year }}
                </h5>
                <p class="text-muted small mb-0">تفاصيل الأداء المالي والعمليات خلال الشهر</p>
            </div>
            <a href="{{ route('reports.index', ['year' => $year]) }}" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">
                <i class="bi bi-arrow-right me-1"></i>
                العودة للتقرير السنوي
            </a>
        </div>
    </div>
</div>

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    {{-- Revenue --}}
    <div class="col-6 col-xl-3">
        <div class="card h-100 border-0 shadow-sm position-relative overflow-hidden report-card">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="report-icon" style="background: linear-gradient(135deg, #198754, #20c997);">
                        <i class="bi bi-cash-coin text-white" style="font-size: 16px;"></i>
                    </div>
                    <span class="text-muted small fw-semibold">إجمالي الإيرادات</span>
                </div>
                <div class="fw-bold fs-4" style="color: #198754;">
                    {{ number_format($revenueSummary['total']) }}
                    <small class="fs-6 fw-normal text-muted">{{ __('messages.currency') }}</small>
                </div>
                <div class="text-muted small mt-1">
                    متوسط الفاتورة: <strong>{{ number_format($revenueSummary['avg']) }}</strong> {{ __('messages.currency') }}
                </div>
            </div>
            <div class="position-absolute bottom-0 end-0 opacity-25" style="font-size: 58px; margin-left: -10px; margin-bottom: -10px; color: #198754;">
                <i class="bi bi-cash-stack"></i>
            </div>
        </div>
    </div>

    {{-- Expenses --}}
    <div class="col-6 col-xl-3">
        <div class="card h-100 border-0 shadow-sm position-relative overflow-hidden report-card">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="report-icon" style="background: linear-gradient(135deg, #dc3545, #e35d6a);">
                        <i class="bi bi-receipt text-white" style="font-size: 16px;"></i>
                    </div>
                    <span class="text-muted small fw-semibold">إجمالي المصروفات</span>
                </div>
                <div class="fw-bold fs-4 text-danger">
                    {{ number_format($expensesSummary['total']) }}
                    <small class="fs-6 fw-normal text-muted">{{ __('messages.currency') }}</small>
                </div>
                <div class="text-muted small mt-1">
                    عدد المصروفات: <strong>{{ $expensesSummary['count'] }}</strong>
                </div>
            </div>
            <div class="position-absolute bottom-0 end-0 opacity-25" style="font-size: 58px; margin-left: -10px; margin-bottom: -10px; color: #dc3545;">
                <i class="bi bi-receipt-cutoff"></i>
            </div>
        </div>
    </div>

    {{-- Net Profit --}}
    <div class="col-6 col-xl-3">
        <div class="card h-100 border-0 shadow-sm position-relative overflow-hidden report-card">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="report-icon" style="background: linear-gradient(135deg, {{ $profitPositive ? '#0d6efd, #6ea8fe' : '#ffc107, #ffcd39' }});">
                        <i class="bi bi-graph-up-arrow text-white" style="font-size: 16px;"></i>
                    </div>
                    <span class="text-muted small fw-semibold">صافي الربح</span>
                </div>
                <div class="fw-bold fs-4 {{ $profitPositive ? 'text-primary' : 'text-danger' }}">
                    {{ $profitPositive ? '+' : '' }}{{ number_format($netProfit) }}
                    <small class="fs-6 fw-normal text-muted">{{ __('messages.currency') }}</small>
                </div>
                <div class="text-muted small mt-1">
                    هامش الربح:
                    <strong>
                        @if($revenueSummary['total'] > 0)
                            {{ number_format(($netProfit / $revenueSummary['total']) * 100, 1) }}%
                        @else
                            0%
                        @endif
                    </strong>
                </div>
            </div>
            <div class="position-absolute bottom-0 end-0 opacity-25" style="font-size: 58px; margin-left: -10px; margin-bottom: -10px; color: {{ $profitPositive ? '#0d6efd' : '#ffc107' }};">
                <i class="bi bi-{{ $profitPositive ? 'graph-up-arrow' : 'graph-down-arrow' }}"></i>
            </div>
        </div>
    </div>

    {{-- Visits --}}
    <div class="col-6 col-xl-3">
        <div class="card h-100 border-0 shadow-sm position-relative overflow-hidden report-card">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="report-icon" style="background: linear-gradient(135deg, #6f42c1, #a855f7);">
                        <i class="bi bi-person-check text-white" style="font-size: 16px;"></i>
                    </div>
                    <span class="text-muted small fw-semibold">الزيارات والفواتير</span>
                </div>
                <div class="fw-bold fs-4" style="color: #6f42c1;">
                    {{ number_format($revenueSummary['count']) }}
                    <small class="fs-6 fw-normal text-muted">فاتورة</small>
                </div>
                <div class="text-muted small mt-1">
                    زيارات: <strong>{{ $revenueSummary['customer_visits'] }}</strong>
                    · بيع سريع: <strong>{{ $revenueSummary['quick_sales'] }}</strong>
                </div>
            </div>
            <div class="position-absolute bottom-0 end-0 opacity-25" style="font-size: 58px; margin-left: -10px; margin-bottom: -10px; color: #6f42c1;">
                <i class="bi bi-people-fill"></i>
            </div>
        </div>
    </div>

    {{-- بطاقة تكلفة البضاعة المباعة (COGS) --}}
    <div class="col-6 col-xl-3">
        <div class="card h-100 border-0 shadow-sm position-relative overflow-hidden report-card">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="report-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                        <i class="bi bi-box-seam-fill text-white" style="font-size: 16px;"></i>
                    </div>
                    <span class="text-muted small fw-semibold">تكلفة البضاعة</span>
                </div>
                <div class="fw-bold fs-4" style="color: #f59e0b;">
                    {{ number_format($cogs) }}
                    <small class="fs-6 fw-normal text-muted">{{ __('messages.currency') }}</small>
                </div>
                <div class="text-muted small mt-1">
                    تكلفة البضائع المباعة في الشهر
                </div>
            </div>
            <div class="position-absolute bottom-0 end-0 opacity-25" style="font-size: 58px; margin-left: -10px; margin-bottom: -10px; color: #f59e0b;">
                <i class="bi bi-box-seam-fill"></i>
            </div>
        </div>
    </div>

    {{-- بطاقة الربح الإجمالي --}}
    <div class="col-6 col-xl-3">
        <div class="card h-100 border-0 shadow-sm position-relative overflow-hidden report-card">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="report-icon" style="background: linear-gradient(135deg, #0d6efd, #6ea8fe);">
                        <i class="bi bi-graph-up-arrow text-white" style="font-size: 16px;"></i>
                    </div>
                    <span class="text-muted small fw-semibold">الربح الإجمالي</span>
                </div>
                <div class="fw-bold fs-4 {{ $grossPositive ? 'text-primary' : 'text-danger' }}">
                    {{ $grossPositive ? '+' : '' }}{{ number_format($grossProfit) }}
                    <small class="fs-6 fw-normal text-muted">{{ __('messages.currency') }}</small>
                </div>
                <div class="text-muted small mt-1">
                    الإيرادات مطروحاً منها تكلفة البضائع
                </div>
            </div>
            <div class="position-absolute bottom-0 end-0 opacity-25" style="font-size: 58px; margin-left: -10px; margin-bottom: -10px; color: #0d6efd;">
                <i class="bi bi-graph-up-arrow"></i>
            </div>
        </div>
    </div>

</div>

{{-- Invoices & Expenses Side by Side --}}
<div class="row g-4 mb-4">
    {{-- Invoices Table --}}
    <div class="col-xl-7">
        <div class="card border-0 shadow-sm h-100 report-card">
            <div class="card-header bg-transparent border-0 pt-3 px-4">
                <div class="d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0">
                        <i class="bi bi-receipt-cutoff text-primary me-2"></i>
                        فواتير الشهر
                    </h6>
                    <span class="badge rounded-pill" style="background: rgba(13,110,253,0.1); color: #0d6efd; font-size: 11px;">
                        {{ $invoices->count() }} فاتورة
                    </span>
                </div>
            </div>
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-hover align-middle mb-0 table-sm">
                    <thead class="sticky-top" style="background: #f8f9fa;">
                        <tr>
                            <th class="px-4 text-muted small">#</th>
                            <th class="text-muted small">العميل</th>
                            <th class="text-muted small">المصدر</th>
                            <th class="text-muted small">الإجمالي</th>
                            <th class="text-muted small">التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($invoices->isNotEmpty())
                            @foreach ($invoices as $invoice)
                                <tr>
                                    <td class="px-4">
                                        <a href="{{ route('invoices.show', $invoice) }}" class="text-decoration-none fw-semibold" style="color: #0d6efd; font-size: 12px;">
                                            {{ $invoice->invoice_number }}
                                        </a>
                                    </td>
                                    <td class="small">{{ $invoice->customer_name }}</td>
                                    <td>
                                        @if($invoice->source === 'customer')
                                            <span class="badge rounded-pill" style="background: rgba(111,66,193,0.1); color: #6f42c1; font-size: 10px;">زيارة</span>
                                        @else
                                            <span class="badge rounded-pill" style="background: rgba(13,110,253,0.1); color: #0d6efd; font-size: 10px;">بيع سريع</span>
                                        @endif
                                    </td>
                                    <td class="fw-semibold small" style="color: #198754;">{{ number_format($invoice->total) }} {{ __('messages.currency') }}</td>
                                    <td class="text-muted small">{{ $invoice->created_at->format('m/d H:i') }}</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-3 d-block mb-2 opacity-50"></i>
                                    لا توجد فواتير في هذا الشهر
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Expenses Table --}}
    <div class="col-xl-5">
        <div class="card border-0 shadow-sm h-100 report-card">
            <div class="card-header bg-transparent border-0 pt-3 px-4">
                <div class="d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0">
                        <i class="bi bi-wallet2 text-danger me-2"></i>
                        مصروفات الشهر
                    </h6>
                    <span class="badge rounded-pill" style="background: rgba(220,53,69,0.1); color: #dc3545; font-size: 11px;">
                        {{ $expenses->count() }} مصروف
                    </span>
                </div>
            </div>
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-hover align-middle mb-0 table-sm">
                    <thead class="sticky-top" style="background: #f8f9fa;">
                        <tr>
                            <th class="px-4 text-muted small">البيان</th>
                            <th class="text-muted small">المبلغ</th>
                            <th class="text-muted small">التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($expenses->isNotEmpty())
                            @foreach ($expenses as $expense)
                                <tr>
                                    <td class="px-4 small">{{ $expense->description }}</td>
                                    <td class="fw-semibold small text-danger">{{ number_format($expense->amount) }} {{ __('messages.currency') }}</td>
                                    <td class="text-muted small">{{ \Carbon\Carbon::parse($expense->expense_date)->format('m/d') }}</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-3 d-block mb-2 opacity-50"></i>
                                    لا توجد مصروفات في هذا الشهر
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Top Products & Vaccinations --}}
<div class="row g-4 mb-4">
    {{-- Top Products --}}
    <div class="col-xl-6">
        <div class="card border-0 shadow-sm h-100 report-card">
            <div class="card-header bg-transparent border-0 pt-3 px-4">
                <div class="d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0">
                        <i class="bi bi-trophy-fill text-warning me-2"></i>
                        أعلى المنتجات مبيعاً
                    </h6>
                    @if($topProducts->isNotEmpty())
                        <span class="badge rounded-pill" style="background: rgba(255,193,7,0.12); color: #b45309; font-size: 11px;">
                            {{ $topProducts->count() }} منتج
                        </span>
                    @endif
                </div>
            </div>
            <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                <table class="table table-hover align-middle mb-0 table-sm">
                    <thead class="sticky-top" style="background: #f8f9fa;">
                        <tr>
                            <th class="px-4 text-muted small">#</th>
                            <th class="text-muted small">المنتج</th>
                            <th class="text-muted small">النوع</th>
                            <th class="text-muted small">الكمية</th>
                            <th class="text-muted small">المبيعات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($topProducts->isNotEmpty())
                            @foreach ($topProducts as $product)
                                @php
                                    $typeMap = [
                                        'product'     => ['منتج',   'primary'],
                                        'service'     => ['خدمة',   'success'],
                                        'vaccination' => ['تطعيم',  'info'],
                                    ];
                                    [$typeLabel, $typeColor] = $typeMap[$product->type] ?? [$product->type, 'secondary'];
                                    $rankColors = ['#ffd700', '#c0c0c0', '#cd7f32'];
                                @endphp
                                <tr>
                                    <td class="px-4">
                                        @if($loop->iteration <= 3)
                                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle fw-bold" style="width: 24px; height: 24px; background: {{ $rankColors[$loop->index] }}20; color: {{ $rankColors[$loop->index] }}; font-size: 11px; border: 1.5px solid {{ $rankColors[$loop->index] }}40;">
                                                {{ $loop->iteration }}
                                            </span>
                                        @else
                                            <span class="text-muted small">{{ $loop->iteration }}</span>
                                        @endif
                                    </td>
                                    <td class="fw-semibold small">{{ $product->name }}</td>
                                    <td>
                                        <span class="badge bg-{{ $typeColor }} bg-opacity-10 text-{{ $typeColor }}" style="font-size: 10px;">{{ $typeLabel }}</span>
                                    </td>
                                    <td class="font-monospace small">{{ number_format((float) $product->total_quantity) }}</td>
                                    <td class="font-monospace fw-bold small" style="color: #198754;">{{ number_format((float) $product->total_sales) }}</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <i class="bi bi-bar-chart-line fs-3 d-block mb-2 opacity-50"></i>
                                    لا توجد بيانات
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Completed Vaccinations --}}
    <div class="col-xl-6">
        <div class="card border-0 shadow-sm h-100 report-card">
            <div class="card-header bg-transparent border-0 pt-3 px-4">
                <div class="d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0">
                        <i class="bi bi-shield-check text-success me-2"></i>
                        التطعيمات المكتملة
                    </h6>
                    <span class="badge rounded-pill" style="background: rgba(25,135,84,0.1); color: #198754; font-size: 11px;">
                        {{ $vaccinations->count() }} تطعيم
                    </span>
                </div>
            </div>
            <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                <table class="table table-hover align-middle mb-0 table-sm">
                    <thead class="sticky-top" style="background: #f8f9fa;">
                        <tr>
                            <th class="px-4 text-muted small">العميل</th>
                            <th class="text-muted small">التطعيم</th>
                            <th class="text-muted small">تاريخ الإتمام</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($vaccinations->isNotEmpty())
                            @foreach ($vaccinations as $vaccination)
                                <tr>
                                    <td class="px-4 small fw-semibold">{{ $vaccination->customer->name ?? '—' }}</td>
                                    <td class="small">
                                        <span class="badge rounded-pill" style="background: rgba(13,202,240,0.1); color: #0dcaf0; font-size: 10px;">
                                            <i class="bi bi-shield-plus me-1"></i>
                                            {{ $vaccination->product->name ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="text-muted small">{{ $vaccination->updated_at->format('m/d') }}</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted">
                                    <i class="bi bi-shield fs-3 d-block mb-2 opacity-50"></i>
                                    لا توجد تطعيمات مكتملة
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Stock Additions (Vaccine Batches Received) --}}
@if($stockAdditions->isNotEmpty())
<div class="card border-0 shadow-sm mb-4" style="border-radius: 12px;">
    <div class="card-header bg-transparent border-0 pt-3 px-4">
        <div class="d-flex align-items-center justify-content-between">
            <h6 class="fw-bold mb-0">
                <i class="bi bi-box-seam-fill text-info me-2"></i>
                إضافات المخزون (التطعيمات الواردة)
            </h6>
            <span class="badge rounded-pill" style="background: rgba(13,202,240,0.1); color: #0891b2; font-size: 11px;">
                {{ $stockAdditions->count() }} تطعيم
            </span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-sm">
            <thead style="background: rgba(13,202,240,0.04);">
                <tr>
                    <th class="px-4 text-muted small">اللقاح</th>
                    <th class="text-muted small">رقم التطعيم</th>
                    <th class="text-muted small">الكمية المستلمة</th>
                    <th class="text-muted small">المتبقي</th>
                    <th class="text-muted small">تاريخ الاستلام</th>
                    <th class="text-muted small">تاريخ الانتهاء</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($stockAdditions as $batch)
                    @php
                        $isExpired = \Carbon\Carbon::parse($batch->expiry_date)->lt(now());
                    @endphp
                    <tr class="{{ $isExpired ? 'opacity-50' : '' }}">
                        <td class="px-4 fw-semibold small">{{ $batch->product->name ?? '—' }}</td>
                        <td class="font-monospace small">{{ $batch->batch_code ?? '—' }}</td>
                        <td class="font-monospace small">{{ number_format($batch->quantity_received, 2) }}</td>
                        <td class="font-monospace small {{ $batch->quantity_remaining <= 0 ? 'text-danger' : 'text-success' }}">
                            {{ number_format($batch->quantity_remaining, 2) }}
                        </td>
                        <td class="text-muted small">{{ \Carbon\Carbon::parse($batch->received_date)->format('m/d') }}</td>
                        <td class="small">
                            @if($isExpired)
                                <span class="badge bg-danger bg-opacity-15 text-danger" style="font-size: 10px;">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    منتهي {{ \Carbon\Carbon::parse($batch->expiry_date)->format('Y-m-d') }}
                                </span>
                            @else
                                <span class="text-muted">{{ \Carbon\Carbon::parse($batch->expiry_date)->format('Y-m-d') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- New Customers Table --}}
@if($newCustomers->isNotEmpty())
<div class="card border-0 shadow-sm mb-4" style="border-radius: 12px;">
    <div class="card-header bg-transparent border-0 pt-3 px-4">
        <div class="d-flex align-items-center justify-content-between">
            <h6 class="fw-bold mb-0">
                <i class="bi bi-person-plus-fill text-info me-2"></i>
                العملاء الجدد
            </h6>
            <span class="badge rounded-pill" style="background: rgba(8,145,178,0.1); color: #0891b2; font-size: 11px;">
                {{ $newCustomers->count() }} عميل
            </span>
        </div>
    </div>
    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
        <table class="table table-hover align-middle mb-0 table-sm">
            <thead class="sticky-top" style="background: #f8f9fa;">
                <tr>
                    <th class="px-4 text-muted small">الاسم</th>
                    <th class="text-muted small">رقم الهاتف</th>
                    <th class="text-muted small">تاريخ الإضافة</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($newCustomers as $customer)
                    <tr>
                        <td class="px-4 fw-semibold small">
                            <a href="{{ route('customers.show', $customer) }}" class="text-decoration-none" style="color: #0d6efd;">
                                {{ $customer->name }}
                            </a>
                        </td>
                        <td class="font-monospace small">{{ $customer->phone ?? '—' }}</td>
                        <td class="text-muted small">{{ $customer->created_at->format('Y-m-d') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection
