@extends('layouts.app')

@section('title', 'تقرير الربحية — ' . $year)
@section('page-title', 'تقرير الربحية')


@push('styles')
<style>
    .report-card {
        border-radius: 12px !important;
        border: none !important;
    }
    .report-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
</style>
@endpush

@section('content')


<div class="card mb-4 border-0 shadow-sm" style="background: linear-gradient(135deg, rgba(13,110,253,0.06) 0%, rgba(25,135,84,0.04) 100%); border-radius: 12px;">
    <div class="card-body py-3 px-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h5 class="fw-bold mb-1" style="color: #2d3748;">
                    <i class="bi bi-graph-up-arrow me-2" style="color: #0d6efd;"></i>
                    تقرير الربحية — {{ $year }}
                </h5>
                <p class="text-muted small mb-0">تحليل الإيرادات والتكاليف والأرباح وديون الطرفين</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                
                <a href="{{ route('reports.index', ['year' => $year]) }}" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">
                    <i class="bi bi-arrow-right me-1"></i>
                    ← التقرير السنوي
                </a>
                
                <form method="GET" action="{{ route('reports.profitability') }}" class="d-flex align-items-center gap-2">
                    <label for="year" class="form-label mb-0 fw-semibold text-muted small text-nowrap">
                        <i class="bi bi-calendar3 me-1"></i>السنة
                    </label>
                    <select name="year" id="year" class="form-select form-select-sm" style="width: 110px; border-radius: 8px;" onchange="this.form.submit()">
                        @foreach($availableYears as $y)
                            <option value="{{ $y }}" @selected($year == $y)>{{ $y }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>
    </div>
</div>


<div class="row g-3 mb-4">

    
    <div class="col-6 col-xl-2">
        <div class="card h-100 border-0 shadow-sm position-relative overflow-hidden report-card">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="report-icon" style="background: linear-gradient(135deg, #198754, #20c997);">
                        <i class="bi bi-cash-coin text-white" style="font-size: 16px;"></i>
                    </div>
                    <span class="text-muted small fw-semibold">إجمالي الإيرادات</span>
                </div>
                <div class="fw-bold fs-5" style="color: #198754;">
                    {{ number_format($revenue) }}
                    <small class="fs-6 fw-normal text-muted">{{ __('messages.currency') }}</small>
                </div>
            </div>
            <div class="position-absolute bottom-0 end-0 opacity-25" style="font-size: 54px; margin-left: -10px; margin-bottom: -10px; color: #198754;">
                <i class="bi bi-cash-stack"></i>
            </div>
        </div>
    </div>

    
    <div class="col-6 col-xl-2">
        <div class="card h-100 border-0 shadow-sm position-relative overflow-hidden report-card">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="report-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                        <i class="bi bi-box-seam-fill text-white" style="font-size: 16px;"></i>
                    </div>
                    <span class="text-muted small fw-semibold">تكلفة البضاعة</span>
                </div>
                <div class="fw-bold fs-5" style="color: #f59e0b;">
                    {{ number_format($cogs) }}
                    <small class="fs-6 fw-normal text-muted">{{ __('messages.currency') }}</small>
                </div>
            </div>
            <div class="position-absolute bottom-0 end-0 opacity-25" style="font-size: 54px; margin-left: -10px; margin-bottom: -10px; color: #f59e0b;">
                <i class="bi bi-box-seam-fill"></i>
            </div>
        </div>
    </div>

    
    <div class="col-6 col-xl-2">
        <div class="card h-100 border-0 shadow-sm position-relative overflow-hidden report-card">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="report-icon" style="background: linear-gradient(135deg, #0d6efd, #6ea8fe);">
                        <i class="bi bi-graph-up-arrow text-white" style="font-size: 16px;"></i>
                    </div>
                    <span class="text-muted small fw-semibold">الربح الإجمالي</span>
                </div>
                <div class="fw-bold fs-5 {{ $grossPositive ? 'text-primary' : 'text-danger' }}">
                    {{ $grossPositive ? '+' : '' }}{{ number_format($grossProfit) }}
                    <small class="fs-6 fw-normal text-muted">{{ __('messages.currency') }}</small>
                </div>
            </div>
            <div class="position-absolute bottom-0 end-0 opacity-25" style="font-size: 54px; margin-left: -10px; margin-bottom: -10px; color: #0d6efd;">
                <i class="bi bi-graph-up-arrow"></i>
            </div>
        </div>
    </div>

    
    <div class="col-6 col-xl-2">
        <div class="card h-100 border-0 shadow-sm position-relative overflow-hidden report-card">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="report-icon" style="background: linear-gradient(135deg, #dc3545, #e35d6a);">
                        <i class="bi bi-receipt text-white" style="font-size: 16px;"></i>
                    </div>
                    <span class="text-muted small fw-semibold">المصروفات</span>
                </div>
                <div class="fw-bold fs-5 text-danger">
                    {{ number_format($expenses) }}
                    <small class="fs-6 fw-normal text-muted">{{ __('messages.currency') }}</small>
                </div>
            </div>
            <div class="position-absolute bottom-0 end-0 opacity-25" style="font-size: 54px; margin-left: -10px; margin-bottom: -10px; color: #dc3545;">
                <i class="bi bi-receipt-cutoff"></i>
            </div>
        </div>
    </div>

    
    <div class="col-6 col-xl-4">
        <div class="card h-100 border-0 shadow-sm position-relative overflow-hidden report-card">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="report-icon" style="background: linear-gradient(135deg, {{ $netPositive ? '#198754, #20c997' : '#ffc107, #ffcd39' }});">
                        <i class="bi bi-trophy-fill text-white" style="font-size: 16px;"></i>
                    </div>
                    <span class="text-muted small fw-semibold">صافي الربح</span>
                </div>
                <div class="d-flex align-items-baseline gap-3 flex-wrap">
                    <div class="fw-bold fs-5 {{ $netPositive ? 'text-success' : 'text-danger' }}">
                        {{ $netPositive ? '+' : '' }}{{ number_format($netProfit) }}
                        <small class="fs-6 fw-normal text-muted">{{ __('messages.currency') }}</small>
                    </div>
                    <span class="badge rounded-pill fw-bold" style="background: {{ $netPositive ? 'rgba(25,135,84,0.12)' : 'rgba(220,53,69,0.12)' }}; color: {{ $netPositive ? '#198754' : '#dc3545' }}; font-size: 13px; padding: 5px 12px;">
                        {{ $margin }}%
                    </span>
                </div>
                <div class="text-muted small mt-1">هامش الربح الصافي للسنة</div>
            </div>
            <div class="position-absolute bottom-0 end-0 opacity-25" style="font-size: 54px; margin-left: -10px; margin-bottom: -10px; color: {{ $netPositive ? '#198754' : '#ffc107' }};">
                <i class="bi bi-trophy-fill"></i>
            </div>
        </div>
    </div>

</div>


<div class="card report-card border-0 shadow-sm mb-4 overflow-hidden">
    <div class="card-body py-3 px-4">
        <div class="d-flex align-items-center justify-content-center gap-0 flex-wrap" style="gap: 0 !important;">

            
            <div class="text-center px-3 py-2">
                <div class="small text-muted mb-1">إيرادات</div>
                <div class="fw-bold fs-6" style="color:#198754;">{{ number_format($revenue) }}</div>
            </div>

            
            <div class="text-center px-2 text-muted">
                <div class="small mb-1" style="color:#f59e0b; font-weight:600;">− تكلفة</div>
                <div class="fw-bold" style="color:#f59e0b;">{{ number_format($cogs) }}</div>
            </div>

            
            <div class="px-2 fw-bold text-muted fs-5">=</div>

            
            <div class="text-center px-3 py-2 rounded-3" style="background: rgba(13,110,253,0.06);">
                <div class="small text-muted mb-1">ربح إجمالي</div>
                <div class="fw-bold fs-6 {{ $grossPositive ? 'text-primary' : 'text-danger' }}">
                    {{ number_format($grossProfit) }}
                </div>
            </div>

            
            <div class="text-center px-2">
                <div class="small mb-1" style="color:#dc3545; font-weight:600;">− مصروفات</div>
                <div class="fw-bold text-danger">{{ number_format($expenses) }}</div>
            </div>

            
            <div class="px-2 fw-bold text-muted fs-5">=</div>

            
            <div class="text-center px-3 py-2 rounded-3" 
                 style="background: {{ $netPositive ? 'rgba(25,135,84,0.08)' : 'rgba(220,53,69,0.08)' }};">
                <div class="small text-muted mb-1">صافي ربح</div>
                <div class="fw-bold fs-6 {{ $netPositive ? 'text-success' : 'text-danger' }}">
                    {{ number_format($netProfit) }}
                </div>
                <span class="badge rounded-pill mt-1" 
                      style="background: {{ $netPositive ? 'rgba(25,135,84,0.15)' : 'rgba(220,53,69,0.15)' }}; 
                             color: {{ $netPositive ? '#198754' : '#dc3545' }}; font-size:11px;">
                    {{ $margin }}%
                </span>
            </div>

        </div>
    </div>
</div>


<div class="card border-0 shadow-sm mb-4 report-card">
    <div class="card-header bg-transparent border-0 pt-3 px-4">
        <div class="d-flex align-items-center justify-content-between">
            <h6 class="fw-bold mb-0">
                <i class="bi bi-bar-chart-fill text-primary me-2"></i>
                الربح الإجمالي مقابل المصروفات شهرياً
            </h6>
            <span class="badge rounded-pill text-muted" style="background: rgba(0,0,0,0.05); font-size: 11px;">{{ $year }}</span>
        </div>
    </div>
    <div class="card-body px-4 pb-4">
        <div id="profitabilityChart"></div>
    </div>
</div>




<div class="row g-4 mb-4">

    
    <div class="col-xl-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
            <div class="card-header bg-transparent border-0 pt-3 px-4">
                <div class="d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0">
                        <i class="bi bi-truck text-danger me-2"></i>
                        ديون على العيادة (للموردين)
                    </h6>
                    @if($supplierDebts->isNotEmpty())
                        <span class="badge rounded-pill" style="background: rgba(220,53,69,0.1); color: #dc3545; font-size: 11px;">
                            {{ $supplierDebts->count() }} طلب
                        </span>
                    @endif
                </div>
            </div>
            <div class="card-body p-0">
                @if($supplierDebts->isEmpty())
                    <div class="d-flex flex-column align-items-center justify-content-center py-5 text-muted">
                        <i class="bi bi-check-circle-fill text-success fs-1 mb-2"></i>
                        <p class="mb-0 fw-semibold">لا توجد ديون للموردين</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 table-sm">
                            <thead class="sticky-top" style="background: #f8f9fa;">
                                <tr>
                                    <th class="px-4 text-muted small">المورد</th>
                                    <th class="text-muted small">رقم الطلب</th>
                                    <th class="text-muted small">الإجمالي</th>
                                    <th class="text-muted small">المدفوع</th>
                                    <th class="text-muted small">المتبقي</th>
                                    <th class="text-muted small">التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($supplierDebts as $po)
                                    @php
                                        $rem = $po->total_cost - $po->amount_paid;
                                    @endphp
                                    <tr>
                                        <td class="px-4 fw-semibold small">
                                            @if($po->supplier)
                                                <a href="{{ route('purchases.show', $po->id) }}" class="text-decoration-none fw-bold" style="color: #0d6efd;">
                                                    {{ $po->supplier->name }}
                                                </a>
                                            @else
                                                <a href="{{ route('purchases.show', $po->id) }}" class="text-decoration-none fw-bold text-muted">
                                                    —
                                                </a>
                                            @endif
                                        </td>
                                        <td><span class="badge rounded-pill" style="background: rgba(108,117,125,0.1); color: #6c757d; font-size: 10px;">{{ $po->order_number }}</span></td>
                                        <td class="small font-monospace">{{ number_format($po->total_cost) }}</td>
                                        <td class="small font-monospace text-success">{{ number_format($po->amount_paid) }}</td>
                                        <td class="fw-bold small font-monospace text-danger">{{ number_format($rem) }}</td>
                                        <td class="text-muted small">{{ $po->purchased_at->format('Y-m-d') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot style="background: rgba(220,53,69,0.04);">
                                <tr>
                                    <td colspan="4" class="px-4 fw-bold text-end">إجمالي الديون للموردين:</td>
                                    <td colspan="2" class="fw-bold text-danger font-monospace">{{ number_format($totalSupplierDebts) }} {{ __('messages.currency') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    
    <div class="col-xl-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
            <div class="card-header bg-transparent border-0 pt-3 px-4">
                <div class="d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0">
                        <i class="bi bi-people-fill text-warning me-2"></i>
                        ديون على العملاء
                    </h6>
                    @if($customerDebts->isNotEmpty())
                        <span class="badge rounded-pill" style="background: rgba(255,193,7,0.15); color: #b45309; font-size: 11px;">
                            {{ $customerDebts->count() }} فاتورة
                        </span>
                    @endif
                </div>
            </div>
            <div class="card-body p-0">
                @if($customerDebts->isEmpty())
                    <div class="d-flex flex-column align-items-center justify-content-center py-5 text-muted">
                        <i class="bi bi-check-circle-fill text-success fs-1 mb-2"></i>
                        <p class="mb-0 fw-semibold">لا توجد ديون على العملاء</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 table-sm">
                            <thead class="sticky-top" style="background: #f8f9fa;">
                                <tr>
                                    <th class="px-4 text-muted small">العميل</th>
                                    <th class="text-muted small">رقم الفاتورة</th>
                                    <th class="text-muted small">الإجمالي</th>
                                    <th class="text-muted small">المدفوع</th>
                                    <th class="text-muted small">المتبقي</th>
                                    <th class="text-muted small">التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($customerDebts as $inv)
                                    @php
                                        $rem = $inv->total - $inv->amount_paid;
                                    @endphp
                                    <tr>
                                        <td class="px-4 fw-semibold small">
                                            @if($inv->customer)
                                                <a href="{{ route('invoices.show', $inv->id) }}" class="text-decoration-none fw-bold" style="color: #0d6efd;">
                                                    {{ $inv->customer->name }}
                                                </a>
                                            @else
                                                <a href="{{ route('invoices.show', $inv->id) }}" class="text-decoration-none fw-bold text-muted">
                                                    {{ $inv->customer_name }}
                                                </a>
                                            @endif
                                        </td>
                                        <td><span class="badge rounded-pill" style="background: rgba(13,110,253,0.1); color: #0d6efd; font-size: 10px;">{{ $inv->invoice_number }}</span></td>
                                        <td class="small font-monospace">{{ number_format($inv->total) }}</td>
                                        <td class="small font-monospace text-success">{{ number_format($inv->amount_paid) }}</td>
                                        <td class="fw-bold small font-monospace" style="color: #b45309;">{{ number_format($rem) }}</td>
                                        <td class="text-muted small">{{ $inv->created_at->format('Y-m-d') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot style="background: rgba(255,193,7,0.06);">
                                <tr>
                                    <td colspan="4" class="px-4 fw-bold text-end">إجمالي الديون على العملاء:</td>
                                    <td colspan="2" class="fw-bold font-monospace" style="color: #b45309;">{{ number_format($totalCustomerDebts) }} {{ __('messages.currency') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/3.45.2/apexcharts.min.js"></script>
<script>
    // بيانات الأشهر للرسم البياني
    const monthNames      = @json(array_map(fn($m) => \App\Http\Controllers\ReportController::arabicMonthName($m), array_keys($months)));
    const grossProfitData = @json(array_values(array_map(fn($d) => $d['gross_profit'], $months)));
    const expensesData    = @json(array_values(array_map(fn($d) => $d['expenses'], $months)));
    const netProfitData   = @json(array_values(array_map(fn($d) => $d['net_profit'], $months)));

    // ===== شارت الربحية =====
    const profitChartEl = document.getElementById('profitabilityChart');
    if (profitChartEl) {
        new ApexCharts(profitChartEl, {
            chart: {
                type: 'bar', height: 320,
                fontFamily: 'Tajawal, sans-serif',
                toolbar: { show: false },
                animations: { enabled: true, speed: 600 },
            },
            series: [
                { name: 'الربح الإجمالي', type: 'bar',  data: grossProfitData },
                { name: 'المصروفات',      type: 'bar',  data: expensesData },
                { name: 'صافي الربح',    type: 'line', data: netProfitData },
            ],
            colors: ['#0d6efd', '#dc3545', '#198754'],
            plotOptions: { bar: { borderRadius: 6, columnWidth: '55%' } },
            stroke: { width: [0, 0, 3], curve: 'smooth' },
            markers: {
                size: [0, 0, 5], strokeWidth: 2,
                fillOpacity: 1, strokeColors: '#fff',
                colors: ['#198754'],
            },
            fill: { opacity: [0.85, 0.75, 0.1], type: ['solid','solid','gradient'] },
            xaxis: {
                categories: monthNames,
                labels: { style: { fontFamily: 'Tajawal, sans-serif', fontSize: '12px' } },
                axisBorder: { show: false }, axisTicks: { show: false },
            },
            yaxis: {
                labels: {
                    style: { fontFamily: 'Tajawal, sans-serif', fontSize: '11px' },
                    formatter: val => Number(val).toLocaleString('ar-EG'),
                },
            },
            grid: { borderColor: 'rgba(0,0,0,0.04)', strokeDashArray: 4 },
            tooltip: {
                shared: true, intersect: false,
                style: { fontFamily: 'Tajawal, sans-serif' },
                y: { formatter: val => Number(val).toLocaleString('ar-EG') + ' ج.م' },
            },
            legend: {
                position: 'top', horizontalAlign: 'left',
                fontFamily: 'Tajawal, sans-serif', fontSize: '13px',
                markers: { radius: 4 },
            },
            dataLabels: { enabled: false },
        }).render();
    }
</script>
@endpush

@endsection
