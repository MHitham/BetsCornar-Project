@extends('layouts.app')

@section('title', 'تقرير الربحية — ' . $year)
@section('page-title', 'تقرير الربحية')

{{-- كلاسات CSS مشتركة لصفحات التقارير --}}
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

{{-- رأس الصفحة مع فلتر السنة وزر العودة --}}
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
                {{-- زر العودة للتقرير السنوي --}}
                <a href="{{ route('reports.index', ['year' => $year]) }}" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">
                    <i class="bi bi-arrow-right me-1"></i>
                    ← التقرير السنوي
                </a>
                {{-- فلتر السنة --}}
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

{{-- بطاقات KPI الخمس --}}
<div class="row g-3 mb-4">

    {{-- إجمالي الإيرادات --}}
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

    {{-- تكلفة البضاعة المباعة --}}
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

    {{-- الربح الإجمالي --}}
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

    {{-- المصروفات التشغيلية --}}
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

    {{-- صافي الربح + الهامش --}}
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

{{-- شريط معادلة الربح المرئية --}}
<div class="card report-card border-0 shadow-sm mb-4 overflow-hidden">
    <div class="card-body py-3 px-4">
        <div class="d-flex align-items-center justify-content-center gap-0 flex-wrap" style="gap: 0 !important;">

            {{-- إيرادات --}}
            <div class="text-center px-3 py-2">
                <div class="small text-muted mb-1">إيرادات</div>
                <div class="fw-bold fs-6" style="color:#198754;">{{ number_format($revenue) }}</div>
            </div>

            {{-- سهم - تكلفة --}}
            <div class="text-center px-2 text-muted">
                <div class="small mb-1" style="color:#f59e0b; font-weight:600;">− تكلفة</div>
                <div class="fw-bold" style="color:#f59e0b;">{{ number_format($cogs) }}</div>
            </div>

            {{-- = --}}
            <div class="px-2 fw-bold text-muted fs-5">=</div>

            {{-- ربح إجمالي --}}
            <div class="text-center px-3 py-2 rounded-3" style="background: rgba(13,110,253,0.06);">
                <div class="small text-muted mb-1">ربح إجمالي</div>
                <div class="fw-bold fs-6 {{ $grossPositive ? 'text-primary' : 'text-danger' }}">
                    {{ number_format($grossProfit) }}
                </div>
            </div>

            {{-- سهم - مصروفات --}}
            <div class="text-center px-2">
                <div class="small mb-1" style="color:#dc3545; font-weight:600;">− مصروفات</div>
                <div class="fw-bold text-danger">{{ number_format($expenses) }}</div>
            </div>

            {{-- = --}}
            <div class="px-2 fw-bold text-muted fs-5">=</div>

            {{-- صافي ربح --}}
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

{{-- رسم بياني: ربح إجمالي مقابل مصروفات شهرياً --}}
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
        <canvas id="profitabilityChart" height="280"></canvas>
    </div>
</div>

{{-- جدول التفصيل الشهري --}}
<div class="card border-0 shadow-sm mb-4 report-card">
    <div class="card-header bg-transparent border-0 pt-3 px-4">
        <div class="d-flex align-items-center justify-content-between">
            <h6 class="fw-bold mb-0">
                <i class="bi bi-table text-secondary me-2"></i>
                التفصيل الشهري
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
                    <th class="px-4 fw-bold text-muted small text-uppercase" style="letter-spacing: 0.5px;">الشهر</th>
                    <th class="fw-bold text-muted small text-uppercase" style="letter-spacing: 0.5px;">إيرادات</th>
                    <th class="fw-bold text-muted small text-uppercase" style="letter-spacing: 0.5px;">تكلفة</th>
                    <th class="fw-bold text-muted small text-uppercase" style="letter-spacing: 0.5px;">ربح إجمالي</th>
                    <th class="fw-bold text-muted small text-uppercase" style="letter-spacing: 0.5px;">مصروفات</th>
                    <th class="fw-bold text-muted small text-uppercase" style="letter-spacing: 0.5px;">صافي ربح</th>
                    <th class="fw-bold text-muted small text-uppercase" style="letter-spacing: 0.5px;">هامش%</th>
                </tr>
            </thead>
            <tbody>
                @foreach($months as $m => $data)
                    @php $hasData = $data['revenue'] > 0 || $data['expenses'] > 0; @endphp
                    <tr class="{{ !$hasData ? 'opacity-50' : '' }}" style="transition: all 0.2s;">
                        {{-- اسم الشهر قابل للضغط إذا كان فيه بيانات --}}
                        <td class="fw-semibold px-4">
                            @if($hasData)
                                <a href="{{ route('reports.month', [$year, $m]) }}" class="text-decoration-none text-dark d-flex align-items-center gap-2">
                                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 28px; height: 28px; background: linear-gradient(135deg, #0d6efd, #6ea8fe); font-size: 11px; color: #fff; font-weight: 700;">
                                        {{ $m }}
                                    </span>
                                    {{ \App\Http\Controllers\ReportController::arabicMonthName($m) }}
                                    <i class="bi bi-chevron-left text-muted" style="font-size: 10px;"></i>
                                </a>
                            @else
                                <span class="d-flex align-items-center gap-2">
                                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 28px; height: 28px; background: #e9ecef; font-size: 11px; color: #adb5bd; font-weight: 700;">
                                        {{ $m }}
                                    </span>
                                    {{ \App\Http\Controllers\ReportController::arabicMonthName($m) }}
                                </span>
                            @endif
                        </td>
                        <td>
                            <span class="fw-semibold" style="color: #198754;">{{ number_format($data['revenue']) }}</span>
                            <small class="text-muted">{{ __('messages.currency') }}</small>
                        </td>
                        <td>
                            <span style="color: #f59e0b;">{{ number_format($data['cogs']) }}</span>
                            <small class="text-muted">{{ __('messages.currency') }}</small>
                        </td>
                        <td>
                            @php $gp = $data['gross_profit']; @endphp
                            <span class="fw-semibold {{ $gp >= 0 ? 'text-primary' : 'text-danger' }}">
                                {{ $gp >= 0 ? '+' : '' }}{{ number_format($gp) }}
                            </span>
                            <small class="text-muted">{{ __('messages.currency') }}</small>
                        </td>
                        <td>
                            <span class="text-danger">{{ number_format($data['expenses']) }}</span>
                            <small class="text-muted">{{ __('messages.currency') }}</small>
                        </td>
                        <td>
                            <span class="fw-bold {{ $data['net_profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $data['net_profit'] >= 0 ? '+' : '' }}{{ number_format($data['net_profit']) }}
                                <small class="fw-normal text-muted">{{ __('messages.currency') }}</small>
                            </span>
                        </td>
                        <td>
                            @if($hasData)
                                <span class="badge rounded-pill" style="background: {{ $data['margin'] >= 0 ? 'rgba(25,135,84,0.1)' : 'rgba(220,53,69,0.1)' }}; color: {{ $data['margin'] >= 0 ? '#198754' : '#dc3545' }}; font-size: 11px; padding: 4px 10px;">
                                    {{ $data['margin'] }}%
                                </span>
                            @else
                                <span class="text-muted" style="font-size: 10px;">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            {{-- صف الإجماليات --}}
            <tfoot>
                <tr style="background: linear-gradient(135deg, rgba(13,110,253,0.06) 0%, rgba(25,135,84,0.04) 100%);">
                    <td class="px-4 fw-bold">
                        <i class="bi bi-calculator me-1 text-primary"></i>
                        الإجمالي
                    </td>
                    <td class="fw-bold" style="color: #198754;">{{ number_format($revenue) }} {{ __('messages.currency') }}</td>
                    <td class="fw-bold" style="color: #f59e0b;">{{ number_format($cogs) }} {{ __('messages.currency') }}</td>
                    <td class="fw-bold {{ $grossProfit >= 0 ? 'text-primary' : 'text-danger' }}">
                        {{ $grossProfit >= 0 ? '+' : '' }}{{ number_format($grossProfit) }} {{ __('messages.currency') }}
                    </td>
                    <td class="fw-bold text-danger">{{ number_format($expenses) }} {{ __('messages.currency') }}</td>
                    <td class="fw-bold {{ $netProfit >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $netProfit >= 0 ? '+' : '' }}{{ number_format($netProfit) }} {{ __('messages.currency') }}
                    </td>
                    <td class="fw-bold">{{ $margin }}%</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- ديون الموردين والعملاء --}}
<div class="row g-4 mb-4">

    {{-- ديون على العيادة للموردين --}}
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

    {{-- ديون على العملاء --}}
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
{{-- Chart.js من CDN --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
    // بيانات الأشهر لاستخدامها في الرسم البياني
    Chart.defaults.font.family = "'Tajawal', 'Segoe UI', sans-serif";
    Chart.defaults.color = '#6c757d';

    const monthNames = @json(array_map(
        fn($m) => \App\Http\Controllers\ReportController::arabicMonthName($m),
        array_keys($months)
    ));
    const grossProfitData = @json(array_values(array_map(fn($d) => $d['gross_profit'], $months)));
    const expensesData    = @json(array_values(array_map(fn($d) => $d['expenses'], $months)));
    const netProfitData   = @json(array_values(array_map(fn($d) => $d['net_profit'], $months)));

    // رسم بياني: الربح الإجمالي مقابل المصروفات
    const ctx = document.getElementById('profitabilityChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: monthNames,
                datasets: [
                    {
                        label: 'الربح الإجمالي',
                        data: grossProfitData,
                        backgroundColor: 'rgba(13, 110, 253, 0.7)',
                        borderColor: 'rgba(13, 110, 253, 1)',
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
                        borderColor: 'rgba(25, 135, 84, 1)',
                        backgroundColor: 'rgba(25, 135, 84, 0.05)',
                        borderWidth: 2.5,
                        pointRadius: 5,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: 'rgba(25, 135, 84, 1)',
                        pointBorderWidth: 2.5,
                        pointHoverRadius: 7,
                        fill: true,
                        tension: 0.4,
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
                        labels: { usePointStyle: true, padding: 16, font: { size: 12, weight: '600' } }
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
                    x: { grid: { display: false }, ticks: { font: { size: 11 } } },
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
</script>
@endpush

@endsection
