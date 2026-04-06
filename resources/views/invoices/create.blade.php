@extends('layouts.app')

@section('title', __('invoices.create_title'))
@section('page-title', __('invoices.create_title'))

@php
    $productsJson = $products->map(function ($p) {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'price' => (float) $p->price,
            'stock_status' => $p->stock_status,
            'quantity' => (float) $p->quantity,
            'track_stock' => (bool) $p->track_stock,
        ];
    });
@endphp

@section('content')

    <!-- Choices CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />

    <form method="POST" action="{{ route('invoices.store') }}" id="quickSaleForm">
        @csrf

        <div class="row g-4">

            {{-- Customer info --}}
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-person-fill text-primary me-1"></i>
                        <span class="fw-bold">بيانات العميل</span>
                    </div>
                    <div class="card-body row g-3">
                        {{-- تم التعديل: حقل بحث مباشر عن العميل بالاسم أو الهاتف --}}
                        <div class="col-12">
                            <label class="form-label">{{ __('invoices.fields.customer_name') }}</label>
                            {{-- حقل مخفي يحمل customer_id عند الاختيار --}}
                            <input type="hidden" name="customer_id" id="customer_id" value="{{ old('customer_id') }}">
                            <div class="position-relative" id="customer-search-wrap">
                                <input type="text"
                                       id="customer_name_display"
                                       name="customer_name"
                                       class="form-control @error('customer_name') is-invalid @enderror"
                                       value="{{ old('customer_name') }}"
                                       placeholder="ابحث بالاسم أو الهاتف..."
                                       autocomplete="off">
                                @error('customer_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                {{-- نتائج البحث --}}
                                <div id="customer-search-results"
                                     style="display:none; position:absolute; top:100%; right:0; left:0; z-index:9999;
                                            background:#fff; border:1px solid #ced4da; border-top:0;
                                            border-radius:0 0 .25rem .25rem; max-height:220px; overflow-y:auto;
                                            box-shadow:0 .5rem 1rem rgba(0,0,0,.15);">
                                </div>
                            </div>
                            <div class="form-text text-muted">
                                اكتب 2 أحرف أو أكثر للبحث — سيُربط الاسم بالعميل تلقائياً إذا وُجد
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Grand Total Card --}}
                <div class="card mt-3 border-success">
                    <div class="card-body text-center">
                        <div class="text-muted small mb-1">{{ __('invoices.fields.grand_total') }}</div>
                        <div class="display-6 fw-bold text-success" id="grand-total-display">0</div>
                        <div class="text-muted">{{ __('messages.currency') }}</div>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-success btn-lg flex-fill">
                        <i class="bi bi-save-fill me-1"></i>{{ __('invoices.actions.save') }}
                    </button>
                    <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary btn-lg">
                        <i class="bi bi-arrow-right me-1"></i>{{ __('messages.back') }}
                    </a>
                </div>
            </div>

            {{-- Items --}}
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <span class="fw-bold">
                            <i class="bi bi-list-ul text-success me-1"></i>{{ __('invoices.fields.items') }}
                        </span>
                        <button type="button" class="btn btn-outline-success btn-sm" onclick="addRow()">
                            <i class="bi bi-plus-lg me-1"></i>{{ __('invoices.actions.add_item') }}
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0" id="items-table">
                                <thead>
                                    {{-- تم التعديل: تحسين مساحات وأعراض أعمدة الجدول لسهولة القراءة --}}
                                    <tr class="align-middle">
                                        <th style="min-width:250px" class="py-3 px-2">{{ __('invoices.fields.product') }}</th>
                                        <th style="width:130px" class="py-3 px-2 text-center">{{ __('invoices.fields.quantity') }}</th>
                                        <th style="width:140px" class="py-3 px-2 text-center">{{ __('invoices.fields.unit_price') }}</th>
                                        <th style="width:140px" class="py-3 px-2 text-center">{{ __('invoices.fields.line_total') }}</th>
                                        <th style="width:70px" class="text-center py-3 px-2">حذف</th>
                                    </tr>
                                </thead>
                                <tbody id="items-body"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </form>

    <style>
        #quickSaleForm .card-body.p-0,
        #quickSaleForm .table-responsive { overflow: visible; }
        #quickSaleForm #items-table { overflow: visible; }
    </style>

@endsection

@push('scripts')
    <!-- Choices JS -->
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    <script>
        const products = @json($productsJson);
        const selectProductPlaceholder = @json(__('invoices.messages.select_product'));
        let rowIdx = 0;

        function isProductOos(p) {
            return p.stock_status === 'out_of_stock' || (p.track_stock && p.quantity <= 0);
        }

        /* ─── Product Choices.js (AJAX, per-row) ─────────────────── */
        function initProductSelect(selectEl) {
            if (!selectEl) return;
            const idx = selectEl.getAttribute('data-idx');

            const instance = new Choices(selectEl, {
                searchEnabled: true,
                searchPlaceholderValue: 'ابحث عن منتج...',
                noResultsText: 'لا توجد نتائج',
                noChoicesText: 'اكتب للبحث...',
                itemSelectText: '',
                shouldSort: false,
                allowHTML: false,
            });

            selectEl._choicesInstance = instance;

            // Load all products statically
            const choices = products.map(function(p) {
                const oos = isProductOos(p);
                return {
                    value: String(p.id),
                    label: p.name + (oos ? ' (نفد المخزون)' : ''),
                    customProperties: { price: p.price },
                    disabled: oos,
                };
            });
            instance.setChoices(choices, 'value', 'label', true);

            // Auto-fill unit price on selection
            selectEl.addEventListener('change', function() {
                const val = this.value;
                if (!val) return;

                // Get price directly from the selected choice in the instance
                const selectedChoice = instance.getValue();
                const price = selectedChoice && selectedChoice.customProperties ?
                    selectedChoice.customProperties.price : 0;

                const priceInput = document.querySelector('[name="items[' + idx + '][unit_price]"]');
                if (priceInput) {
                    priceInput.value = parseFloat(price || 0).toFixed(2);
                    recalcRow(idx);
                }
            });
        }

        function addRow() {
            var idx = rowIdx++;
            // تم التعديل: تكبير الحقول النصية وهوامش وعرض الخلايا
            var row = '<tr id="row-' + idx + '" class="align-middle">' +
                '<td class="p-2">' +
                '<select name="items[' + idx + '][product_id]" ' +
                'id="product-select-' + idx + '" class="form-select product-choices-select" ' +
                'data-idx="' + idx + '" required>' +
                '<option value=""></option></select>' +
                '</td>' +
                // تم التعديل: تحديث القيم الافتراضية للحقل الرقمي
                '<td class="p-2"><input type="number" name="items[' + idx +
                '][quantity]" class="form-control text-center" value="1" min="0" step="1" oninput="recalcRow(' +
                idx + ')" required></td>' +
                '<td class="p-2"><input type="number" name="items[' + idx +
                '][unit_price]" class="form-control text-center" id="price-' + idx +
                '" placeholder="0" min="0" step="1" oninput="recalcRow(' + idx + ')" required></td>' +
                '<td class="p-2"><input type="text" class="form-control text-center bg-light text-primary fw-bold" id="total-' + idx +
                '" placeholder="0" readonly></td>' +
                '<td class="text-center p-2"><button type="button" class="btn btn-outline-danger" onclick="removeRow(' +
                idx + ')"><i class="bi bi-trash"></i></button></td>' +
                '</tr>';

            document.getElementById('items-body').insertAdjacentHTML('beforeend', row);
            // Initialise Choices.js AFTER the row is in the DOM
            initProductSelect(document.getElementById('product-select-' + idx));
        }

        function recalcRow(idx) {
            var qty = parseFloat(document.querySelector('[name="items[' + idx + '][quantity]"]').value) || 0;
            var price = parseFloat(document.getElementById('price-' + idx).value) || 0;
            document.getElementById('total-' + idx).value = (qty * price).toFixed(2);
            recalcGrandTotal();
        }

        function removeRow(idx) {
            var el = document.getElementById('row-' + idx);
            if (el) {
                var sel = el.querySelector('.product-choices-select');
                if (sel && sel._choicesInstance) {
                    sel._choicesInstance.destroy();
                }
                el.remove();
            }
            recalcGrandTotal();
        }

        function recalcGrandTotal() {
            var total = 0;
            document.querySelectorAll('[id^="total-"]').forEach(function(el) {
                total += parseFloat(el.value) || 0;
            });
            document.getElementById('grand-total-display').textContent = total.toFixed(2);
        }

        // Add first row on load
        addRow();

        // تم الإضافة: بحث مباشر عن العميل (Live Search) بالاسم أو الهاتف
        (function () {
            var searchInput   = document.getElementById('customer_name_display');
            var hiddenIdInput = document.getElementById('customer_id');
            var resultsBox    = document.getElementById('customer-search-results');
            var searchUrl     = '{{ route("customers.search") }}';
            var debounceTimer = null;

            function renderResults(customers) {
                if (!customers.length) {
                    resultsBox.innerHTML = '<div style="padding:.5rem .75rem;color:#888;font-size:.875rem;">لا توجد نتائج — سيُستخدم الاسم المدخل</div>';
                    resultsBox.style.display = 'block';
                    return;
                }
                resultsBox.innerHTML = customers.map(function (c) {
                    return '<div class="customer-result-item" data-id="' + c.id + '" data-name="' + c.name + '"' +
                           ' style="padding:.4rem .75rem;cursor:pointer;font-size:.875rem;border-bottom:1px solid #f0f0f0;">' +
                           '<strong>' + c.name + '</strong> <span style="color:#888;font-size:.8rem;">' + c.phone + '</span></div>';
                }).join('');
                resultsBox.style.display = 'block';

                // أحداث الاختيار
                resultsBox.querySelectorAll('.customer-result-item').forEach(function (el) {
                    el.addEventListener('mouseenter', function() { this.style.background = '#f5f5f5'; });
                    el.addEventListener('mouseleave', function() { this.style.background = ''; });
                    el.addEventListener('click', function () {
                        hiddenIdInput.value   = this.getAttribute('data-id');
                        searchInput.value     = this.getAttribute('data-name');
                        resultsBox.style.display = 'none';
                    });
                });
            }

            function clearSelection() {
                // إذا عدّل المستخدم النص بعد الاختيار نمسح الـ id
                hiddenIdInput.value = '';
            }

            searchInput.addEventListener('input', function () {
                clearSelection();
                var q = this.value.trim();
                clearTimeout(debounceTimer);

                if (q.length < 2) {
                    resultsBox.style.display = 'none';
                    return;
                }

                debounceTimer = setTimeout(function () {
                    fetch(searchUrl + '?q=' + encodeURIComponent(q), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (data) { renderResults(data); })
                    .catch(function () { resultsBox.style.display = 'none'; });
                }, 250);
            });

            // إغلاق القائمة عند النقر خارجها
            document.addEventListener('click', function (e) {
                if (!document.getElementById('customer-search-wrap').contains(e.target)) {
                    resultsBox.style.display = 'none';
                }
            });
        })();
    </script>
@endpush
