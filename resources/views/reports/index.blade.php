@extends('layouts.app')

@section('title', __('reports.title'))
@section('page-title', __('reports.title'))


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
    /* تمييز أفضل شهر في الجدول */
    .best-month-row {
        background: linear-gradient(90deg, rgba(255,193,7,0.07) 0%, transparent 100%) !important;
        border-right: 3px solid #ffc107;
    }
    .best-month-row td:first-child::after {
        content: " 🏆";
        font-size: 12px;
    }
</style>
@endpush

@section('content')


<div class="card mb-4 border-0 shadow-sm" style="background: linear-gradient(135deg, rgba(13,110,253,0.06) 0%, rgba(25,135,84,0.04) 100%);">
    <div class="card-body py-3 px-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h5 class="fw-bold mb-1" style="color: #2d3748;">
                    <i class="bi bi-clipboard2-data-fill me-2" style="color: #0d6efd;"></i>
                    التقرير السنوي — {{ $year }}
                </h5>
                <p class="text-muted small mb-0">نظرة شاملة على أداء العيادة خلال العام</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                
                <a href="{{ route('reports.profitability', ['year' => $year]) }}" class="btn btn-outline-primary btn-sm" style="border-radius: 8px;">
                    <i class="bi bi-graph-up-arrow me-1"></i>
                    تقرير الربحية ←
                </a>
                <form method="GET" action="{{ route('reports.index') }}" class="d-flex align-items-center gap-2">
                    <label for="year" class="form-label mb-0 fw-semibold text-muted small text-nowrap">
                        <i class="bi bi-calendar3 me-1"></i>السنة
                    </label>
                    <select name="year" id="year" class="form-select form-select-sm" style="width: 110px; border-radius: 8px;" onchange="this.form.submit()">
                        @foreach (range(2026, now()->year) as $selectYear)
                            <option value="{{ $selectYear }}" @selected($year === $selectYear)>{{ $selectYear }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>
    </div>
</div>


<div class="row g-3 mb-4">
    
    <div class="col-6 col-xl-3">
        <div class="card h-100 border-0 shadow-sm position-relative overflow-hidden" style="border-radius: 12px;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: linear-gradient(135deg, #198754, #20c997);">
                        <i class="bi bi-cash-coin text-white" style="font-size: 16px;"></i>
                    </div>
                    <span class="text-muted small fw-semibold">{{ __('reports.summary.total_revenue') }}</span>
                </div>
                <div class="fw-bold fs-4" style="color: #198754;">
                    {{ number_format($yearlyTotals['revenue']) }}
                    <small class="fs-6 fw-normal text-muted">{{ __('messages.currency') }}</small>
                </div>
            </div>
            <div class="position-absolute bottom-0 end-0 opacity-25" style="font-size: 64px; margin-left: -10px; margin-bottom: -12px; color: #198754;">
                <i class="bi bi-cash-stack"></i>
            </div>
        </div>
    </div>

    
    <div class="col-6 col-xl-3">
        <div class="card h-100 border-0 shadow-sm position-relative overflow-hidden" style="border-radius: 12px;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: linear-gradient(135deg, #dc3545, #e35d6a);">
                        <i class="bi bi-receipt text-white" style="font-size: 16px;"></i>
                    </div>
                    <span class="text-muted small fw-semibold">{{ __('reports.summary.total_expenses') }}</span>
                </div>
                <div class="fw-bold fs-4 text-danger">
                    {{ number_format($yearlyTotals['expenses']) }}
                    <small class="fs-6 fw-normal text-muted">{{ __('messages.currency') }}</small>
                </div>
            </div>
            <div class="position-absolute bottom-0 end-0 opacity-25" style="font-size: 64px; margin-left: -10px; margin-bottom: -12px; color: #dc3545;">
                <i class="bi bi-receipt-cutoff"></i>
            </div>
        </div>
    </div>

    
    <div class="col-6 col-xl-3">
        <div class="card h-100 border-0 shadow-sm position-relative overflow-hidden report-card">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: linear-gradient(135deg, {{ $profitPositive ? '#0d6efd, #6ea8fe' : '#ffc107, #ffcd39' }});">
                        <i class="bi bi-graph-up-arrow text-white" style="font-size: 16px;"></i>
                    </div>
                    <span class="text-muted small fw-semibold">{{ __('reports.summary.total_profit') }}</span>
                </div>
                <div class="fw-bold fs-4 {{ $profitPositive ? 'text-primary' : 'text-danger' }}">
                    {{ $profitPositive ? '+' : '' }}{{ number_format($yearlyTotals['net_profit']) }}
                    <small class="fs-6 fw-normal text-muted">{{ __('messages.currency') }}</small>
                </div>
            </div>
            <div class="position-absolute bottom-0 end-0 opacity-25" style="font-size: 64px; margin-left: -10px; margin-bottom: -12px; color: {{ $profitPositive ? '#0d6efd' : '#ffc107' }};">
                <i class="bi bi-{{ $profitPositive ? 'graph-up-arrow' : 'graph-down-arrow' }}"></i>
            </div>
        </div>
    </div>

    
    <div class="col-6 col-xl-3">
        <div class="card h-100 border-0 shadow-sm position-relative overflow-hidden" style="border-radius: 12px;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: linear-gradient(135deg, #6f42c1, #a855f7);">
                        <i class="bi bi-person-check text-white" style="font-size: 16px;"></i>
                    </div>
                    <span class="text-muted small fw-semibold">{{ __('reports.summary.total_visits') }}</span>
                </div>
                <div class="fw-bold fs-4" style="color: #6f42c1;">
                    {{ number_format($yearlyTotals['visit_count']) }}
                    <small class="fs-6 fw-normal text-muted">زيارة</small>
                </div>
            </div>
            <div class="position-absolute bottom-0 end-0 opacity-25" style="font-size: 64px; margin-left: -10px; margin-bottom: -12px; color: #6f42c1;">
                <i class="bi bi-people-fill"></i>
            </div>
        </div>
    </div>
</div>


<div class="row g-4 mb-4">
    
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
            <div class="card-header bg-transparent border-0 pb-0 pt-3 px-4">
                <div class="d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0">
                        <i class="bi bi-bar-chart-fill text-primary me-2"></i>
                        الإيرادات مقابل المصروفات الشهرية
                    </h6>
                    <span class="badge rounded-pill text-muted" style="background: rgba(0,0,0,0.05); font-size: 11px;">{{ $year }}</span>
                </div>
            </div>
            <div class="card-body px-4 pb-4">
                <div id="revenueExpensesChart"></div>
            </div>
        </div>
    </div>

    
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
            <div class="card-header bg-transparent border-0 pb-0 pt-3 px-4">
                <h6 class="fw-bold mb-0">
                    <i class="bi bi-pie-chart-fill text-success me-2"></i>
                    أعلى المنتجات مبيعاً
                </h6>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center px-4 pb-4">
                @if($topProducts->isNotEmpty())
                    <div id="topProductsChart"></div>
                @else
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-pie-chart fs-1 d-block mb-2 opacity-50"></i>
                        لا توجد بيانات
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>


<div class="card border-0 shadow-sm mb-4" style="border-radius: 12px;">
    <div class="card-header bg-transparent border-0 pt-3 px-4">
        <div class="d-flex align-items-center justify-content-between">
            <h6 class="fw-bold mb-0">
                <i class="bi bi-table text-secondary me-2"></i>
                التفصيل الشهري الموحد
            </h6>
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted small">
                    <i class="bi bi-info-circle me-1"></i>
                    اضغط على الشهر للتفاصيل
                </span>
                <a href="{{ route('reports.profitability', ['year' => $year]) }}"
                   class="btn btn-outline-primary btn-sm" style="border-radius: 8px;">
                    <i class="bi bi-graph-up-arrow me-1"></i>
                    تقرير الربحية والديون
                </a>
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-sm">
            <thead>
                <tr style="background: linear-gradient(135deg, rgba(13,110,253,0.04) 0%, rgba(25,135,84,0.03) 100%);">
                    <th class="px-3 fw-bold text-muted small">الشهر</th>
                    <th class="fw-bold text-muted small">الإيرادات</th>
                    <th class="fw-bold text-muted small">الزيارات</th>
                    <th class="fw-bold text-muted small">التكلفة</th>
                    <th class="fw-bold text-muted small">الربح الإجمالي</th>
                    <th class="fw-bold text-muted small">المصروفات</th>
                    <th class="fw-bold text-muted small">صافي الربح</th>
                    <th class="fw-bold text-muted small">هامش%</th>
                    <th class="fw-bold text-muted small">عملاء جدد</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($monthlyData as $row)
                    <tr class="{{ !$row['has_data'] ? 'opacity-50' : '' }} {{ $row['month'] === $bestMonth ? 'best-month-row' : '' }}"
                        style="transition: all 0.2s;">

                        
                        <td class="fw-semibold px-3">
                            @if($row['has_data'])
                                <a href="{{ route('reports.month', [$year, $row['month']]) }}"
                                   class="text-decoration-none text-dark d-flex align-items-center gap-2">
                                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle"
                                          style="width:26px;height:26px;background:linear-gradient(135deg,#0d6efd,#6ea8fe);font-size:10px;color:#fff;font-weight:700;">
                                        {{ $row['month'] }}
                                    </span>
                                    {{ $row['month_name'] }}
                                    <i class="bi bi-chevron-left text-muted" style="font-size:10px;"></i>
                                </a>
                            @else
                                <span class="d-flex align-items-center gap-2">
                                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle"
                                          style="width:26px;height:26px;background:#e9ecef;font-size:10px;color:#adb5bd;font-weight:700;">
                                        {{ $row['month'] }}
                                    </span>
                                    {{ $row['month_name'] }}
                                </span>
                            @endif
                        </td>

                        
                        <td>
                            <span class="fw-semibold small" style="color:#198754;">
                                {{ number_format($row['revenue']) }}
                            </span>
                            <small class="text-muted">{{ __('messages.currency') }}</small>
                        </td>

                        
                        <td>
                            <span class="badge rounded-pill small"
                                  style="background:rgba(111,66,193,0.1);color:#6f42c1;font-weight:600;padding:4px 8px;">
                                <i class="bi bi-person-check me-1" style="font-size:9px;"></i>
                                {{ $row['visit_count'] }}
                            </span>
                        </td>

                        
                        <td>
                            <span class="small" style="color:#f59e0b;">
                                {{ number_format($row['cogs']) }}
                            </span>
                            <small class="text-muted">{{ __('messages.currency') }}</small>
                        </td>

                        
                        <td>
                            <span class="fw-semibold small {{ $row['gross_profit'] >= 0 ? 'text-primary' : 'text-danger' }}">
                                {{ $row['gross_profit'] >= 0 ? '+' : '' }}{{ number_format($row['gross_profit']) }}
                            </span>
                            <small class="text-muted">{{ __('messages.currency') }}</small>
                        </td>

                        
                        <td>
                            <span class="small text-danger">{{ number_format($row['expenses']) }}</span>
                            <small class="text-muted">{{ __('messages.currency') }}</small>
                        </td>

                        
                        <td>
                            <span class="fw-bold small {{ $row['net_profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $row['net_profit'] >= 0 ? '+' : '' }}{{ number_format($row['net_profit']) }}
                            </span>
                            <small class="text-muted">{{ __('messages.currency') }}</small>
                        </td>

                        
                        <td>
                            @if($row['has_data'])
                                <span class="badge rounded-pill"
                                      style="background:{{ $row['margin'] >= 0 ? 'rgba(25,135,84,0.1)' : 'rgba(220,53,69,0.1)' }};
                                             color:{{ $row['margin'] >= 0 ? '#198754' : '#dc3545' }};
                                             font-size:11px;padding:4px 8px;">
                                    {{ $row['margin'] }}%
                                </span>
                            @else
                                <span class="text-muted" style="font-size:10px;">—</span>
                            @endif
                        </td>

                        
                        <td>
                            @if($row['new_customers'] > 0)
                                <span class="badge rounded-pill"
                                      style="background:rgba(13,202,240,0.1);color:#0891b2;font-weight:600;padding:4px 8px;">
                                    <i class="bi bi-person-plus" style="font-size:9px;"></i>
                                    {{ $row['new_customers'] }}
                                </span>
                            @else
                                <span class="text-muted" style="font-size:10px;">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background:linear-gradient(135deg,rgba(13,110,253,0.06) 0%,rgba(25,135,84,0.04) 100%);">
                    <td class="px-3 fw-bold small">
                        <i class="bi bi-calculator me-1 text-primary"></i>
                        الإجمالي
                    </td>
                    <td class="fw-bold small" style="color:#198754;">
                        {{ number_format($yearlyTotals['revenue']) }}
                        <small class="text-muted fw-normal">{{ __('messages.currency') }}</small>
                    </td>
                    <td class="fw-bold small">{{ number_format($yearlyTotals['visit_count']) }}</td>
                    <td class="fw-bold small" style="color:#f59e0b;">
                        {{ number_format($monthlyData->sum('cogs')) }}
                        <small class="text-muted fw-normal">{{ __('messages.currency') }}</small>
                    </td>
                    <td class="fw-bold small text-primary">
                        {{ number_format($monthlyData->sum('gross_profit')) }}
                        <small class="text-muted fw-normal">{{ __('messages.currency') }}</small>
                    </td>
                    <td class="fw-bold small text-danger">
                        {{ number_format($yearlyTotals['expenses']) }}
                        <small class="text-muted fw-normal">{{ __('messages.currency') }}</small>
                    </td>
                    <td class="fw-bold small {{ $yearlyTotals['net_profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ number_format($yearlyTotals['net_profit']) }}
                        <small class="text-muted fw-normal">{{ __('messages.currency') }}</small>
                    </td>
                    <td class="fw-bold small">—</td>
                    <td class="fw-bold small">
                        {{ number_format($monthlyData->sum('new_customers')) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>


<div class="card border-0 shadow-sm" style="border-radius: 12px;">
    <div class="card-header bg-transparent border-0 pt-3 px-4">
        <div class="d-flex align-items-center justify-content-between">
            <h6 class="fw-bold mb-0">
                <i class="bi bi-trophy-fill text-warning me-2"></i>
                {{ __('reports.top_products') }}
            </h6>
            @if($topProducts->isNotEmpty())
                <span class="badge rounded-pill" style="background: rgba(255,193,7,0.12); color: #b45309; font-size: 11px;">
                    أعلى {{ $topProducts->count() }} منتج
                </span>
            @endif
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr style="background: linear-gradient(135deg, rgba(255,193,7,0.05) 0%, rgba(253,126,20,0.03) 100%);">
                    <th class="px-4 fw-bold text-muted small" style="width:50px">#</th>
                    <th class="fw-bold text-muted small">{{ __('reports.fields.product') }}</th>
                    <th class="fw-bold text-muted small">{{ __('reports.fields.type') }}</th>
                    <th class="fw-bold text-muted small">{{ __('reports.fields.quantity') }}</th>
                    <th class="fw-bold text-muted small">{{ __('reports.fields.total_sales') }}</th>
                    <th class="fw-bold text-muted small" style="width:130px">النسبة</th>
                </tr>
            </thead>
            <tbody>
                @php $maxSales = $topProducts->max('total_sales') ?: 1; @endphp
                @if ($topProducts->isNotEmpty())
                    @foreach ($topProducts as $product)
                        @php
                            $typeMap = [
                                'product'     => ['منتج',   'primary'],
                                'service'     => ['خدمة',   'success'],
                                'vaccination' => ['تطعيم',  'info'],
                            ];
                            [$typeLabel, $typeColor] = $typeMap[$product->type] ?? [$product->type, 'secondary'];
                            $percent = round(($product->total_sales / $maxSales) * 100);
                            $rankColors = ['#ffd700', '#c0c0c0', '#cd7f32'];
                        @endphp
                        <tr>
                            <td class="px-4">
                                @if($loop->iteration <= 3)
                                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle fw-bold" style="width: 26px; height: 26px; background: {{ $rankColors[$loop->index] }}20; color: {{ $rankColors[$loop->index] }}; font-size: 12px; border: 1.5px solid {{ $rankColors[$loop->index] }}40;">
                                        {{ $loop->iteration }}
                                    </span>
                                @else
                                    <span class="text-muted">{{ $loop->iteration }}</span>
                                @endif
                            </td>
                            <td class="fw-semibold">{{ $product->name }}</td>
                            <td>
                                <span class="badge bg-{{ $typeColor }} bg-opacity-10 text-{{ $typeColor }} border border-{{ $typeColor }} border-opacity-25" style="font-size: 11px;">
                                    {{ $typeLabel }}
                                </span>
                            </td>
                            <td class="font-monospace">{{ number_format((float) $product->total_quantity) }}</td>
                            <td class="font-monospace fw-bold" style="color: #198754;">{{ number_format((float) $product->total_sales) }} {{ __('messages.currency') }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 6px; border-radius: 3px; background: rgba(0,0,0,0.06);">
                                        <div class="progress-bar bg-{{ $typeColor }}" style="width: {{ $percent }}%; border-radius: 3px; transition: width 0.8s ease;"></div>
                                    </div>
                                    <span class="text-muted" style="font-size: 10px; min-width: 28px;">{{ $percent }}%</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-bar-chart-line fs-1 d-block mb-2 opacity-50"></i>
                            {{ __('messages.no_data') }}
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/3.45.2/apexcharts.min.js"></script>
<script>
    // بيانات الشهور للرسوم البيانية
    const monthLabels   = @json($monthlyData->pluck('month_name'));
    const revenueData   = @json($monthlyData->pluck('revenue'));
    const expensesData  = @json($monthlyData->pluck('expenses'));
    const netProfitData = @json($monthlyData->pluck('net_profit'));

    // ===== شارت الإيرادات مقابل المصروفات =====
    const revenueChartEl = document.getElementById('revenueExpensesChart');
    if (revenueChartEl) {
        new ApexCharts(revenueChartEl, {
            chart: {
                type: 'bar', height: 320,
                fontFamily: 'Tajawal, sans-serif',
                toolbar: { show: false },
                animations: { enabled: true, speed: 600 },
            },
            series: [
                { name: 'الإيرادات',   type: 'bar',  data: revenueData },
                { name: 'المصروفات',   type: 'bar',  data: expensesData },
                { name: 'صافي الربح', type: 'line', data: netProfitData },
            ],
            colors: ['#198754', '#dc3545', '#0d6efd'],
            plotOptions: { bar: { borderRadius: 6, columnWidth: '55%' } },
            stroke: { width: [0, 0, 3], curve: 'smooth' },
            markers: {
                size: [0, 0, 5], strokeWidth: 2,
                fillOpacity: 1, strokeColors: '#fff',
                colors: ['#0d6efd'],
            },
            fill: { opacity: [0.85, 0.75, 0.1], type: ['solid','solid','gradient'] },
            xaxis: {
                categories: monthLabels,
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

    // ===== شارت أعلى المنتجات (Donut) =====
    const productsChartEl = document.getElementById('topProductsChart');
    if (productsChartEl) {
        const topProductsLabels = @json($topProducts->take(7)->pluck('name'));
        const topProductsData   = @json($topProducts->take(7)->pluck('total_sales')->map(fn($v) => (float)$v));

        if (topProductsData.length > 0) {
            new ApexCharts(productsChartEl, {
                chart: {
                    type: 'donut', height: 300,
                    fontFamily: 'Tajawal, sans-serif',
                    toolbar: { show: false },
                    animations: { speed: 600 },
                },
                series: topProductsData,
                labels: topProductsLabels,
                colors: ['#0d6efd','#198754','#6f42c1','#fd7e14','#0dcaf0','#ffc107','#20c997'],
                plotOptions: {
                    pie: {
                        donut: {
                            size: '68%',
                            labels: {
                                show: true,
                                total: {
                                    show: true,
                                    label: 'الإجمالي',
                                    fontFamily: 'Tajawal, sans-serif',
                                    formatter: w => {
                                        const t = w.globals.seriesTotals.reduce((a,b) => a+b, 0);
                                        return Number(t).toLocaleString('ar-EG') + ' ج.م';
                                    },
                                },
                            },
                        },
                    },
                },
                dataLabels: { enabled: false },
                legend: {
                    position: 'bottom',
                    fontFamily: 'Tajawal, sans-serif', fontSize: '11px',
                    markers: { radius: 4 },
                },
                tooltip: {
                    style: { fontFamily: 'Tajawal, sans-serif' },
                    y: { formatter: val => Number(val).toLocaleString('ar-EG') + ' ج.م' },
                },
            }).render();
        }
    }
</script>
@endpush

@endsection