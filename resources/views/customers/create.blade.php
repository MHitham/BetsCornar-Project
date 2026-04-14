@extends('layouts.app')

@section('title', __('customers.create_title'))
@section('page-title', __('customers.create_title'))

@section('content')

    <!-- Choices CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />

    <form method="POST" action="{{ route('customers.store') }}" id="visitForm">
        @csrf

        <div class="row g-4">

            {{-- === Customer Info === --}}
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="bi bi-person-fill text-primary me-1"></i>
                        <span class="fw-bold">بيانات العميل والحيوان</span>
                    </div>
                    <div class="card-body row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('customers.fields.name') }} <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="name" id="name"
                                class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name', request('name')) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('customers.fields.phone') }} <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="phone" id="phone"
                                class="form-control @error('phone') is-invalid @enderror"
                                value="{{ old('phone', request('phone')) }}" placeholder="01xxxxxxxxx" required>
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('customers.fields.animal_type') }} <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="animal_type" id="animal_type"
                                class="form-control @error('animal_type') is-invalid @enderror" list="animal-types-list"
                                value="{{ old('animal_type') }}" placeholder="قط، كلب، طائر..." required>
                            <datalist id="animal-types-list">
                                @foreach (['قط', 'كلب', 'طائر', 'أرنب', 'زواحف', 'أخرى'] as $t)
                                    <option value="{{ $t }}">
                                @endforeach
                            </datalist>
                            @error('animal_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('customers.fields.address') }}</label>
                            <input type="text" name="address" class="form-control" value="{{ old('address') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('customers.fields.notes') }}</label>
                            <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- === Visit Info  تفاصيل الزيارة=== --}}
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="bi bi-clipboard2-pulse-fill text-success me-1"></i>
                        <span class="fw-bold">{{ __('customers.visit.title') }}</span>
                    </div>
                    <div class="card-body row g-3">

                        {{-- Consultation  سعر الكشف--}}
                        <div class="col-12">
                            <label class="form-label">{{ __('customers.visit.consultation_price') }} <span
                                    class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="consultation_price" id="consultation_price"
                                    class="form-control @error('consultation_price') is-invalid @enderror"
                                    value="{{ old('consultation_price', $consultationProduct?->price ?? 0) }}"
                                    step="1" min="0" required>
                                <span class="input-group-text">{{ __('messages.currency') }}</span>
                            </div>
                            @error('consultation_price')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- checkbox لتفعيل قسم التطعيمات --}}
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="has_vaccination"
                                    onchange="toggleVaccinations(this)">
                                <label class="form-check-label fw-semibold" for="has_vaccination">
                                    {{ __('customers.visit.has_vaccination') }}
                                </label>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            {{-- قسم التطعيمات — مخفي بالافتراضي ويظهر عند تفعيل الـ checkbox --}}
            <div class="col-12" id="vaccinations-card" style="display:none;">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <span class="fw-bold">
                            <i class="bi bi-capsule-pill text-primary me-1"></i>
                            {{ __('customers.visit.vaccinations_section') }}
                        </span>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addVaccination()">
                            <i class="bi bi-plus-lg me-1"></i>{{ __('customers.visit.add_vaccination') }}
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <table class="table mb-0" id="vaccinations-table">
                            <thead>
                                <tr>
                                    <th style="width:30%">{{ __('customers.visit.vaccine_product') }}</th>
                                    <th style="width:13%">{{ __('customers.visit.vaccine_quantity') }}</th>
                                    <th style="width:15%">{{ __('customers.visit.unit_price') }}</th>
                                    <th style="width:15%">{{ __('customers.visit.vaccination_date') }}</th>
                                    <th style="width:15%">{{ __('customers.visit.next_dose_date') }}</th>
                                    <th style="width:25%">{{ __('customers.visit.line_total') }}</th>
                                    <th style="width:5%" class="text-center">حذف</th>
                                </tr>
                            </thead>
                            <tbody id="vaccinations-body">
                                {{-- JS-rendered rows --}}
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <td colspan="7" class="text-muted small py-2 px-3">
                                        <i class="bi bi-info-circle me-1"></i>
                                        اضغط "إضافة تطعيم" لإضافة تطعيم أو أكثر في هذه الزيارة
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            {{-- === Additional Items === --}}
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <span class="fw-bold">
                            <i
                                class="bi bi-plus-circle-fill text-secondary me-1"></i>{{ __('customers.visit.additional_items') }}
                        </span>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addItem()">
                            <i class="bi bi-plus-lg me-1"></i>{{ __('customers.visit.add_item') }}
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <table class="table mb-0" id="items-table">
                            <thead>
                                <tr>
                                    <th style="width:40%">{{ __('customers.visit.product_service') }}</th>
                                    <th style="width:15%">{{ __('customers.visit.quantity') }}</th>
                                    <th style="width:18%">{{ __('customers.visit.unit_price') }}</th>
                                    <th style="width:18%">{{ __('customers.visit.line_total') }}</th>
                                    <th style="width:9%" class="text-center">حذف</th>
                                </tr>
                            </thead>
                            <tbody id="items-body">
                                {{-- JS-rendered rows --}}
                            </tbody>
                            <tfoot>
                                <tr class="table-light fw-bold">
                                    <td colspan="3" class="text-start">{{ __('customers.visit.grand_total') }}</td>
                                    <td id="grand-total-cell" class="text-primary fw-bold">0
                                        {{ __('messages.currency') }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            {{-- === Submit === --}}
            <div class="col-12 d-flex gap-3">
                <button type="submit" class="btn btn-primary btn-lg px-5">
                    <i class="bi bi-save-fill me-1"></i>{{ __('customers.actions.save') }}
                </button>
                <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-lg">
                    <i class="bi bi-arrow-right me-1"></i>{{ __('messages.back') }}
                </a>
            </div>

        </div>
    </form>



    @push('scripts')
        <!-- Choices JS -->
        <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
        <script>
            @php
                $vaccinesJson = $vaccines->map(function ($v) use ($vaccineUsableQty) {
                    return [
                        'id' => $v->id,
                        'name' => $v->name,
                        'price' => (float) $v->price,
                        'stock_status' => $v->stock_status,
                        'quantity' => (float) $v->quantity,
                        'track_stock' => (bool) $v->track_stock,
                        'usable_qty' => (float) ($vaccineUsableQty[$v->id] ?? 0),
                    ];
                });

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

            const vaccinesData = @json($vaccinesJson);
            const productsData = @json($productsJson);
            const selectProductPlaceholder = @json(__('customers.visit.select_product_service'));
            const selectVaccinePlaceholder = @json(__('customers.visit.select_vaccine'));
            let itemIndex = 0;
            // ── عداد صفوف التطعيمات الديناميكية ────────────────────
            let vaccinationIndex = 0;

            /* ─── OOS helpers ─────────────────────────────────────────── */
            function isProductOos(p) {
                return p.stock_status === 'out_of_stock' || (p.track_stock && p.quantity <= 0);
            }

            function isVaccineOos(v) {
                return v.stock_status === 'out_of_stock' || (v.track_stock && v.quantity <= 0) || v.usable_qty <= 0;
            }

            /* ─── إدارة التطعيمات الديناميكية ──────────────────────── */
            // ── إضافة صف تطعيم جديد ────────────────────────────────
            window.addVaccination = function() {
                const idx = vaccinationIndex++;
                const row = `
                    <tr id="vacc-row-${idx}">
                        <td>
                            <select name="vaccinations[${idx}][vaccine_product_id]"
                                    id="vaccine-select-${idx}"
                                    class="form-select form-select-sm vaccine-choices-select"
                                    data-idx="${idx}" required>
                                <option value=""></option>
                            </select>
                        </td>
                        <td>
                            <!-- تم التعديل: تحديث القيم الافتراضية للحقل الرقمي -->
                            <input type="number" name="vaccinations[${idx}][vaccine_quantity]"
                                   class="form-control form-control-sm vacc-qty-input"
                                   data-idx="${idx}" value="1" min="0" step="1"
                                   oninput="recalcVaccRow(${idx})" required>
                        </td>
                        <td>
                            <div class="input-group input-group-sm">
                                <!-- تم التعديل: تحديث القيم الافتراضية للحقل الرقمي -->
                                <input type="number" name="vaccinations[${idx}][vaccine_unit_price]"
                                       class="form-control form-control-sm vacc-price-input"
                                       data-idx="${idx}" placeholder="0" min="0" step="0.01"
                                       oninput="recalcVaccRow(${idx})" required>
                                <span class="input-group-text">ج.م</span>
                            </div>
                        </td>
                        <td>
                            <input type="date" name="vaccinations[${idx}][vaccination_date]"
                                   class="form-control form-control-sm"
                                   value="{{ date('Y-m-d') }}" required>
                        </td>
                        <td>
                            <input type="date" name="vaccinations[${idx}][next_dose_date]"
                                   class="form-control form-control-sm" required>
                        </td>
                        <td>
                            <!-- تم التعديل: تحديث القيم الافتراضية للحقل الرقمي -->
                            <input type="text" class="form-control form-control-sm bg-light vacc-line-total"
                                   id="vacc-line-total-${idx}" placeholder="0" readonly>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVaccination(${idx})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>`;
                document.getElementById('vaccinations-body').insertAdjacentHTML('beforeend', row);
                // تهيئة Choices.js للقاح الجديد بعد إدراج الصف في DOM
                initVaccineSelect(document.getElementById('vaccine-select-' + idx));
            };

            // ── تهيئة Choices.js لقائمة اللقاحات (بيانات ثابتة) ───
            function initVaccineSelect(selectEl) {
                if (!selectEl) return;
                const idx = selectEl.getAttribute('data-idx');

                const instance = new Choices(selectEl, {
                    searchEnabled: true,
                    searchPlaceholderValue: 'ابحث عن لقاح...',
                    noResultsText: 'لا توجد نتائج',
                    itemSelectText: '',
                    shouldSort: false,
                    allowHTML: false,
                });

                selectEl._choicesInstance = instance;

                // بناء قائمة اللقاحات من البيانات الثابتة
                const choices = vaccinesData.map(function(v) {
                    const oos = isVaccineOos(v);
                    return {
                        value: String(v.id),
                        label: v.name + (oos ? ' (نفد المخزون)' : ''),
                        customProperties: {
                            price: v.price
                        },
                        disabled: oos,
                    };
                });
                instance.setChoices(choices, 'value', 'label', true);

                // ملء السعر تلقائياً عند اختيار لقاح
                selectEl.addEventListener('change', function() {
                    const val = this.value;
                    if (!val) return;

                    const selectedChoice = instance.getValue();
                    const price = selectedChoice && selectedChoice.customProperties ?
                        selectedChoice.customProperties.price :
                        0;

                    const priceInput = document.querySelector(
                        '[name="vaccinations[' + idx + '][vaccine_unit_price]"]'
                    );
                    if (priceInput) {
                        priceInput.value = parseFloat(price || 0).toFixed(2);
                        recalcVaccRow(idx);
                    }
                });
            }

            // ── حساب الإجمالي لصف التطعيم ──────────────────────────
            window.recalcVaccRow = function(idx) {
                const qty = parseFloat(document.querySelector(`[name="vaccinations[${idx}][vaccine_quantity]"]`).value) ||
                    0;
                const price = parseFloat(document.querySelector(`[name="vaccinations[${idx}][vaccine_unit_price]"]`)
                    .value) || 0;
                document.getElementById(`vacc-line-total-${idx}`).value = (qty * price).toFixed(2);
                recalcTotal();
            };

            // ── حذف صف التطعيم ──────────────────────────────────────
            window.removeVaccination = function(idx) {
                const row = document.getElementById(`vacc-row-${idx}`);
                if (row) {
                    // تدمير مثيل Choices.js لتجنب تسriب الذاكرة
                    const sel = row.querySelector('.vaccine-choices-select');
                    if (sel && sel._choicesInstance) {
                        sel._choicesInstance.destroy();
                    }
                    row.remove();
                }
                recalcTotal();
            };

            /* ─── Product Choices.js (Local, per-row) ─────────────────── */
            function initProductSelect(selectEl) {
                if (!selectEl) return;
                const idx = selectEl.getAttribute('data-idx');

                const instance = new Choices(selectEl, {
                    searchEnabled: true,
                    searchPlaceholderValue: 'ابحث عن منتج...',
                    noResultsText: 'لا توجد نتائج',
                    itemSelectText: '',
                    shouldSort: false,
                    allowHTML: false,
                });

                selectEl._choicesInstance = instance;

                // Load all products statically
                const choices = productsData.map(function(p) {
                    const oos = isProductOos(p);
                    return {
                        value: String(p.id),
                        label: p.name + (oos ? ' (نفد المخزون)' : ''),
                        customProperties: {
                            price: p.price
                        },
                        disabled: oos,
                    };
                });
                instance.setChoices(choices, 'value', 'label', true);

                // Auto-fill unit price on selection
                selectEl.addEventListener('change', function() {
                    const val = this.value;
                    if (!val) return;

                    const selectedChoice = instance.getValue();
                    const price = selectedChoice && selectedChoice.customProperties ?
                        selectedChoice.customProperties.price :
                        0;

                    const priceInput = document.querySelector(
                        '[name="additional_items[' + idx + '][unit_price]"]'
                    );
                    if (priceInput) {
                        priceInput.value = parseFloat(price || 0).toFixed(2);
                        recalcRow(idx);
                    }
                });
            }

            /* ─── Row management ─────────────────────────────────────── */
            function addItem() {
                const idx = itemIndex++;
                const row = `
        <tr id="row-${idx}">
            <td>
                <select name="additional_items[${idx}][product_id]"
                        id="product-select-${idx}"
                        class="form-select form-select-sm product-choices-select"
                        data-idx="${idx}" required>
                    <option value=""></option>
                </select>
            </td>
            <td>
                <!-- تم التعديل: تحديث القيم الافتراضية للحقل الرقمي -->
                <input type="number" name="additional_items[${idx}][quantity]"
                       class="form-control form-control-sm qty-input"
                       data-idx="${idx}" value="1" min="0" step="1"
                       oninput="recalcRow(${idx})" required>
            </td>
            <td>
                <div class="input-group input-group-sm">
                    <!-- تم التعديل: تحديث القيم الافتراضية للحقل الرقمي -->
                    <input type="number" name="additional_items[${idx}][unit_price]"
                           class="form-control form-control-sm price-input"
                           data-idx="${idx}" placeholder="0" min="0" step="0.01"
                           oninput="recalcRow(${idx})" required>
                    <span class="input-group-text">ج.م</span>
                </div>
            </td>
            <td>
                <!-- تم التعديل: تحديث القيم الافتراضية للحقل الرقمي -->
                <input type="text" class="form-control form-control-sm bg-light line-total"
                       id="line-total-${idx}" placeholder="0" readonly>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItem(${idx})">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>`;
                document.getElementById('items-body').insertAdjacentHTML('beforeend', row);
                // Initialise Choices.js AFTER the row is in the DOM
                initProductSelect(document.getElementById('product-select-' + idx));
            }

            function recalcRow(idx) {
                const qty = parseFloat(document.querySelector(`[name="additional_items[${idx}][quantity]"]`).value) || 0;
                const price = parseFloat(document.querySelector(`[name="additional_items[${idx}][unit_price]"]`).value) || 0;
                document.getElementById(`line-total-${idx}`).value = (qty * price).toFixed(2);
                recalcTotal();
            }

            function removeItem(idx) {
                const row = document.getElementById(`row-${idx}`);
                if (row) {
                    // Destroy the Choices.js instance to prevent memory leaks
                    const sel = row.querySelector('.product-choices-select');
                    if (sel && sel._choicesInstance) {
                        sel._choicesInstance.destroy();
                    }
                    row.remove();
                }
                recalcTotal();
            }

            function recalcTotal() {
                let total = parseFloat(document.getElementById('consultation_price').value) || 0;

                // جمع إجماليات التطعيمات من كل صف
                document.querySelectorAll('.vacc-line-total').forEach(function(el) {
                    total += parseFloat(el.value) || 0;
                });

                // جمع إجماليات المنتجات/الخدمات الإضافية
                document.querySelectorAll('.line-total').forEach(function(el) {
                    total += parseFloat(el.value) || 0;
                });

                document.getElementById('grand-total-cell').textContent = total.toFixed(2) + ' ج.م';
            }

            // ── إظهار/إخفاء قسم التطعيمات عند الضغط على الـ checkbox ──
            window.toggleVaccinations = function(checkbox) {
                const card = document.getElementById('vaccinations-card');
                if (checkbox.checked) {
                    // إظهار القسم وإضافة صف تلقائي لو الجدول فاضي
                    card.style.display = 'block';
                    if (document.getElementById('vaccinations-body').children.length === 0) {
                        addVaccination();
                    }
                } else {
                    // إخفاء القسم وحذف كل صفوف التطعيمات وإرجاع الإجمالي
                    card.style.display = 'none';
                    const body = document.getElementById('vaccinations-body');
                    Array.from(body.querySelectorAll('tr')).forEach(row => {
                        const idx = row.id.replace('vacc-row-', '');
                        removeVaccination(parseInt(idx));
                    });
                }
                recalcTotal();
            };

            /* ─── مستمعو الإدخال العام ─────────────────────────── */
            document.getElementById('consultation_price').addEventListener('input', recalcTotal);
        </script>
    @endpush

@endsection
