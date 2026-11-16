@extends('layouts.app')

@section('title', __('customers.timeline.title'))
@section('page-title', __('customers.timeline.title'))

@section('content')

    
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
                    {{-- زرار تعديل بيانات العميل --}}
                    <a href="{{ route('customers.edit', $customer) }}" class="btn btn-outline-primary">
                        <i class="bi bi-pencil-square me-1"></i>تعديل البيانات
                    </a>
                    <a href="{{ route('customers.animals.index', $customer) }}" class="btn btn-info text-white">
                        <i class="bi bi-list-ul me-1"></i>حيوانات العميل
                    </a>
                    <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-right me-1"></i>{{ __('customers.timeline.back_to_list') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    
    @php
        $unpaidInvoices = $customer->invoices->filter(fn($inv) =>
            !$inv->isCancelled() && (float)$inv->remaining_amount > 0
        );
        $totalOutstanding = $unpaidInvoices->sum('remaining_amount');
    @endphp

    @if($totalOutstanding > 0)
    <div class="card mb-4 border-danger">
        <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
            <span class="fw-bold">
                <i class="bi bi-exclamation-circle me-2"></i>
                متبقي على العميل
            </span>
            <span class="fs-5 fw-bold">{{ number_format($totalOutstanding, 2) }} ج</span>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>رقم الفاتورة</th>
                        <th>تاريخ الفاتورة</th>
                        <th>إجمالي الفاتورة</th>
                        <th>المدفوع</th>
                        <th>المتبقي</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($unpaidInvoices as $unpaidInv)
                    <tr>
                        <td>
                            <a href="{{ route('invoices.show', $unpaidInv) }}" class="fw-semibold text-decoration-none">
                                {{ $unpaidInv->invoice_number }}
                            </a>
                        </td>
                        <td>{{ $unpaidInv->created_at->format('Y/m/d') }}</td>
                        <td>{{ number_format($unpaidInv->total, 2) }} ج</td>
                        <td class="text-success">{{ number_format($unpaidInv->amount_paid, 2) }} ج</td>
                        <td class="text-danger fw-bold">{{ number_format($unpaidInv->remaining_amount, 2) }} ج</td>
                        <td>
                            <button class="btn btn-sm btn-success"
                                    data-bs-toggle="modal"
                                    data-bs-target="#payModal{{ $unpaidInv->id }}">
                                <i class="bi bi-cash-coin me-1"></i> تسجيل دفعة
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    
    @foreach($unpaidInvoices as $unpaidInv)
    <div class="modal fade" id="payModal{{ $unpaidInv->id }}" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تسجيل دفعة — {{ $unpaidInv->invoice_number }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('invoice.payments.store', $unpaidInv) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            المتبقي على هذه الفاتورة:
                            <strong>{{ number_format($unpaidInv->remaining_amount, 1) }} ج</strong>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">المبلغ المدفوع <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control"
                                   min="1" step="1"
                                   max="{{ $unpaidInv->remaining_amount }}">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-lg me-1"></i> تسجيل الدفعة
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endforeach
    @endif


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
    
    @forelse ($customer->invoices as $invoice)
        @php
            $paid = $invoice->amount_paid ?? 0;
            $remaining = $invoice->total - $paid;
        @endphp
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
                    @if ($paid >= $invoice->total)
                        <span class="badge bg-success">مدفوعة</span>
                    @elseif ($paid > 0 && $paid < $invoice->total)
                        <span class="badge bg-warning text-dark">جزئي</span>
                    @else
                        <span class="badge bg-danger">غير مدفوعة</span>
                    @endif
                    <button class="btn btn-xs btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#paymentsModal{{ $invoice->id }}">
                        <i class="bi bi-cash"></i>
                    </button>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div class="fw-bold {{ $invoice->isCancelled() ? 'text-muted text-decoration-line-through' : 'text-success' }}">
                        {{ __('customers.timeline.total') }}: {{ number_format($invoice->total) }} {{ __('messages.currency') }}
                    </div>
                    <div class="text-muted small border-start ps-3">
                        <span class="d-block">المدفوع: {{ number_format($invoice->amount_paid ?? 0, 2) }} ج.م</span>
                        <span class="d-block">المتبقي: {{ number_format(($invoice->total - ($invoice->amount_paid ?? 0)), 2) }} ج.م</span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                
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
                                    <td class="font-monospace">{{ number_format($item->quantity,2) }}</td>
                                    <td class="font-monospace">{{ number_format($item->unit_price,2) }} {{ __('messages.currency') }}</td>
                                    <td class="font-monospace fw-bold">{{ number_format($item->line_total,2) }} {{ __('messages.currency') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                
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

    
    @foreach($customer->invoices as $invoice)
    <div class="modal fade" id="paymentsModal{{ $invoice->id }}" tabindex="-1" aria-labelledby="paymentsModalLabel{{ $invoice->id }}" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentsModalLabel{{ $invoice->id }}">
                        <i class="bi bi-clock-history me-2 text-primary"></i>
                        سجل دفعات الفاتورة #{{ $invoice->invoice_number }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    
                    @if($invoice->payments->isEmpty())
                        <p class="text-muted text-center py-3">لا توجد دفعات مسجلة</p>
                    @else
                        @php $totalPaid = $invoice->payments->sum('amount'); @endphp
                        <table class="table table-sm align-middle mb-3">
                            <thead class="table-light">
                                <tr>
                                    <th>التاريخ</th>
                                    <th>المبلغ</th>
                                    <th>ملاحظات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoice->payments as $payment)
                                    <tr>
                                        <td class="font-monospace small">{{ $payment->paid_at->format('Y-m-d H:i') }}</td>
                                        <td class="fw-semibold text-success">{{ number_format($payment->amount, 2) }} ج.م</td>
                                        <td class="text-muted">{{ $payment->notes ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td>إجمالي المدفوع</td>
                                    <td class="text-success">{{ number_format($totalPaid, 2) }} ج.م</td>
                                    <td>-</td>
                                </tr>
                                @php $rem = $invoice->total - $totalPaid; @endphp
                                <tr>
                                    <td>المتبقي</td>
                                    <td class="{{ $rem > 0 ? 'text-danger' : 'text-success' }}">
                                        {{ number_format($rem, 2) }} ج.م
                                    </td>
                                    <td>-</td>
                                </tr>
                            </tfoot>
                        </table>
                    @endif

                    
                    @php $remaining = $invoice->total - $invoice->payments->sum('amount'); @endphp
                    @if($remaining > 0 && $invoice->status !== 'cancelled')
                        <hr>
                        <h6 class="fw-bold mb-3"><i class="bi bi-plus-circle me-1 text-success"></i>تسجيل دفعة جديدة</h6>
                        <form action="{{ route('invoice.payments.store', $invoice) }}" method="POST">
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">المبلغ المدفوع <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" name="amount" class="form-control"
                                               min="0.01" step="0.01" max="{{ $remaining }}"
                                               placeholder="المبلغ المدفوع" required>
                                        <span class="input-group-text">ج.م</span>
                                    </div>
                                    <div class="form-text text-muted">الحد الأقصى: {{ number_format($remaining, 2) }} ج.م</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">ملاحظات</label>
                                    <input type="text" name="notes" class="form-control" placeholder="ملاحظات (اختياري)">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">تاريخ الدفع</label>
                                    <input type="datetime-local" name="paid_at" class="form-control"
                                           value="{{ now()->format('Y-m-d\TH:i') }}">
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-lg me-1"></i>تسجيل دفعة
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endforeach

@endsection