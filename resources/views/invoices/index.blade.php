@extends('layouts.app')

@section('title', __('invoices.title'))
@section('page-title', __('invoices.title'))

@section('content')
    @role('admin')
        @if (!$hasFilters)
            <script>
                // Redirect seamlessly to apply default period filter on first load
                window.location.replace("{{ route('invoices.index', ['period' => 'today']) }}");
            </script>
        @endif

        <div class="card mb-4 shadow-sm border-0">
            <div class="card-body pb-0">
                {{-- Filter Tabs & Date Picker --}}
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
                    <ul class="nav nav-tabs border-0 gap-2 m-0">
                        <li class="nav-item">
                            <a class="nav-link {{ !$date && $period === 'today' ? 'active fw-bold border-bottom flex-column bg-light' : 'text-muted' }}"
                                href="{{ request()->fullUrlWithQuery(['period' => 'today', 'page' => null, 'date' => null]) }}"
                                @if (!$date && $period === 'today') style="border-bottom-width: 3px !important; border-bottom-color: #0d6efd !important;" @endif>
                                اليوم
                                <span class="badge {{ !$date && $period === 'today' ? 'bg-primary' : 'bg-secondary' }} ms-1 rounded-pill">{{ $countToday }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ !$date && $period === 'month' ? 'active fw-bold border-bottom bg-light' : 'text-muted' }}"
                                href="{{ request()->fullUrlWithQuery(['period' => 'month', 'page' => null, 'date' => null]) }}"
                                @if (!$date && $period === 'month') style="border-bottom-width: 3px !important; border-bottom-color: #0d6efd !important;" @endif>
                                هذا الشهر
                                <span class="badge {{ !$date && $period === 'month' ? 'bg-primary' : 'bg-secondary' }} ms-1 rounded-pill">{{ $countMonth }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ !$date && $period === 'all' ? 'active fw-bold border-bottom bg-light' : 'text-muted' }}"
                                href="{{ request()->fullUrlWithQuery(['period' => 'all', 'page' => null, 'date' => null]) }}"
                                @if (!$date && $period === 'all') style="border-bottom-width: 3px !important; border-bottom-color: #0d6efd !important;" @endif>
                                الكل
                                <span class="badge {{ !$date && $period === 'all' ? 'bg-primary' : 'bg-secondary' }} ms-1 rounded-pill">{{ $countAll }}</span>
                            </a>
                        </li>
                    </ul>

                    <form method="GET" action="{{ route('invoices.index') }}" class="d-flex align-items-center gap-2 m-0" id="dateFilterForm">
                        @if(request('q')) <input type="hidden" name="q" value="{{ request('q') }}"> @endif
                        @if(request('source')) <input type="hidden" name="source" value="{{ request('source') }}"> @endif
                        @if(request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
                        
                        <label class="form-label text-muted m-0 text-nowrap">تاريخ محدد:</label>
                        <input type="date" name="date" class="form-control" style="width: 150px;" value="{{ $date }}" onchange="document.getElementById('dateFilterForm').submit()">
                        
                        @if($date)
                            <a href="{{ request()->fullUrlWithQuery(['date' => null, 'period' => 'today']) }}" class="btn btn-outline-danger text-nowrap" title="مسح التاريخ">
                                <i class="bi bi-x-circle"></i> مسح
                            </a>
                        @endif
                    </form>
                </div>

                {{-- Search / Filter --}}
                <form method="GET" action="{{ route('invoices.index') }}" class="row g-3 align-items-end mb-3">
                    <input type="hidden" name="period" value="{{ $period }}">
                    <div class="col-md-5">
                        <label class="form-label text-muted">بحث</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" name="q" class="form-control" value="{{ $q }}"
                                placeholder="{{ __('invoices.filters.search_placeholder') }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-muted">{{ __('invoices.fields.source') }}</label>
                        <select name="source" class="form-select" onchange="this.form.submit()">
                            <option value="">{{ __('invoices.sources.all') }}</option>
                            <option value="customer" {{ $source === 'customer' ? 'selected' : '' }}>
                                {{ __('invoices.sources.customer') }}</option>
                            <option value="quick_sale" {{ $source === 'quick_sale' ? 'selected' : '' }}>
                                {{ __('invoices.sources.quick_sale') }}</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
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
    @endrole

    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <span class="fw-bold fs-5">
            <i class="bi bi-receipt text-primary me-2"></i>
            @if(auth()->user()->hasRole('employee'))
                فواتير اليوم
            @else
                {{ $invoices->total() }} فاتورة
            @endif
        </span>
        <a href="{{ route('invoices.create') }}" class="btn btn-success fw-bold shadow-sm">
            <i class="bi bi-lightning-fill me-1"></i>{{ __('invoices.actions.add') }}
        </a>
    </div>

    {{-- Table --}}
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
                            {{-- الصف يتلون بالرمادي الفاتح لو الفاتورة ملغية --}}
                            <tr class="{{ $invoice->isCancelled() ? 'table-secondary text-muted' : '' }}">

                                <td>
                                    {{-- رقم الفاتورة — عليه خط لو ملغية --}}
                                    <span
                                        class="fw-bold font-monospace {{ $invoice->isCancelled() ? 'text-muted text-decoration-line-through' : 'text-primary' }}">
                                        {{ $invoice->invoice_number }}
                                    </span>
                                    {{-- badge ملغية تظهر جنب الرقم --}}
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

                                {{-- الإجمالي — مشطوب ورمادي لو الفاتورة ملغية --}}
                                <td
                                    class="fw-bold {{ $invoice->isCancelled() ? 'text-muted text-decoration-line-through' : 'text-success' }}">
                                    {{ number_format($invoice->total, 2) }} {{ __('messages.currency') }}
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

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $invoices->links() }}
    </div>

@endsection
