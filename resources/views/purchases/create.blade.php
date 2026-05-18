@extends('layouts.app')
@section('title', 'فاتورة شراء جديدة')
@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('purchases.index') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="bi bi-arrow-right"></i>
        </a>
        <h4 class="mb-0 fw-bold">فاتورة شراء جديدة</h4>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('purchases.store') }}" method="POST" id="purchaseForm">
        @csrf

        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold bg-light">بيانات الفاتورة</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">المورد <span class="text-muted">(اختياري)</span></label>
                        <select name="supplier_id" class="form-select">
                            <option value="">— بدون مورد —</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                    {{ $supplier->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">تاريخ الشراء <span class="text-danger">*</span></label>
                        <input type="date" name="purchased_at"
                               class="form-control @error('purchased_at') is-invalid @enderror"
                               value="{{ old('purchased_at', date('Y-m-d')) }}" required>
                        @error('purchased_at')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">ملاحظات</label>
                        <input type="text" name="notes" class="form-control"
                               value="{{ old('notes') }}" placeholder="ملاحظات اختيارية">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center bg-light">
                <span class="fw-semibold">بنود الشراء</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addItemBtn">
                    <i class="bi bi-plus-lg me-1"></i> إضافة منتج
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0 align-middle" id="itemsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width:200px">المنتج</th>
                                <th style="min-width:100px">الكمية</th>
                                <th style="min-width:130px">سعر الشراء</th>
                                <th style="min-width:130px">سعر البيع</th>
                                <th style="min-width:130px">تاريخ الانتهاء</th>
                                <th style="min-width:140px">تحديث سعر البيع؟</th>
                                <th style="min-width:100px">الإجمالي</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <td colspan="6" class="text-end fw-bold">إجمالي التكلفة:</td>
                                <td class="fw-bold text-primary" id="grandTotal">0.00 ج</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold bg-light">الدفع</div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">إجمالي التكلفة</label>
                        <input type="text" class="form-control" id="totalCostDisplay" readonly placeholder="0.00 ج">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">المدفوع الآن</label>
                        <input type="number" name="amount_paid" id="amountPaid"
                               class="form-control" min="0" step="0.01"
                               value="{{ old('amount_paid', 0) }}"
                               placeholder="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">المتبقي</label>
                        <input type="text" class="form-control text-danger fw-bold"
                               id="remainingDisplay" readonly placeholder="0.00 ج">
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-check-lg me-1"></i> حفظ فاتورة الشراء
            </button>
            <a href="{{ route('purchases.index') }}" class="btn btn-outline-secondary btn-lg">إلغاء</a>
        </div>

    </form>
</div>

<script>
const PRODUCTS = @json($products->keyBy('id'));

let rowIndex = 0;

function createRow(index) {
    const productOptions = Object.values(PRODUCTS).map(p =>
        `<option value="${p.id}" data-type="${p.type}" data-price="${p.price}">${p.name}</option>`
    ).join('');

    return `
    <tr id="row_${index}">
        <td>
            <select name="items[${index}][product_id]" class="form-select form-select-sm product-select" required>
                <option value="">اختر المنتج</option>
                ${productOptions}
            </select>
        </td>
        <td>
            <input type="number" name="items[${index}][quantity]"
                   class="form-control form-control-sm qty-input"
                   min="0.01" step="0.01" value="1" required>
        </td>
        <td>
            <input type="number" name="items[${index}][purchase_price_per_unit]"
                   class="form-control form-control-sm purchase-price"
                   min="0" step="0.01" value="0" required>
        </td>
        <td>
            <input type="number" name="items[${index}][selling_price_per_unit]"
                   class="form-control form-control-sm selling-price"
                   min="0" step="0.01" value="0" required>
        </td>
        <td>
            <input type="date" name="items[${index}][expiry_date]"
                   class="form-control form-control-sm expiry-date" style="display:none">
            <span class="text-muted small expiry-na">—</span>
        </td>
        <td class="text-center">
            <div class="form-check form-switch d-flex justify-content-center">
                <input class="form-check-input update-price-check" type="checkbox"
                       name="update_selling_price[${index}]" value="1">
            </div>
        </td>
        <td class="fw-semibold line-total text-primary">0.00 ج</td>
        <td>
            <button type="button" class="btn btn-sm btn-outline-danger remove-row">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    </tr>`;
}

function updateTotals() {
    let grand = 0;
    document.querySelectorAll('#itemsBody tr').forEach(row => {
        const qty   = parseFloat(row.querySelector('.qty-input')?.value) || 0;
        const price = parseFloat(row.querySelector('.purchase-price')?.value) || 0;
        const line  = qty * price;
        const cell  = row.querySelector('.line-total');
        if (cell) cell.textContent = line.toFixed(2) + ' ج';
        grand += line;
    });

    document.getElementById('grandTotal').textContent       = grand.toFixed(2) + ' ج';
    document.getElementById('totalCostDisplay').value       = grand.toFixed(2) + ' ج';

    const paid      = parseFloat(document.getElementById('amountPaid').value) || 0;
    const remaining = Math.max(0, grand - paid);
    document.getElementById('remainingDisplay').value       = remaining.toFixed(2) + ' ج';
}

document.getElementById('addItemBtn').addEventListener('click', () => {
    document.getElementById('itemsBody').insertAdjacentHTML('beforeend', createRow(rowIndex++));
    updateTotals();
});

document.getElementById('itemsBody').addEventListener('change', e => {
    const row = e.target.closest('tr');

    if (e.target.classList.contains('product-select')) {
        const opt  = e.target.selectedOptions[0];
        const type = opt?.dataset?.type;
        const price = opt?.dataset?.price || 0;

        row.querySelector('.selling-price').value = price;

        const expiry = row.querySelector('.expiry-date');
        const expiryNa = row.querySelector('.expiry-na');
        if (type === 'vaccination') {
            expiry.style.display = '';
            expiryNa.style.display = 'none';
            expiry.required = true;
        } else {
            expiry.style.display = 'none';
            expiryNa.style.display = '';
            expiry.required = false;
            expiry.value = '';
        }
    }

    updateTotals();
});

document.getElementById('itemsBody').addEventListener('input', e => {
    if (e.target.classList.contains('qty-input') ||
        e.target.classList.contains('purchase-price')) {
        updateTotals();
    }
});

document.getElementById('amountPaid').addEventListener('input', updateTotals);

document.getElementById('itemsBody').addEventListener('click', e => {
    if (e.target.closest('.remove-row')) {
        const rows = document.querySelectorAll('#itemsBody tr');
        if (rows.length <= 1) {
            alert('يجب أن تحتوي الفاتورة على بند واحد على الأقل');
            return;
        }
        e.target.closest('tr').remove();
        updateTotals();
    }
});

document.getElementById('itemsBody').insertAdjacentHTML('beforeend', createRow(rowIndex++));
</script>
@endsection
