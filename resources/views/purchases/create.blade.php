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

    {{-- حماية نموذج الشراء من الإرسال المكرر. --}}
    <form action="{{ route('purchases.store') }}" method="POST" id="purchaseForm" data-prevent-double>
        @csrf
        <x-idempotency-token />

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
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input type="hidden" name="is_from_clinic_cash" value="0">
                            <input class="form-check-input"
                                   type="checkbox"
                                   role="switch"
                                   id="isFromClinicCash"
                                   name="is_from_clinic_cash"
                                   value="1"
                                   @checked(old('is_from_clinic_cash', true))>
                            <label class="form-check-label fw-semibold" for="isFromClinicCash">
                                الدفعة من درج العيادة؟
                            </label>
                            <div class="form-text text-muted">
                                سيتم خصمها من إيراد يوم الشراء
                            </div>
                        </div>
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
const products = @js($products);
const productsById = new Map(products.map(product => [String(product.id), product]));
const previousItems = @js(old('items', []));
const previousPriceUpdates = @js(old('update_selling_price', []));
const itemsBody = document.getElementById('itemsBody');

let rowIndex = 0;

function addRow(item = {}, updatePrice = false) {
    const index = rowIndex++;
    itemsBody.insertAdjacentHTML('beforeend', `
    <tr>
        <td>
            <select name="items[${index}][product_id]" class="form-select form-select-sm product-select" aria-label="المنتج" required>
                <option value="">اختر المنتج</option>
                <option value="new">+ منتج جديد</option>
            </select>
            <div class="new-product-fields mt-2" hidden>
                <label for="new_product_name_${index}" class="form-label small mb-1">اسم الصنف الجديد</label>
                <input id="new_product_name_${index}" name="items[${index}][new_product_name]" type="text"
                       class="form-control form-control-sm new-product-name" maxlength="255">
                <label for="new_product_type_${index}" class="form-label small mt-2 mb-1">نوع الصنف</label>
                <select id="new_product_type_${index}" name="items[${index}][new_product_type]" class="form-select form-select-sm new-product-type">
                    <option value="product">منتج عادي</option>
                    <option value="vaccination">تطعيم</option>
                </select>
                <div class="mt-2 existing-product-match" aria-live="polite" hidden>
                    <span class="small text-muted">الصنف موجود بالفعل</span>
                    <button type="button" class="btn btn-outline-primary btn-sm use-existing-product mt-1"></button>
                </div>
            </div>
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
                   class="form-control form-control-sm expiry-date" aria-label="تاريخ الانتهاء" hidden>
            <span class="text-muted small expiry-na">—</span>
        </td>
        <td class="text-center">
            <div class="form-check form-switch d-flex justify-content-center">
                <input class="form-check-input update-price-check" type="checkbox" aria-label="تحديث سعر البيع"
                       name="update_selling_price[${index}]" value="1">
            </div>
        </td>
        <td class="fw-semibold line-total text-primary">0.00 ج</td>
        <td>
            <button type="button" class="btn btn-sm btn-outline-danger remove-row" aria-label="حذف البند">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    </tr>`);

    const row = itemsBody.lastElementChild;
    const select = row.querySelector('.product-select');
    for (const product of products) {
        select.add(new Option(product.name, String(product.id)));
    }
    select.value = item.product_id ?? '';
    if (select.selectedIndex === -1) select.value = '';
    row.querySelector('.new-product-name').value = item.new_product_name ?? '';
    row.querySelector('.new-product-type').value = item.new_product_type ?? 'product';
    row.querySelector('.qty-input').value = item.quantity ?? 1;
    row.querySelector('.purchase-price').value = item.purchase_price_per_unit ?? 0;
    row.querySelector('.selling-price').value = item.selling_price_per_unit ?? 0;
    row.querySelector('.expiry-date').value = item.expiry_date ?? '';
    row.querySelector('.update-price-check').checked = updatePrice === true || String(updatePrice) === '1';
    updateProductFields(row);
}

function updateProductFields(row) {
    const selection = row.querySelector('.product-select').value;
    const isNew = selection === 'new';
    const name = row.querySelector('.new-product-name');
    const type = row.querySelector('.new-product-type');
    const product = productsById.get(selection);
    const isVaccine = (isNew ? type.value : product?.type) === 'vaccination';
    const expiry = row.querySelector('.expiry-date');

    row.querySelector('.new-product-fields').hidden = !isNew;
    name.disabled = type.disabled = !isNew;
    name.required = type.required = isNew;
    expiry.hidden = expiry.disabled = !isVaccine;
    expiry.required = isVaccine;
    row.querySelector('.expiry-na').hidden = isVaccine;
    if (!isVaccine) expiry.value = '';

    const updatePrice = row.querySelector('.update-price-check');
    updatePrice.disabled = !product;
    if (!product) updatePrice.checked = false;

    showExistingProduct(row);
}

function showExistingProduct(row) {
    const name = row.querySelector('.new-product-name');
    const type = row.querySelector('.new-product-type').value;
    const normalizedName = name.value.trim().toLowerCase();
    const existing = name.disabled || !normalizedName ? undefined : products.find(product =>
        product.type === type && product.name.trim().toLowerCase() === normalizedName
    );

    row.querySelector('.existing-product-match').hidden = !existing;
    name.setCustomValidity(existing ? 'الصنف موجود بالفعل. اختاره من القائمة.' : '');
    if (existing) {
        const button = row.querySelector('.use-existing-product');
        button.dataset.productId = existing.id;
        button.textContent = 'اختيار ' + existing.name;
    }
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
    addRow();
    updateTotals();
});

itemsBody.addEventListener('change', e => {
    const row = e.target.closest('tr');
    if (e.target.classList.contains('product-select')) {
        row.querySelector('.selling-price').value = productsById.get(e.target.value)?.price ?? 0;
        updateProductFields(row);
        if (e.target.value === 'new') row.querySelector('.new-product-name').focus();
    } else if (e.target.classList.contains('new-product-type')) {
        updateProductFields(row);
    }

    updateTotals();
});

itemsBody.addEventListener('input', e => {
    if (e.target.classList.contains('new-product-name')) {
        showExistingProduct(e.target.closest('tr'));
    }
    if (e.target.classList.contains('qty-input') ||
        e.target.classList.contains('purchase-price')) {
        updateTotals();
    }
});

document.getElementById('amountPaid').addEventListener('input', updateTotals);

itemsBody.addEventListener('click', e => {
    const existingButton = e.target.closest('.use-existing-product');
    if (existingButton) {
        const select = existingButton.closest('tr').querySelector('.product-select');
        select.value = existingButton.dataset.productId;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        select.focus();
    }

    if (e.target.closest('.remove-row')) {
        if (itemsBody.rows.length <= 1) {
            alert('يجب أن تحتوي الفاتورة على بند واحد على الأقل');
            return;
        }
        e.target.closest('tr').remove();
        updateTotals();
    }
});

for (const [index, item] of Object.entries(previousItems ?? {})) {
    if (item && typeof item === 'object' && !Array.isArray(item)) {
        addRow(item, previousPriceUpdates?.[index]);
    }
}
if (!itemsBody.rows.length) addRow();
updateTotals();
</script>
@endsection
