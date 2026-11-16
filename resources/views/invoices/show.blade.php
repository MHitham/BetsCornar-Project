@extends('layouts.app')

@section('title', 'فاتورة ' . $invoice->invoice_number)
@section('page-title', 'تفاصيل الفاتورة')

@section('content')

    <style>
        @media print {

            .sidebar,
            .navbar,
            .btn,
            .no-print {
                display: none !important;
            }

            body {
                background: white !important;
            }
        }
    </style>

    
    @if ($invoice->isCancelled())
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-x-octagon-fill fs-5"></i>
            <div>
                <strong>هذه الفاتورة ملغية</strong>
                @if ($invoice->cancellation_reason)
                    — {{ $invoice->cancellation_reason }}
                @endif
                <div class="small text-muted mt-1">
                    تاريخ الإلغاء: {{ $invoice->cancelled_at?->format('Y-m-d H:i') }}
                </div>
            </div>
        </div>
    @endif

    <div class="row g-4">

        
        <div class="col-12 col-lg-5">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-receipt text-primary me-1"></i>
                    <span class="fw-bold">بيانات الفاتورة</span>
                </div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td class="text-muted" style="width:40%">رقم الفاتورة</td>
                            <td class="fw-bold text-primary font-monospace">{{ $invoice->invoice_number }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">العميل</td>
                            <td class="fw-semibold">{{ $invoice->customer_name }}</td>
                        </tr>
                        <tr>
    <td class="text-muted">تم الإنشاء بواسطة</td>
    <td>{{ $invoice->creator->name ?? '—' }}</td>
</tr>
                        
                        <tr>
                            <td class="text-muted">الحالة</td>
                            <td>
                                @if ($invoice->isCancelled())
                                    <span class="badge bg-danger">ملغية</span>
                                @else
                                    <span class="badge bg-success">مؤكدة</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">التاريخ</td>
                            <td>{{ $invoice->created_at->format('(Y-m-d) - H:i') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">الإجمالي</td>
                            
                            <td
                                class="fw-bold fs-5 {{ $invoice->isCancelled() ? 'text-muted text-decoration-line-through' : 'text-dark' }}">
                                {{ number_format($invoice->total, 2) }} {{ __('messages.currency') }}
                            </td>
                        </tr>
                        @php 
                            // استخدام amount_paid المباشر من الفاتورة (المصدر الرسمي) بدل جمع الدفعات يدويًا،
                            // عشان يعكس أي تعديل حصل بسبب مرتجعات (اللي بتقلل amount_paid من غير ما تلمس سجل الدفعات التاريخي)
                            $totalPaid = (float) $invoice->amount_paid;
                            $remaining = $invoice->remaining_amount;
                        @endphp
                        <tr>
                            <td class="text-muted">المدفوع</td>
                            <td class="fw-bold text-success">
                                {{ number_format($totalPaid, 2) }} {{ __('messages.currency') }}
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">المتبقي</td>
                            <td class="fw-bold {{ $remaining > 0 ? 'text-danger' : 'text-success' }}">
                                {{ number_format($remaining, 2) }} {{ __('messages.currency') }}
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            <div class="mt-3 no-print d-flex gap-2 flex-wrap action-toolbar">
                <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-right me-1"></i>{{ __('messages.back') }}
                </a>

                <button type="button" class="btn btn-info ms-2 text-white" onclick="window.print()">
                    🖨️ طباعة
                </button>
                <a href="{{ route('invoices.pdf', $invoice) }}" class="btn btn-warning ms-2">
                    📄 تحميل PDF
                </a>
                
                @if ($invoice->isConfirmed())
                    <button type="button" class="btn btn-outline-danger ms-2 mb-2" data-bs-toggle="modal" data-bs-target="#cancelModal">
                        <i class="bi bi-x-circle me-1"></i>إلغاء الفاتورة
                    </button>
                    
                    <button type="button" class="btn btn-outline-warning ms-2 mb-2"
                            data-bs-toggle="modal" data-bs-target="#returnModal">
                        <i class="bi bi-arrow-return-right me-1"></i>إرجاع أصناف
                    </button>
                @endif
                <button type="button" class="btn btn-outline-primary ms-2 mb-2" data-bs-toggle="modal" data-bs-target="#paymentsModal">
                    <i class="bi bi-clock-history"></i> سجل الدفعات
                </button>
                @if ($remaining > 0 && !$invoice->isCancelled())
                    <button type="button" class="btn btn-success ms-2 mb-2" data-bs-toggle="modal" data-bs-target="#paymentsModal">
                        <i class="bi bi-cash-coin me-1"></i> تسجيل دفعة
                    </button>
                @endif
            </div>
        </div>

        
        <div class="col-12 col-lg-7">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-list-ul text-success me-1"></i>
                    <span class="fw-bold">بنود الفاتورة</span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>المنتج / الخدمة</th>
                                <th class="text-center">الكمية</th>
                                <th class="text-center">سعر الوحدة</th>
                                <th class="text-center">الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->items as $item)
                                @if($item->quantity > 0)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $item->product->name }}</div>
                                        <div class="text-muted small">
                                            {{ ['product' => 'منتج', 'service' => 'خدمة', 'vaccination' => 'تطعيم'][$item->product->type] ?? $item->product->type }}
                                        </div>
                                    </td>
                                    <td class="text-center">{{ number_format($item->quantity, 2) }}</td>
                                    <td class="text-center">{{ number_format($item->unit_price, 2) }}</td>
                                    <td class="text-center fw-bold text-success">
                                        {{ number_format($item->line_total) }} {{ __('messages.currency') }}
                                    </td>
                                </tr>
                                @endif
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-light fw-bold">
                                <td colspan="3" class="text-start">الإجمالي الكلي</td>
                                <td class="text-center text-success fs-6">
                                    {{ number_format($invoice->total) }} {{ __('messages.currency') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            
            @if ($invoice->vaccinations->isNotEmpty())
                <div class="card mt-3">
                    <div class="card-header">
                        <i class="bi bi-capsule-pill text-primary me-1"></i>
                        <span class="fw-bold">سجل التطعيمات المرتبطة</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>اللقاح</th>
                                    <th>تاريخ التطعيم</th>
                                    <th>موعد الجرعة القادمة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoice->vaccinations as $vacc)
                                    <tr>
                                        <td>{{ $vacc->product->name ?? '—' }}</td>
                                        <td>{{ $vacc->vaccination_date ? \Carbon\Carbon::parse($vacc->vaccination_date)->format('Y-m-d') : '—' }}
                                        </td>
                                        <td>{{ $vacc->next_dose_date ? \Carbon\Carbon::parse($vacc->next_dose_date)->format('Y-m-d') : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            
            @if ($invoice->returns->isNotEmpty())
                <div class="card mt-3 border-warning">
                    <div class="card-header bg-warning bg-opacity-10">
                        <i class="bi bi-arrow-return-right text-warning me-1"></i>
                        <span class="fw-bold">المرتجعات المسجلة</span>
                    </div>
                    @foreach ($invoice->returns as $ret)
                        <div class="card-body border-bottom py-2">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="text-muted">
                                    {{ $ret->created_at->format('Y-m-d H:i') }}
                                    @if($ret->reason) — {{ $ret->reason }} @endif
                                </small>
                                <span class="badge bg-warning text-dark">
                                    إجمالي المرتجع: {{ number_format($ret->total_refund, 2) }} {{ __('messages.currency') }}
                                </span>
                            </div>
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>المنتج</th>
                                        <th class="text-center">الكمية المُرجعة</th>
                                        <th class="text-center">سعر الوحدة</th>
                                        <th class="text-center">الإجمالي</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($ret->items as $ri)
                                        <tr>
                                            <td>{{ $ri->product->name ?? '—' }}</td>
                                            <td class="text-center">{{ number_format($ri->quantity_returned, 2) }}</td>
                                            <td class="text-center">{{ number_format($ri->unit_price, 2) }}</td>
                                            <td class="text-center text-warning fw-bold">
                                                {{ number_format($ri->line_total, 2) }} {{ __('messages.currency') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

    
    @if ($invoice->isConfirmed())
        <div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
                <div class="modal-content">
                    <form action="{{ route('invoices.cancel', $invoice) }}" method="POST">
                        @csrf
                        <div class="modal-header border-danger">
                            <h5 class="modal-title text-danger" id="cancelModalLabel">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                تأكيد إلغاء الفاتورة
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-3">
                                هل أنت متأكد من إلغاء الفاتورة
                                <strong class="text-primary font-monospace">{{ $invoice->invoice_number }}</strong>؟
                            </p>
                            <p class="text-muted small mb-3">
                                <i class="bi bi-info-circle me-1"></i>
                                سيتم إرجاع الكميات المخصومة إلى المخزون تلقائياً.
                            </p>
                            
                            <div class="mb-3">
                                <label for="cancellation_reason" class="form-label">
                                    سبب الإلغاء <span class="text-muted">(اختياري)</span>
                                </label>
                                <input type="text" class="form-control" id="cancellation_reason" name="cancellation_reason"
                                    placeholder="مثال: طلب العميل إرجاع المنتج">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                تراجع
                            </button>
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-x-circle me-1"></i>تأكيد الإلغاء
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    
    <div class="modal fade" id="paymentsModal" tabindex="-1" aria-labelledby="paymentsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentsModalLabel">
                        <i class="bi bi-clock-history me-2 text-primary"></i>
                        سجل دفعات الفاتورة #{{ $invoice->invoice_number }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    
                    @if($invoice->payments->isEmpty())
                        <p class="text-muted text-center py-3">لا توجد دفعات مسجلة</p>
                    @else
                        @php 
                            // نفس المنطق - استخدام amount_paid الرسمي بدل جمع الدفعات يدويًا
                            $totalPaid = (float) $invoice->amount_paid; 
                        @endphp
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
                                @php $rem = $invoice->remaining_amount; @endphp
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

                    
                    @php $remaining = $invoice->remaining_amount; @endphp
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


@if ($invoice->isConfirmed())
@php
    $alreadyReturnedQtys = $invoice->returns
        ->flatMap->items
        ->groupBy('invoice_item_id')
        ->map(fn($g) => $g->sum('quantity_returned'));
@endphp
<div class="modal fade" id="returnModal" tabindex="-1"
     aria-labelledby="returnModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <form action="{{ route('invoice-returns.store', $invoice) }}" method="POST">
                @csrf
                <div class="modal-header border-warning">
                    <h5 class="modal-title text-warning" id="returnModalLabel">
                        <i class="bi bi-arrow-return-right me-1"></i>
                        إرجاع أصناف من الفاتورة {{ $invoice->invoice_number }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @error('return')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror
                    <div class="mb-3">
                        <label class="form-label fw-semibold">سبب الإرجاع (اختياري)</label>
                        <input type="text" name="reason" class="form-control"
                               placeholder="مثال: منتج تالف، طلب العميل...">
                    </div>
                    <table class="table table-bordered align-middle text-center">
                        <thead class="table-light">
                            <tr>
                                <th>المنتج</th>
                                <th>الكمية الأصلية</th>
                                <th>تم إرجاعه</th>
                                <th>المتاح للإرجاع</th>
                                <th>الكمية المُرجعة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->items as $item)
                            @php
                                $returned   = (float)($alreadyReturnedQtys[$item->id] ?? 0);
                                $returnable = round((float)$item->quantity, 2);
                                $originalQty = round($returnable + $returned, 2);
                            @endphp
                            <tr>
                                <td class="text-start">
                                    @if($returnable > 0)
                                        <input type="hidden"
                                               name="items[{{ $loop->index }}][invoice_item_id]"
                                               value="{{ $item->id }}">
                                    @endif
                                    <div class="fw-semibold">{{ $item->product->name }}</div>
                                    <small class="text-muted">
                                        {{ number_format($item->unit_price, 2) }} {{ __('messages.currency') }}
                                    </small>
                                </td>
                                <td>{{ number_format($originalQty, 2) }}</td>
                                <td class="{{ $returned > 0 ? 'text-warning fw-bold' : 'text-muted' }}">
                                    {{ number_format($returned, 2) }}
                                </td>
                                <td class="{{ $returnable <= 0 ? 'text-muted' : 'text-success fw-bold' }}">
                                    {{ number_format($returnable, 2) }}
                                </td>
                                <td>
                                    @if($returnable > 0)
                                        <input type="number"
                                               name="items[{{ $loop->index }}][quantity_returned]"
                                               class="form-control form-control-sm text-center"
                                               min="0" max="{{ $returnable }}"
                                               step="0.01" value="0"
                                               style="width: 90px; margin: auto;">
                                    @else
                                        <span class="badge bg-secondary">تم الإرجاع</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                            data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-warning text-dark">
                        <i class="bi bi-arrow-return-right me-1"></i>تأكيد الإرجاع
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif



@endsection