@extends('layouts.app')

@section('title', __('customers.timeline.title'))
@section('page-title', __('customers.timeline.title'))

@section('content')

    {{-- تم الإضافة: بطاقة تعريف العميل مع الإجراءات السريعة --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <h4 class="mb-2">{{ $customer->name }}</h4>
                    <div class="d-flex flex-wrap gap-3 text-muted small">
                        <span>
                            <i class="bi bi-telephone me-1"></i>
                            <a href="https://wa.me/{{ $customer->phone }}" target="_blank" class="text-success text-decoration-none">
                                {{ $customer->phone }}
                            </a>
                        </span>
                        <span><i class="bi bi-heart-pulse me-1"></i>{{ $customer->animal_type }}</span>
                        <span><i class="bi bi-clock-history me-1"></i>{{ __('customers.timeline.total_visits') }}: {{ $customer->invoices->count() }}</span>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('customers.create') }}?phone={{ urlencode($customer->phone) }}&name={{ urlencode($customer->name) }}" class="btn btn-primary">
                        <i class="bi bi-clipboard2-plus me-1"></i>{{ __('customers.timeline.new_visit') }}
                    </a>
                    <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-right me-1"></i>{{ __('customers.timeline.back_to_list') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
{{-- تم الإضافة: عرض ملاحظات العميل بشكل منظم وواضح --}}
@if(filled($customer->notes))
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body bg-light rounded-3">
            <div class="d-flex align-items-start gap-3">
                <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle bg-warning bg-opacity-10"
                     style="width: 44px; height: 44px;">
                    <i class="bi bi-sticky-fill text-warning fs-5"></i>
                </div>

                <div class="flex-grow-1">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <h6 class="mb-0 fw-bold text-dark">ملاحظات العميل</h6>
                    </div>

                    <div class="text-muted lh-lg notes-box">
                        {!! nl2br(e($customer->notes)) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
    {{-- تم الإضافة: التايم لاين الكامل لزيارات العميل من الأحدث إلى الأقدم --}}
    @forelse ($customer->invoices as $invoice)
        <div @class(['card mb-4','border-secondary bg-light-subtle' => $invoice->isCancelled(),])>
            <div class="card-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="badge bg-primary">{{ $invoice->created_at->format('Y-m-d H:i') }}</span>
                    <a href="{{ route('invoices.show', $invoice) }}" class="fw-semibold text-decoration-none">
                        {{ __('customers.timeline.invoice_number') }}: {{ $invoice->invoice_number }}
                    </a>
                    @if ($invoice->isCancelled())
                        <span class="badge bg-danger">ملغية</span>
                    @else
                        <span class="badge bg-success">مؤكدة</span>
                    @endif
                    <span class="badge bg-light text-dark border">
                        {{ $invoice->source === 'customer' ? 'زيارة عميل' : 'بيع سريع' }}
                    </span>
                </div>
                <div class="fw-bold {{ $invoice->isCancelled() ? 'text-muted text-decoration-line-through' : 'text-success' }}">
                    {{ __('customers.timeline.total') }}: {{ number_format($invoice->total, 2) }} {{ __('messages.currency') }}
                </div>
            </div>
            <div class="card-body">
                {{-- تم الإضافة: جدول بنود الفاتورة ضمن السجل الطبي --}}
                <h6 class="mb-3">{{ __('customers.timeline.invoice_items') }}</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('customers.timeline.product_name') }}</th>
                                <th>{{ __('customers.timeline.type') }}</th>
                                <th>{{ __('customers.timeline.quantity') }}</th>
                                <th>{{ __('customers.timeline.unit_price') }}</th>
                                <th>{{ __('customers.timeline.line_total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->items as $item)
                                <tr>
                                    <td class="fw-semibold">{{ $item->product?->name ?? '—' }}</td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            {{ __('customers.types.' . ($item->product?->type ?? 'product')) }}
                                        </span>
                                    </td>
                                    <td class="font-monospace">{{ number_format($item->quantity, 2) }}</td>
                                    <td class="font-monospace">{{ number_format($item->unit_price, 2) }} {{ __('messages.currency') }}</td>
                                    <td class="font-monospace fw-bold">{{ number_format($item->line_total, 2) }} {{ __('messages.currency') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- تم الإضافة: جدول التطعيمات الخاصة بالزيارة الحالية عند وجودها --}}
                @if ($invoice->vaccinations->isNotEmpty())
                    <h6 class="mb-3">{{ __('customers.timeline.vaccinations') }}</h6>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('customers.timeline.vaccine_name') }}</th>
                                    <th>{{ __('customers.timeline.vaccination_date') }}</th>
                                    <th>{{ __('customers.timeline.next_dose') }}</th>
                                    <th>{{ __('customers.timeline.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoice->vaccinations as $vacc)
                                    @php
                                        $today = today();
                                        $isCompleted = $vacc->is_completed;
                                        $nextDoseDate = $vacc->next_dose_date;

                                        if ($isCompleted) {
                                            $statusClass = 'secondary';
                                            $statusLabel = __('customers.timeline.status_done');
                                        } elseif ($nextDoseDate && $nextDoseDate->lt($today)) {
                                            $statusClass = 'danger';
                                            $statusLabel = __('customers.timeline.status_overdue');
                                        } elseif ($nextDoseDate && $nextDoseDate->lte($today->copy()->addDays(7))) {
                                            $statusClass = 'warning';
                                            $statusLabel = __('customers.timeline.status_soon');
                                        } else {
                                            $statusClass = 'success';
                                            $statusLabel = __('customers.timeline.status_ok');
                                        }
                                    @endphp
                                    <tr>
                                        <td class="fw-semibold">{{ $vacc->product?->name ?? '—' }}</td>
                                        <td>{{ $vacc->vaccination_date?->format('Y-m-d') ?? '—' }}</td>
                                        <td>{{ $nextDoseDate?->format('Y-m-d') ?? '—' }}</td>
                                        <td>
                                            <span class="badge bg-{{ $statusClass }} {{ $statusClass === 'warning' ? 'text-dark' : 'text-white' }}">
                                                {{ $statusLabel }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <i class="bi bi-clock-history text-muted"></i>
                    <p>{{ __('customers.timeline.no_visits') }}</p>
                </div>
            </div>
        </div>
    @endforelse

@endsection