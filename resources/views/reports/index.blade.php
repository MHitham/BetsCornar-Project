@extends('layouts.app')

@section('title', __('reports.title'))
@section('page-title', __('reports.title'))

@section('content')

{{-- Year Filter + Header --}}
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

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    {{-- Revenue Card --}}
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

    {{-- Expenses Card --}}
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

    {{-- Net Profit Card --}}
    <div class="col-6 col-xl-3">
        @php $profitPositive = $yearlyTotals['net_profit'] >= 0; @endphp
        <div class="card h-100 border-0 shadow-sm position-relative overflow-hidden" style="border-radius: 12px;">
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

    {{-- Visits Card --}}
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

{{-- Charts Row --}}
<div class="row g-4 mb-4">
    {{-- Bar + Line Chart --}}
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
                <canvas id="revenueExpensesChart" height="280"></canvas>
            </div>
        </div>
    </div>

    {{-- Doughnut Chart --}}
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
                    <canvas id="topProductsChart" height="280"></canvas>
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

{{-- Monthly Breakdown Table --}}
<div class="card border-0 shadow-sm mb-4" style="border-radius: 12px;">
    <div class="card-header bg-transparent border-0 pt-3 px-4">
        <div class="d-flex align-items-center justify-content-between">
            <h6 class="fw-bold mb-0">
                <i class="bi bi-table text-secondary me-2"></i>
                {{ __('reports.monthly_table') }}
            </h6>
            <span class="text-muted small">
                <i class="bi bi-info-circle me-1"></i>
                اضغط على الشهر للتفاصيل
            </span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr style="background: linear-gradient(135deg, rgba(13,110,253,0.04) 0%, rgba(25,135,84,0.03) 100%);">
                    <th class="px-4 fw-bold text-muted small text-uppercase" style="letter-spacing: 0.5px;">{{ __('reports.fields.month') }}</th>
                    <th class="fw-bold text-muted small text-uppercase" style="letter-spacing: 0.5px;">{{ __('reports.fields.revenue') }}</th>
                    <th class="fw-bold text-muted small text-uppercase" style="letter-spacing: 0.5px;">{{ __('reports.fields.visit_count') }}</th>
                    <th class="fw-bold text-muted small text-uppercase" style="letter-spacing: 0.5px;">{{ __('reports.fields.expenses') }}</th>
                    <th class="fw-bold text-muted small text-uppercase" style="letter-spacing: 0.5px;">{{ __('reports.fields.net_profit') }}</th>
                    <th style="width: 100px;" class="fw-bold text-muted small text-uppercase" style="letter-spacing: 0.5px;">الأداء</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($monthlyData as $row)
                    @php
                        $hasData = $row['revenue'] > 0 || $row['expenses'] > 0;
                        $maxRevenue = $monthlyData->max('revenue') ?: 1;
                        $revenuePercent = round(($row['revenue'] / $maxRevenue) * 100);
                    @endphp
                    <tr class="{{ !$hasData ? 'opacity-50' : '' }}" style="transition: all 0.2s;">
                        <td class="fw-semibold px-4">
                            @if($hasData)
                                <a href="{{ route('reports.month', [$year, $row['month']]) }}" class="text-decoration-none text-dark d-flex align-items-center gap-2">
                                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 28px; height: 28px; background: linear-gradient(135deg, #0d6efd, #6ea8fe); font-size: 11px; color: #fff; font-weight: 700;">
                                        {{ $row['month'] }}
                                    </span>
                                    {{ $row['month_name'] }}
                                    <i class="bi bi-chevron-left text-muted" style="font-size: 10px;"></i>
                                </a>
                            @else
                                <span class="d-flex align-items-center gap-2">
                                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 28px; height: 28px; background: #e9ecef; font-size: 11px; color: #adb5bd; font-weight: 700;">
                                        {{ $row['month'] }}
                                    </span>
                                    {{ $row['month_name'] }}
                                </span>
                            @endif
                        </td>
                        <td>
                            <span class="fw-semibold" style="color: #198754;">{{ number_format($row['revenue']) }}</span>
                            <small class="text-muted">{{ __('messages.currency') }}</small>
                        </td>
                        <td>
                            <span class="badge rounded-pill" style="background: rgba(111,66,193,0.1); color: #6f42c1; font-weight: 600; padding: 5px 10px;">
                                <i class="bi bi-person-check me-1" style="font-size: 10px;"></i>{{ number_format($row['visit_count']) }}
                            </span>
                        </td>
                        <td>
                            <span class="text-danger">{{ number_format($row['expenses']) }}</span>
                            <small class="text-muted">{{ __('messages.currency') }}</small>
                        </td>
                        <td>
                            <span class="fw-bold {{ $row['net_profit'] >= 0 ? 'text-primary' : 'text-danger' }}">
                                {{ $row['net_profit'] >= 0 ? '+' : '' }}{{ number_format($row['net_profit']) }}
                                <small class="fw-normal text-muted">{{ __('messages.currency') }}</small>
                            </span>
                        </td>
                        <td>
                            @if($hasData)
                                <div class="progress" style="height: 6px; border-radius: 3px; background: rgba(0,0,0,0.06);">
                                    <div class="progress-bar" style="width: {{ $revenuePercent }}%; background: linear-gradient(90deg, #198754, #20c997); border-radius: 3px;"></div>
                                </div>
                            @else
                                <span class="text-muted" style="font-size: 10px;">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background: linear-gradient(135deg, rgba(13,110,253,0.06) 0%, rgba(25,135,84,0.04) 100%);">
                    <td class="px-4 fw-bold">
                        <i class="bi bi-calculator me-1 text-primary"></i>
                        {{ __('reports.totals_row') }}
                    </td>
                    <td class="fw-bold" style="color: #198754;">{{ number_format($yearlyTotals['revenue']) }} {{ __('messages.currency') }}</td>
                    <td class="fw-bold">{{ number_format($yearlyTotals['visit_count']) }}</td>
                    <td class="fw-bold text-danger">{{ number_format($yearlyTotals['expenses']) }} {{ __('messages.currency') }}</td>
                    <td class="fw-bold {{ $yearlyTotals['net_profit'] >= 0 ? 'text-primary' : 'text-danger' }}">
                        {{ $yearlyTotals['net_profit'] >= 0 ? '+' : '' }}{{ number_format($yearlyTotals['net_profit']) }} {{ __('messages.currency') }}
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- Top Products Table --}}
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
                                <span class="badge bg-{{ $typeColor }} bg-opacity-15 text-{{ $typeColor }} border border-{{ $typeColor }} border-opacity-25" style="font-size: 11px;">
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
{{-- Chart.js من CDN --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
    // بيانات من PHP لاستخدامها في الرسوم البيانية
    const monthLabels = @json($monthlyData->pluck('month_name'));
    const revenueData  = @json($monthlyData->pluck('revenue'));
    const expensesData = @json($monthlyData->pluck('expenses'));
    const netProfitData = @json($monthlyData->pluck('net_profit'));

    // ألوان مخصصة للشارت
    Chart.defaults.font.family = "'Tajawal', 'Segoe UI', sans-serif";
    Chart.defaults.color = '#6c757d';

    // ===== Bar Chart: إيرادات vs مصروفات =====
    const revenueCtx = document.getElementById('revenueExpensesChart');
    if (revenueCtx) {
        new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: monthLabels,
                datasets: [
                    {
                        label: 'الإيرادات',
                        data: revenueData,
                        backgroundColor: 'rgba(25, 135, 84, 0.75)',
                        borderColor: 'rgba(25, 135, 84, 1)',
                        borderWidth: 1,
                        borderRadius: 6,
                        borderSkipped: false,
                    },
                    {
                        label: 'المصروفات',
                        data: expensesData,
                        backgroundColor: 'rgba(220, 53, 69, 0.65)',
                        borderColor: 'rgba(220, 53, 69, 1)',
                        borderWidth: 1,
                        borderRadius: 6,
                        borderSkipped: false,
                    },
                    {
                        label: 'صافي الربح',
                        data: netProfitData,
                        type: 'line',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        backgroundColor: 'rgba(13, 110, 253, 0.05)',
                        borderWidth: 2.5,
                        pointRadius: 5,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: 'rgba(13, 110, 253, 1)',
                        pointBorderWidth: 2.5,
                        pointHoverRadius: 7,
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: {
                            usePointStyle: true,
                            padding: 16,
                            font: { size: 12, weight: '600' }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleFont: { size: 13, weight: 'bold' },
                        bodyFont: { size: 12 },
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: ctx => ` ${ctx.dataset.label}: ${Number(ctx.raw).toLocaleString('ar-EG')} ج.م`
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.04)' },
                        ticks: {
                            font: { size: 11 },
                            callback: val => Number(val).toLocaleString('ar-EG')
                        }
                    }
                }
            }
        });
    }

    // ===== Doughnut Chart: أعلى المنتجات =====
    const productsCtx = document.getElementById('topProductsChart');
    if (productsCtx) {
        const topProductsLabels = @json($topProducts->take(7)->pluck('name'));
        const topProductsData   = @json($topProducts->take(7)->pluck('total_sales'));
        const chartColors = [
            '#0d6efd','#198754','#6f42c1','#fd7e14',
            '#0dcaf0','#ffc107','#20c997'
        ];

        new Chart(productsCtx, {
            type: 'doughnut',
            data: {
                labels: topProductsLabels,
                datasets: [{
                    data: topProductsData,
                    backgroundColor: chartColors,
                    borderWidth: 3,
                    borderColor: '#fff',
                    hoverOffset: 12,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 14,
                            font: { size: 11, weight: '500' },
                            boxWidth: 10,
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: ctx => ` ${ctx.label}: ${Number(ctx.raw).toLocaleString('ar-EG')} ج.م`
                        }
                    }
                },
                cutout: '68%',
            }
        });
    }
</script>
@endpush

@endsection