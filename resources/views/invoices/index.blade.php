@extends('layouts.app')

@section('title', __('invoices.title'))
@section('page-title', __('invoices.title'))

@section('content')

        @if (!$hasFilters)
            <script>
                window.location.replace("{{ route('invoices.index', ['period' => 'today']) }}");
            </script>
        @endif

        <div class="card mb-4 shadow-sm border-0">
            <div class="card-body pb-0">
                @if (!empty($showRevenueBar) && !empty($revenueSummary))
                    @include('invoices.partials.revenue-modal')
                    @php
                        $revenuePeriodTitles = [
                            'day' => 'إيرادات اليوم',
                            'month' => 'إيرادات الشهر',
                            'all' => 'إجمالي الإيرادات',
                        ];
                        $revenueBarTitle = $revenuePeriodTitles[$revenueSummary['period_type'] ?? 'day'] ?? 'الإيرادات';
                    @endphp
                    <div class="revenue-bar-card rounded-3 p-3 mb-3">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div>
                                <div class="text-muted small mb-1">{{ $revenueBarTitle }}</div>
                                <div class="d-flex flex-wrap align-items-baseline gap-2">
                                    <span class="fs-3 fw-bold text-success">
                                        {{ number_format($revenueSummary['gross_revenue'], 2) }}
                                    </span>
                                    <span class="text-muted">{{ __('messages.currency') }}</span>
                                    <span class="badge bg-light text-dark border">{{ $revenueSummary['label'] }}</span>
                                </div>
                                <div class="text-muted small mt-1">
                                    {{ $revenueSummary['invoice_count'] }} فاتورة مؤكدة
                                </div>
                            </div>
                            <button type="button" class="btn btn-success btn-lg px-4" data-bs-toggle="modal"
                                data-bs-target="#revenueModal">
                                <i class="bi bi-cash-stack me-2"></i>الإيرادات
                            </button>
                        </div>
                    </div>
                @endif

                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
                    <ul class="nav nav-tabs border-0 gap-2 m-0 flex-wrap">
                        <li class="nav-item">
                            <a class="nav-link {{ !$date && $period === 'today' ? 'active fw-bold border-bottom flex-column bg-light' : 'text-muted' }}"
                                href="{{ request()->fullUrlWithQuery(['period' => 'today', 'page' => null, 'date' => null]) }}"
                                @if (!$date && $period === 'today') style="border-bottom-width: 3px !important; border-bottom-color: #0d6efd !important;" @endif>
                                اليوم
                                <span
                                    class="badge {{ !$date && $period === 'today' ? 'bg-primary' : 'bg-secondary' }} ms-1 rounded-pill">{{ $countToday }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ !$date && $period === 'month' ? 'active fw-bold border-bottom bg-light' : 'text-muted' }}"
                                href="{{ request()->fullUrlWithQuery(['period' => 'month', 'page' => null, 'date' => null]) }}"
                                @if (!$date && $period === 'month') style="border-bottom-width: 3px !important; border-bottom-color: #0d6efd !important;" @endif>
                                هذا الشهر
                                <span
                                    class="badge {{ !$date && $period === 'month' ? 'bg-primary' : 'bg-secondary' }} ms-1 rounded-pill">{{ $countMonth }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ !$date && $period === 'all' ? 'active fw-bold border-bottom bg-light' : 'text-muted' }}"
                                href="{{ request()->fullUrlWithQuery(['period' => 'all', 'page' => null, 'date' => null]) }}"
                                @if (!$date && $period === 'all') style="border-bottom-width: 3px !important; border-bottom-color: #0d6efd !important;" @endif>
                                الكل
                                <span
                                    class="badge {{ !$date && $period === 'all' ? 'bg-primary' : 'bg-secondary' }} ms-1 rounded-pill">{{ $countAll }}</span>
                            </a>
                        </li>
                    </ul>

                    <form method="GET" action="{{ route('invoices.index') }}"
                        class="d-flex flex-wrap align-items-center gap-2 m-0 w-100 w-md-auto" id="dateFilterForm">
                        @if (request('q'))
                            <input type="hidden" name="q" value="{{ request('q') }}">
                        @endif
                        @if (request('source'))
                            <input type="hidden" name="source" value="{{ request('source') }}">
                        @endif
                        @if (request('status'))
                            <input type="hidden" name="status" value="{{ request('status') }}">
                        @endif

                        <label class="form-label text-muted m-0 text-nowrap">تاريخ محدد:</label>
                        <input type="date" name="date" class="form-control flex-grow-1 flex-md-grow-0"
                            style="max-width: 180px;" value="{{ $date }}"
                            onchange="document.getElementById('dateFilterForm').submit()">

                        @if ($date)
                            <a href="{{ request()->fullUrlWithQuery(['date' => null, 'period' => 'today']) }}"
                                class="btn btn-outline-danger text-nowrap" title="مسح التاريخ">
                                <i class="bi bi-x-circle"></i> مسح
                            </a>
                        @endif
                    </form>
                </div>

                <form method="GET" action="{{ route('invoices.index') }}" class="row g-3 align-items-end mb-3">
                    <input type="hidden" name="period" value="{{ $period }}">
                    <div class="col-12 col-md-5">
                        <label class="form-label text-muted">بحث</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" name="q" class="form-control" value="{{ $q }}"
                                placeholder="{{ __('invoices.filters.search_placeholder') }}">
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label text-muted">{{ __('invoices.fields.source') }}</label>
                        <select name="source" class="form-select" onchange="this.form.submit()">
                            <option value="">{{ __('invoices.sources.all') }}</option>
                            <option value="customer" {{ $source === 'customer' ? 'selected' : '' }}>
                                {{ __('invoices.sources.customer') }}</option>
                            <option value="quick_sale" {{ $source === 'quick_sale' ? 'selected' : '' }}>
                                {{ __('invoices.sources.quick_sale') }}</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            بحث
                        </button>
                        <a href="{{ route('invoices.index', ['period' => $period]) }}" class="btn btn-outline-secondary"
                            title="إعادة ضبط">
                            <i class="bi bi-arrow-clockwise"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>


    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <span class="fw-bold fs-5">
            <i class="bi bi-receipt text-primary me-2"></i>
            @if ($isEmployee ?? false)
                فواتير اليوم
            @else
                {{ $invoices->total() }} فاتورة
            @endif
        </span>
        <a href="{{ route('invoices.create') }}" class="btn btn-success fw-bold shadow-sm">
            <i class="bi bi-lightning-fill me-1"></i>{{ __('invoices.actions.add') }}
        </a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('invoices.fields.invoice_number') }}</th>
                        <th>{{ __('invoices.fields.customer_name') }}</th>
                        <th>{{ __('invoices.fields.source') }}</th>
                        <th>{{ __('invoices.fields.total') }}</th>
                        <th>{{ __('invoices.fields.date') }}</th>
                        <th class="text-center">{{ __('messages.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($invoices->isNotEmpty())
                        @foreach ($invoices as $invoice)
                            <tr class="{{ $invoice->isCancelled() ? 'table-secondary text-muted' : '' }}">
                                <td>
                                    <span
                                        class="fw-bold font-monospace {{ $invoice->isCancelled() ? 'text-muted text-decoration-line-through' : 'text-primary' }}">
                                        {{ $invoice->invoice_number }}
                                    </span>
                                    @if ($invoice->isCancelled())
                                        <span class="badge bg-danger me-1">ملغية</span>
                                    @endif
                                </td>
                                <td>{{ $invoice->customer_name }}</td>
                                <td>
                                    @if ($invoice->source === 'customer')
                                        <span class="badge bg-primary text-white">
                                            <i class="bi bi-person-fill me-1"></i>{{ __('invoices.sources.customer') }}
                                        </span>
                                    @else
                                        <span class="badge bg-success text-white">
                                            <i
                                                class="bi bi-lightning-fill me-1"></i>{{ __('invoices.sources.quick_sale') }}
                                        </span>
                                    @endif
                                </td>
                                <td
                                    class="fw-bold fs-6 font-monospace {{ $invoice->isCancelled() ? 'text-muted text-decoration-line-through' : 'text-success' }}">
                                    {{ number_format($invoice->total) }} {{ __('messages.currency') }}
                                </td>
                                <td class="text-muted small">{{ $invoice->created_at->format('Y-m-d H:i') }}</td>
                                <td class="text-center">
                                    <a href="{{ route('invoices.show', $invoice) }}"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye me-1"></i>عرض
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="6">
                                <div class="text-center py-5 text-muted">
                                    <i class="bi bi-receipt fs-1 d-block mb-3"></i>
                                    <p>{{ __('invoices.messages.no_invoices') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $invoices->links() }}
    </div>

@endsection
