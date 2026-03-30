@extends('layouts.app')

@section('title', 'فاتورة ' . $invoice->invoice_number)
@section('page-title', 'تفاصيل الفاتورة')

@section('content')

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
                            <td class="text-muted">المصدر</td>
                            <td>
                                @if ($invoice->source === 'customer')
                                    <span class="badge bg-primary text-white">زيارة عميل</span>
                                @else
                                    <span class="badge bg-success text-white">بيع سريع</span>
                                @endif
                            </td>
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
                            <td>{{ $invoice->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">الإجمالي</td>
                            {{-- الإجمالي يظهر مشطوباً إذا كانت الفاتورة ملغية --}}
                            <td
                                class="fw-bold fs-5 {{ $invoice->isCancelled() ? 'text-muted text-decoration-line-through' : 'text-success' }}">
                                {{ number_format($invoice->total, 2) }} {{ __('messages.currency') }}
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            <div class="mt-3">
                <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-right me-1"></i>{{ __('messages.back') }}
                </a>
                {{-- زرار الإلغاء يظهر فقط للفواتير المؤكدة --}}
                @if ($invoice->isConfirmed())
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal"
                        data-bs-target="#cancelModal">
                        <i class="bi bi-x-circle me-1"></i>إلغاء الفاتورة
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
                                    <td class="text-center">{{ number_format($item->quantity, 2) }}</td>
                                    <td class="text-center">{{ number_format($item->unit_price, 2) }}</td>
                                    <td class="text-center fw-bold text-success">
                                        {{ number_format($item->line_total, 2) }} {{ __('messages.currency') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-light fw-bold">
                                <td colspan="3" class="text-start">الإجمالي الكلي</td>
                                <td class="text-center text-success fs-6">
                                    {{ number_format($invoice->total, 2) }} {{ __('messages.currency') }}
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
                                        <td>{{ $vacc->vaccination_date }}</td>
                                        <td>{{ $vacc->next_dose_date ?? '—' }}</td>
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
                                <input type="text" class="form-control" id="cancellation_reason"
                                    name="cancellation_reason" placeholder="مثال: طلب العميل إرجاع المنتج">
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

@endsection
