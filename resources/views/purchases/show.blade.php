@extends('layouts.app')
@section('title', 'تفاصيل فاتورة الشراء')
@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('purchases.index') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="bi bi-arrow-right"></i>
        </a>
        <h4 class="mb-0 fw-bold">{{ $purchase->order_number }}</h4>
        @php $status = $purchase->payment_status; @endphp
        @if($status === 'paid')
            <span class="badge bg-success ms-3 fs-6">مسدد</span>
        @elseif($status === 'partial')
            <span class="badge bg-warning text-dark ms-3 fs-6">جزئي</span>
        @else
            <span class="badge bg-danger ms-3 fs-6">غير مسدد</span>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">

        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold bg-light">معلومات الفاتورة</div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted">المورد</td>
                            <td class="fw-semibold">{{ $purchase->supplier?->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">تاريخ الشراء</td>
                            <td>{{ $purchase->purchased_at->format('Y/m/d') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">إجمالي التكلفة</td>
                            <td class="fw-bold">{{ number_format($purchase->total_cost, 2) }} ج</td>
                        </tr>
                        <tr>
                            <td class="text-muted">المدفوع</td>
                            <td class="text-success fw-semibold">{{ number_format($purchase->amount_paid, 2) }} ج</td>
                        </tr>
                        <tr>
                            <td class="text-muted">المتبقي</td>
                            <td class="text-danger fw-bold">{{ number_format($purchase->remaining_amount, 2) }} ج</td>
                        </tr>
                        @if($purchase->notes)
                        <tr>
                            <td class="text-muted">ملاحظات</td>
                            <td>{{ $purchase->notes }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center bg-light">
                    <span class="fw-semibold">سجل الدفعات</span>
                    @if($purchase->payment_status !== 'paid')
                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#payModal">
                            <i class="bi bi-cash-coin me-1"></i> تسجيل دفعة
                        </button>
                    @endif
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>التاريخ</th>
                                <th>المبلغ</th>
                                <th>من الدرج؟</th>
                                <th>ملاحظات</th>
                                <th>بواسطة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($purchase->payments as $payment)
                                <tr>
                                    <td>{{ $payment->paid_at->format('Y/m/d') }}</td>
                                    <td class="fw-semibold text-success">{{ number_format($payment->amount, 2) }} ج</td>
                                    <td>
                                        @if($payment->is_from_clinic_cash)
                                            <span class="badge bg-warning text-dark">من الدرج</span>
                                        @else
                                            <span class="badge bg-secondary">خارجي</span>
                                        @endif
                                    </td>
                                    <td>{{ $payment->notes ?? '—' }}</td>
                                    <td>{{ $payment->creator?->name ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">لا توجد دفعات مسجلة</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <div class="card shadow-sm mt-2">
        <div class="card-header fw-semibold bg-light">بنود الشراء</div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>المنتج</th>
                        <th>النوع</th>
                        <th>الكمية</th>
                        <th>سعر الشراء</th>
                        <th>سعر البيع</th>
                        <th>تاريخ الانتهاء</th>
                        <th>الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchase->items as $item)
                        <tr>
                            <td class="fw-semibold">{{ $item->product?->name ?? '—' }}</td>
                            <td>
                                @if($item->product?->type === 'vaccination')
                                    <span class="badge bg-info text-dark">تطعيم</span>
                                @elseif($item->product?->type === 'service')
                                    <span class="badge bg-secondary">خدمة</span>
                                @else
                                    <span class="badge bg-primary">منتج</span>
                                @endif
                            </td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ number_format($item->purchase_price_per_unit, 2) }} ج</td>
                            <td>{{ number_format($item->selling_price_per_unit, 2) }} ج</td>
                            <td>{{ $item->expiry_date?->format('Y/m/d') ?? '—' }}</td>
                            <td class="fw-bold text-primary">{{ number_format($item->line_total, 2) }} ج</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="6" class="text-end fw-bold">الإجمالي</td>
                        <td class="fw-bold text-primary">{{ number_format($purchase->total_cost, 2) }} ج</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

</div>

<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تسجيل دفعة — {{ $purchase->order_number }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('purchases.pay', $purchase) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        المتبقي: <strong>{{ number_format($purchase->remaining_amount, 2) }} ج</strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">المبلغ المدفوع <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control"
                               min="0.01" step="0.01"
                               max="{{ $purchase->remaining_amount }}"
                               value="{{ $purchase->remaining_amount }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">من درج العيادة؟</label>
                        <div class="d-flex align-items-center gap-3">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox"
                                       role="switch"
                                       id="isFromClinicCash"
                                       name="is_from_clinic_cash"
                                       value="1"
                                       checked>
                                <label class="form-check-label" for="isFromClinicCash" id="clinicCashLabel">نعم — يُخصم من إيراد اليوم</label>
                            </div>
                        </div>
                        <div class="form-text text-muted">إذا كانت الدفعة من درج العيادة، سيتم خصمها من صافي إيراد يوم الدفع.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ملاحظات</label>
                        <input type="text" name="notes" class="form-control" placeholder="اختياري">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-cash-coin me-1"></i> تسجيل الدفعة
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const toggle = document.getElementById('isFromClinicCash');
    const label  = document.getElementById('clinicCashLabel');
    if (!toggle || !label) return;

    function updateLabel() {
        label.textContent = toggle.checked
            ? 'نعم — يُخصم من إيراد اليوم'
            : 'لا — دفعة خارجية';
    }

    toggle.addEventListener('change', updateLabel);
    updateLabel();
})();
</script>
@endpush

