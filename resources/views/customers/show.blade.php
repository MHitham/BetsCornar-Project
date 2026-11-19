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
                    {{-- Ø²Ø±Ø§Ø± ØªØ¹Ø¯ÙŠÙ„ Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ø¹Ù…ÙŠÙ„ --}}
                    <a href="{{ route('customers.edit', $customer) }}" class="btn btn-outline-primary">
                        <i class="bi bi-pencil-square me-1"></i>ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª
                    </a>
                    <a href="{{ route('customers.animals.index', $customer) }}" class="btn btn-info text-white">
                        <i class="bi bi-list-ul me-1"></i>Ø­ÙŠÙˆØ§Ù†Ø§Øª Ø§Ù„Ø¹Ù…ÙŠÙ„
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
                Ù…ØªØ¨Ù‚ÙŠ Ø¹Ù„Ù‰ Ø§Ù„Ø¹Ù…ÙŠÙ„
            </span>
            <span class="fs-5 fw-bold">{{ number_format($totalOutstanding, 2) }} Ø¬</span>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Ø±Ù‚Ù… Ø§Ù„ÙØ§ØªÙˆØ±Ø©</th>
                        <th>ØªØ§Ø±ÙŠØ® Ø§Ù„ÙØ§ØªÙˆØ±Ø©</th>
                        <th>Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„ÙØ§ØªÙˆØ±Ø©</th>
                        <th>Ø§Ù„Ù…Ø¯ÙÙˆØ¹</th>
                        <th>Ø§Ù„Ù…ØªØ¨Ù‚ÙŠ</th>
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
                        <td>{{ number_format($unpaidInv->total, 2) }} Ø¬</td>
                        <td class="text-success">{{ number_format($unpaidInv->amount_paid, 2) }} Ø¬</td>
                        <td class="text-danger fw-bold">{{ number_format($unpaidInv->remaining_amount, 2) }} Ø¬</td>
                        <td>
                            <button class="btn btn-sm btn-success"
                                    data-bs-toggle="modal"
                                    data-bs-target="#payModal{{ $unpaidInv->id }}">
                                <i class="bi bi-cash-coin me-1"></i> ØªØ³Ø¬ÙŠÙ„ Ø¯ÙØ¹Ø©
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
                    <h5 class="modal-title">ØªØ³Ø¬ÙŠÙ„ Ø¯ÙØ¹Ø© â€” {{ $unpaidInv->invoice_number }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('invoice.payments.store', $unpaidInv) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            Ø§Ù„Ù…ØªØ¨Ù‚ÙŠ Ø¹Ù„Ù‰ Ù‡Ø°Ù‡ Ø§Ù„ÙØ§ØªÙˆØ±Ø©:
                            <strong>{{ number_format($unpaidInv->remaining_amount, 1) }} Ø¬</strong>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Ø§Ù„Ù…Ø¨Ù„Øº Ø§Ù„Ù…Ø¯ÙÙˆØ¹ <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control"
                                   min="1" step="1"
                                   max="{{ $unpaidInv->remaining_amount }}">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Ø¥Ù„ØºØ§Ø¡</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-lg me-1"></i> ØªØ³Ø¬ÙŠÙ„ Ø§Ù„Ø¯ÙØ¹Ø©
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
                        <h6 class="mb-0 fw-bold text-dark">Ù…Ù„Ø§Ø­Ø¸Ø§Øª Ø§Ù„Ø¹Ù…ÙŠÙ„</h6>
                    </div>

                    <div class="text-muted lh-lg notes-box">
                        {!! nl2br(e($customer->notes)) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
    
    {{-- ملف المالك مجمّع حسب الحيوان، مع مجموعة مستقلة للسجلات القديمة بلا حيوان. --}}
    @foreach ($customer->animals as $animal)
        <div class="card mb-4" data-pet-name="{{ $animal->name }}">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-1">{{ $animal->name }}</h5>
                <div class="small">{{ $animal->species ?: '—' }} @if($animal->breed) — {{ $animal->breed }} @endif @if($animal->age) — {{ $animal->age }} @endif</div>
            </div>
            <div class="card-body">
                <h6 class="fw-bold">الفواتير</h6>
                @forelse ($animal->invoices as $invoice)
                    <div class="border rounded p-3 mb-3 {{ $invoice->isCancelled() ? 'bg-light text-muted' : '' }}">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <div>
                                <span class="badge bg-secondary">{{ $invoice->created_at->format('Y-m-d H:i') }}</span>
                                <a href="{{ route('invoices.show', $invoice) }}" class="fw-semibold ms-2">{{ $invoice->invoice_number }}</a>
                                @if($invoice->isCancelled()) <span class="badge bg-danger ms-2">ملغية</span> @else <span class="badge bg-success ms-2">مؤكدة</span> @endif
                            </div>
                            <strong>{{ number_format($invoice->total, 2) }} ج.م</strong>
                        </div>
                        <div class="mt-2"><strong>التشخيص:</strong> {{ $invoice->diagnosis ?: '—' }}</div>
                        @if(filled($invoice->treatment))<div class="mt-2"><strong>العلاج:</strong> {!! nl2br(e($invoice->treatment)) !!}</div>@endif
                        <div class="mt-2"><strong>الأصناف:</strong> {{ $invoice->items->pluck('product.name')->filter()->join('، ') ?: '—' }}</div>
                    </div>
                @empty
                    <p class="text-muted">لا توجد فواتير مرتبطة بهذا الحيوان.</p>
                @endforelse

                <h6 class="fw-bold mt-4">التطعيمات</h6>
                @forelse ($animal->vaccinations as $vaccination)
                    <div class="d-flex flex-wrap gap-3 border-bottom py-2">
                        <span class="fw-semibold">{{ $vaccination->product?->name ?? '—' }}</span>
                        <span>{{ $vaccination->vaccination_date?->format('Y-m-d') ?? '—' }}</span>
                        <span>الجرعة التالية: {{ $vaccination->next_dose_date?->format('Y-m-d') ?? '—' }}</span>
                        @if($vaccination->is_completed)<span class="badge bg-secondary">مكتمل</span>@else<span class="badge bg-warning text-dark">غير مكتمل</span>@endif
                    </div>
                @empty
                    <p class="text-muted">لا توجد تطعيمات مرتبطة بهذا الحيوان.</p>
                @endforelse
            </div>
        </div>
    @endforeach

    @if ($orphanInvoices->isNotEmpty() || $orphanVaccinations->isNotEmpty())
        <div class="card mb-4 border-warning" data-pet-name="orphan">
            <div class="card-header bg-warning"><h5 class="mb-0">سجلات بدون حيوان</h5></div>
            <div class="card-body">
                @foreach($orphanInvoices as $invoice)
                    <div class="border rounded p-3 mb-3">
                        <a href="{{ route('invoices.show', $invoice) }}" class="fw-semibold">{{ $invoice->invoice_number }}</a>
                        <span class="text-muted ms-2">{{ $invoice->created_at->format('Y-m-d H:i') }}</span>
                        <div class="mt-2"><strong>التشخيص:</strong> {{ $invoice->diagnosis ?: '—' }}</div>
                        @if(filled($invoice->treatment))<div class="mt-2"><strong>العلاج:</strong> {!! nl2br(e($invoice->treatment)) !!}</div>@endif
                        <div><strong>الأصناف:</strong> {{ $invoice->items->pluck('product.name')->filter()->join('، ') ?: '—' }}</div>
                    </div>
                @endforeach
                @foreach($orphanVaccinations as $vaccination)
                    <div class="border-bottom py-2">{{ $vaccination->product?->name ?? '—' }} — {{ $vaccination->vaccination_date?->format('Y-m-d') ?? '—' }}</div>
                @endforeach
            </div>
        </div>
    @endif

    
    @foreach($customer->invoices as $invoice)
    <div class="modal fade" id="paymentsModal{{ $invoice->id }}" tabindex="-1" aria-labelledby="paymentsModalLabel{{ $invoice->id }}" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentsModalLabel{{ $invoice->id }}">
                        <i class="bi bi-clock-history me-2 text-primary"></i>
                        Ø³Ø¬Ù„ Ø¯ÙØ¹Ø§Øª Ø§Ù„ÙØ§ØªÙˆØ±Ø© #{{ $invoice->invoice_number }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    
                    @if($invoice->payments->isEmpty())
                        <p class="text-muted text-center py-3">Ù„Ø§ ØªÙˆØ¬Ø¯ Ø¯ÙØ¹Ø§Øª Ù…Ø³Ø¬Ù„Ø©</p>
                    @else
                        @php $totalPaid = $invoice->payments->sum('amount'); @endphp
                        <table class="table table-sm align-middle mb-3">
                            <thead class="table-light">
                                <tr>
                                    <th>Ø§Ù„ØªØ§Ø±ÙŠØ®</th>
                                    <th>Ø§Ù„Ù…Ø¨Ù„Øº</th>
                                    <th>Ù…Ù„Ø§Ø­Ø¸Ø§Øª</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoice->payments as $payment)
                                    <tr>
                                        <td class="font-monospace small">{{ $payment->paid_at->format('Y-m-d H:i') }}</td>
                                        <td class="fw-semibold text-success">{{ number_format($payment->amount, 2) }} Ø¬.Ù…</td>
                                        <td class="text-muted">{{ $payment->notes ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td>Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„Ù…Ø¯ÙÙˆØ¹</td>
                                    <td class="text-success">{{ number_format($totalPaid, 2) }} Ø¬.Ù…</td>
                                    <td>-</td>
                                </tr>
                                @php $rem = $invoice->total - $totalPaid; @endphp
                                <tr>
                                    <td>Ø§Ù„Ù…ØªØ¨Ù‚ÙŠ</td>
                                    <td class="{{ $rem > 0 ? 'text-danger' : 'text-success' }}">
                                        {{ number_format($rem, 2) }} Ø¬.Ù…
                                    </td>
                                    <td>-</td>
                                </tr>
                            </tfoot>
                        </table>
                    @endif

                    
                    @php $remaining = $invoice->total - $invoice->payments->sum('amount'); @endphp
                    @if($remaining > 0 && $invoice->status !== 'cancelled')
                        <hr>
                        <h6 class="fw-bold mb-3"><i class="bi bi-plus-circle me-1 text-success"></i>ØªØ³Ø¬ÙŠÙ„ Ø¯ÙØ¹Ø© Ø¬Ø¯ÙŠØ¯Ø©</h6>
                        {{-- Ø­Ù…Ø§ÙŠØ© ØªØ³Ø¬ÙŠÙ„ Ø¯ÙØ¹Ø© Ø§Ù„Ø¹Ù…ÙŠÙ„ Ù…Ù† Ø§Ù„Ø¥Ø±Ø³Ø§Ù„ Ø§Ù„Ù…ÙƒØ±Ø±. --}}
                        <form action="{{ route('invoice.payments.store', $invoice) }}" method="POST" data-prevent-double>
                            @csrf
                            <x-idempotency-token />
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Ø§Ù„Ù…Ø¨Ù„Øº Ø§Ù„Ù…Ø¯ÙÙˆØ¹ <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" name="amount" class="form-control"
                                               min="0.01" step="0.01" max="{{ $remaining }}"
                                               placeholder="Ø§Ù„Ù…Ø¨Ù„Øº Ø§Ù„Ù…Ø¯ÙÙˆØ¹" required>
                                        <span class="input-group-text">Ø¬.Ù…</span>
                                    </div>
                                    <div class="form-text text-muted">Ø§Ù„Ø­Ø¯ Ø§Ù„Ø£Ù‚ØµÙ‰: {{ number_format($remaining, 2) }} Ø¬.Ù…</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Ù…Ù„Ø§Ø­Ø¸Ø§Øª</label>
                                    <input type="text" name="notes" class="form-control" placeholder="Ù…Ù„Ø§Ø­Ø¸Ø§Øª (Ø§Ø®ØªÙŠØ§Ø±ÙŠ)">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">ØªØ§Ø±ÙŠØ® Ø§Ù„Ø¯ÙØ¹</label>
                                    <input type="datetime-local" name="paid_at" class="form-control"
                                           value="{{ now()->format('Y-m-d\TH:i') }}">
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-lg me-1"></i>ØªØ³Ø¬ÙŠÙ„ Ø¯ÙØ¹Ø©
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
