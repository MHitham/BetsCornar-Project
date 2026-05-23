@extends('layouts.app')

@section('title', 'تقرير الربحية — ' . $year)

@section('content')
<div class="container-fluid py-4">

    {{-- Section 1: Year Filter --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h4 mb-0"><i class="bi bi-graph-up-arrow me-2 text-primary"></i> تقرير الربحية — {{ $year }}</h2>
        <form method="GET" action="{{ route('reports.profitability') }}" class="d-flex align-items-center">
            <label for="year" class="me-2 fw-bold text-muted">اختر السنة:</label>
            <select name="year" id="year" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                @foreach($availableYears as $y)
                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
        </form>
    </div>

    {{-- Section 2: KPI Cards --}}
    <div class="row g-3 mb-4">
        {{-- Revenue --}}
        <div class="col-md">
            <div class="card h-100 border-primary shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-cash-stack fs-1 text-primary mb-2"></i>
                    <h6 class="text-muted fw-bold">إجمالي الإيرادات</h6>
                    <h5 class="fw-bold mb-0 text-dark">{{ number_format($revenue, 2) }}</h5>
                </div>
            </div>
        </div>

        {{-- COGS --}}
        <div class="col-md">
            <div class="card h-100 border-warning shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-box-seam fs-1 text-warning mb-2"></i>
                    <h6 class="text-muted fw-bold">تكلفة البضاعة</h6>
                    <h5 class="fw-bold mb-0 text-dark">{{ number_format($cogs, 2) }}</h5>
                </div>
            </div>
        </div>

        {{-- Gross Profit --}}
        <div class="col-md">
            <div class="card h-100 border-info shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-graph-up fs-1 text-info mb-2"></i>
                    <h6 class="text-muted fw-bold">الربح الإجمالي</h6>
                    <h5 class="fw-bold mb-0 text-dark">{{ number_format($grossProfit, 2) }}</h5>
                </div>
            </div>
        </div>

        {{-- Expenses --}}
        <div class="col-md">
            <div class="card h-100 border-danger shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-receipt fs-1 text-danger mb-2"></i>
                    <h6 class="text-muted fw-bold">المصروفات</h6>
                    <h5 class="fw-bold mb-0 text-dark">{{ number_format($expenses, 2) }}</h5>
                </div>
            </div>
        </div>

        {{-- Net Profit --}}
        <div class="col-md">
            <div class="card h-100 shadow-sm {{ $netProfit < 0 ? 'border-danger' : 'border-success' }}">
                <div class="card-body text-center">
                    <i class="bi bi-trophy fs-1 mb-2 {{ $netProfit < 0 ? 'text-danger' : 'text-success' }}"></i>
                    <h6 class="text-muted fw-bold">صافي الربح — هامش {{ $margin }}%</h6>
                    <h5 class="fw-bold mb-0 {{ $netProfit < 0 ? 'text-danger' : 'text-success' }}">
                        {{ number_format($netProfit, 2) }}
                    </h5>
                </div>
            </div>
        </div>
    </div>

    {{-- Section 3: Monthly Breakdown Table --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bi bi-calendar3 me-2 text-secondary"></i> التفصيل الشهري</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle text-center mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>الشهر</th>
                            <th>إيرادات</th>
                            <th>تكلفة</th>
                            <th>ربح إجمالي</th>
                            <th>مصروفات</th>
                            <th>صافي ربح</th>
                            <th>هامش%</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($months as $m => $data)
                            @if($data['revenue'] == 0 && $data['expenses'] == 0)
                                <tr>
                                    <td class="fw-bold text-muted">{{ \App\Http\Controllers\ReportController::arabicMonthName($m) }}</td>
                                    <td class="text-muted">—</td>
                                    <td class="text-muted">—</td>
                                    <td class="text-muted">—</td>
                                    <td class="text-muted">—</td>
                                    <td class="text-muted">—</td>
                                    <td class="text-muted">—</td>
                                </tr>
                            @else
                                @php
                                    $rowClass = '';
                                    if ($data['net_profit'] < 0) $rowClass = 'table-danger';
                                    elseif ($data['net_profit'] == 0) $rowClass = 'text-muted';
                                @endphp
                                <tr class="{{ $rowClass }}">
                                    <td class="fw-bold">{{ \App\Http\Controllers\ReportController::arabicMonthName($m) }}</td>
                                    <td>{{ number_format($data['revenue'], 2) }}</td>
                                    <td>{{ number_format($data['cogs'], 2) }}</td>
                                    <td>{{ number_format($data['gross_profit'], 2) }}</td>
                                    <td>{{ number_format($data['expenses'], 2) }}</td>
                                    <td class="fw-bold {{ $data['net_profit'] > 0 ? 'text-success' : ($data['net_profit'] < 0 ? 'text-danger' : '') }}">
                                        {{ number_format($data['net_profit'], 2) }}
                                    </td>
                                    <td>{{ $data['margin'] }}%</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td>الإجمالي</td>
                            <td>{{ number_format($revenue, 2) }}</td>
                            <td>{{ number_format($cogs, 2) }}</td>
                            <td>{{ number_format($grossProfit, 2) }}</td>
                            <td>{{ number_format($expenses, 2) }}</td>
                            <td class="{{ $netProfit > 0 ? 'text-success' : ($netProfit < 0 ? 'text-danger' : '') }}">
                                {{ number_format($netProfit, 2) }}
                            </td>
                            <td>{{ $margin }}%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Section 4: Supplier Debts --}}
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                    <h5 class="mb-0 text-danger"><i class="bi bi-truck me-2"></i> ديون على العيادة (للموردين)</h5>
                </div>
                <div class="card-body">
                    @if($supplierDebts->isEmpty())
                        <div class="alert alert-success mb-0">
                            لا توجد ديون للموردين ✅
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 text-center">
                                <thead class="table-light">
                                    <tr>
                                        <th>المورد</th>
                                        <th>رقم الطلب</th>
                                        <th>إجمالي الفاتورة</th>
                                        <th>المدفوع</th>
                                        <th>المتبقي</th>
                                        <th>تاريخ الشراء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $totalSupplierDebts = 0; @endphp
                                    @foreach($supplierDebts as $po)
                                        @php
                                            $rem = $po->total_cost - $po->amount_paid;
                                            $totalSupplierDebts += $rem;
                                        @endphp
                                        <tr>
                                            <td>{{ $po->supplier->name ?? '—' }}</td>
                                            <td><span class="badge bg-secondary">{{ $po->order_number }}</span></td>
                                            <td>{{ number_format($po->total_cost, 2) }}</td>
                                            <td class="text-success">{{ number_format($po->amount_paid, 2) }}</td>
                                            <td class="fw-bold text-danger">{{ number_format($rem, 2) }}</td>
                                            <td class="text-muted small">{{ $po->purchased_at->format('Y-m-d') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-end fw-bold">إجمالي الديون للموردين:</td>
                                        <td colspan="2" class="fw-bold text-danger fs-6 text-start">{{ number_format($totalSupplierDebts, 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Section 5: Customer Debts --}}
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                    <h5 class="mb-0 text-warning text-dark"><i class="bi bi-people me-2"></i> ديون على العملاء</h5>
                </div>
                <div class="card-body">
                    @if($customerDebts->isEmpty())
                        <div class="alert alert-success mb-0">
                            لا توجد ديون على العملاء ✅
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 text-center">
                                <thead class="table-light">
                                    <tr>
                                        <th>العميل</th>
                                        <th>رقم الفاتورة</th>
                                        <th>إجمالي الفاتورة</th>
                                        <th>المدفوع</th>
                                        <th>المتبقي</th>
                                        <th>تاريخ الفاتورة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $totalCustomerDebts = 0; @endphp
                                    @foreach($customerDebts as $inv)
                                        @php
                                            $rem = $inv->total - $inv->amount_paid;
                                            $totalCustomerDebts += $rem;
                                        @endphp
                                        <tr>
                                            <td>{{ $inv->customer->name ?? $inv->customer_name }}</td>
                                            <td><span class="badge bg-secondary">{{ $inv->invoice_number }}</span></td>
                                            <td>{{ number_format($inv->total, 2) }}</td>
                                            <td class="text-success">{{ number_format($inv->amount_paid, 2) }}</td>
                                            <td class="fw-bold text-warning text-dark">{{ number_format($rem, 2) }}</td>
                                            <td class="text-muted small">{{ $inv->created_at->format('Y-m-d') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-end fw-bold">إجمالي الديون على العملاء:</td>
                                        <td colspan="2" class="fw-bold text-warning text-dark fs-6 text-start">{{ number_format($totalCustomerDebts, 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
