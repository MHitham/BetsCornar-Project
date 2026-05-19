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

    {{-- تنبيه يظهر فقط إذا كانت الفاتورة ملغية --}}
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

        {{-- Invoice Header --}}
        <div class="col-md-5">
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
                        {{-- حالة الفاتورة: مؤكدة أو ملغية --}}
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
                            {{-- الإجمالي يظهر مشطوباً إذا كانت الفاتورة ملغية --}}
                            <td
                                class="fw-bold fs-5 {{ $invoice->isCancelled() ? 'text-muted text-decoration-line-through' : 'text-dark' }}">
                                {{ number_format($invoice->total, 2) }} {{ __('messages.currency') }}
                            </td>
                        </tr>
                        @php 
                            $totalPaid = $invoice->payments->sum('amount'); 
                            $remaining = max(0, $invoice->total - $totalPaid);
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
            <div class="mt-3 no-print d-flex gap-2 flex-wrap">
                <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-right me-1"></i>{{ __('messages.back') }}
                </a>
                <button type="button" class="btn btn-info ms-2 text-white" onclick="window.print()">
                    🖨️ طباعة
                </button>
                <a href="{{ route('invoices.pdf', $invoice) }}" class="btn btn-warning ms-2">
                    📄 تحميل PDF
                </a>
                {{-- زرار الإلغاء يظهر فقط للفواتير المؤكدة --}}
                @if ($invoice->isConfirmed())
                    <button type="button" class="btn btn-outline-danger ms-2 mb-2" data-bs-toggle="modal" data-bs-target="#cancelModal">
                        <i class="bi bi-x-circle me-1"></i>إلغاء الفاتورة
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

        {{-- Invoice Items --}}
        <div class="col-md-7">
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
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $item->product->name }}</div>
                                        <div class="text-muted small">
                                            {{ ['product' => 'منتج', 'service' => 'خدمة', 'vaccination' => 'تطعيم'][$item->product->type] ?? $item->product->type }}
                                        </div>
                                    </td>
                                    <td class="text-center">{{ number_format($item->quantity) }}</td>
                                    <td class="text-center">{{ number_format($item->unit_price, 1) }}</td>
                                    <td class="text-center fw-bold text-success">
                                        {{ number_format($item->line_total) }} {{ __('messages.currency') }}
                                    </td>
                                </tr>
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

            {{-- Vaccinations if any --}}
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
        </div>

    </div>

    {{-- modal تأكيد الإلغاء — يظهر فقط للفواتير المؤكدة --}}
    @if ($invoice->isConfirmed())
        <div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
            <div class="modal-dialog">
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
                            {{-- حقل سبب الإلغاء اختياري --}}
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

    {{-- modal سجل الدفعات --}}
    <div class="modal fade" id="paymentsModal" tabindex="-1" aria-labelledby="paymentsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentsModalLabel">
                        <i class="bi bi-clock-history me-2 text-primary"></i>
                        سجل دفعات الفاتورة #{{ $invoice->invoice_number }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    {{-- قسم 1: جدول الدفعات السابقة --}}
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

                    {{-- قسم 2: إضافة دفعة جديدة --}}
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

@endsection