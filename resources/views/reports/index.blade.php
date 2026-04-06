@extends('layouts.app')

@section('title', __('reports.title'))
@section('page-title', __('reports.title'))

@section('content')

    {{-- تم الإضافة: فلتر السنة للتقارير الشهرية --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="year" class="form-label">{{ __('reports.year_filter') }}</label>
                    <select name="year" id="year" class="form-select" onchange="this.form.submit()">
                        @foreach (range(2026, now()->year) as $selectYear)
                            <option value="{{ $selectYear }}" @selected($year === $selectYear)>{{ $selectYear }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
    </div>

    {{-- تم الإضافة: بطاقات الملخص السنوي --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small mb-2">{{ __('reports.summary.total_revenue') }}</div>
                    <div class="fw-bold fs-4">{{ number_format($yearlyTotals['revenue'], 2) }} {{ __('messages.currency') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small mb-2">{{ __('reports.summary.total_expenses') }}</div>
                    <div class="fw-bold fs-4">{{ number_format($yearlyTotals['expenses'], 2) }} {{ __('messages.currency') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small mb-2">{{ __('reports.summary.total_profit') }}</div>
                    <div class="fw-bold fs-4 {{ $yearlyTotals['net_profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ number_format($yearlyTotals['net_profit'], 2) }} {{ __('messages.currency') }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small mb-2">{{ __('reports.summary.total_visits') }}</div>
                    <div class="fw-bold fs-4">{{ number_format($yearlyTotals['visit_count']) }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- تم الإضافة: جدول التفاصيل الشهرية --}}
    <div class="card mb-4">
        <div class="card-header bg-transparent border-0 pb-0">
            <h5 class="mb-0">{{ __('reports.monthly_table') }}</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('reports.fields.month') }}</th>
                        <th>{{ __('reports.fields.revenue') }}</th>
                        <th>{{ __('reports.fields.visit_count') }}</th>
                        <th>{{ __('reports.fields.expenses') }}</th>
                        <th>{{ __('reports.fields.net_profit') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($monthlyData as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row['month_name'] }}</td>
                            <td class="font-monospace">{{ number_format($row['revenue'], 2) }} {{ __('messages.currency') }}</td>
                            <td>{{ number_format($row['visit_count']) }}</td>
                            <td class="font-monospace">{{ number_format($row['expenses'], 2) }} {{ __('messages.currency') }}</td>
                            <td class="font-monospace fw-bold {{ $row['net_profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($row['net_profit'], 2) }} {{ __('messages.currency') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="table-light fw-bold">
                        <td>{{ __('reports.totals_row') }}</td>
                        <td>{{ number_format($yearlyTotals['revenue'], 2) }} {{ __('messages.currency') }}</td>
                        <td>{{ number_format($yearlyTotals['visit_count']) }}</td>
                        <td>{{ number_format($yearlyTotals['expenses'], 2) }} {{ __('messages.currency') }}</td>
                        <td class="{{ $yearlyTotals['net_profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ number_format($yearlyTotals['net_profit'], 2) }} {{ __('messages.currency') }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- تم الإضافة: جدول أعلى المنتجات والخدمات مبيعًا --}}
    <div class="card">
        <div class="card-header bg-transparent border-0 pb-0">
            <h5 class="mb-0">{{ __('reports.top_products') }}</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('reports.fields.product') }}</th>
                        <th>{{ __('reports.fields.type') }}</th>
                        <th>{{ __('reports.fields.quantity') }}</th>
                        <th>{{ __('reports.fields.total_sales') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($topProducts as $product)
                        @php
                            $typeMap = [
                                'product' => ['منتج', 'primary'],
                                'service' => ['خدمة', 'success'],
                                'vaccination' => ['تطعيم', 'info'],
                            ];
                            [$typeLabel, $typeColor] = $typeMap[$product->type] ?? [$product->type, 'secondary'];
                        @endphp
                        <tr>
                            <td class="text-muted">{{ $loop->iteration }}</td>
                            <td class="fw-semibold">{{ $product->name }}</td>
                            <td><span class="badge bg-{{ $typeColor }} text-white">{{ $typeLabel }}</span></td>
                            <td class="font-monospace">{{ number_format((float) $product->total_quantity, 2) }}</td>
                            <td class="font-monospace fw-bold">{{ number_format((float) $product->total_sales, 2) }} {{ __('messages.currency') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="bi bi-bar-chart-line text-muted"></i>
                                    <p>{{ __('messages.no_data') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection