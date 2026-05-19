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
                        {{-- تم الإضافة: بحث مباشر بالاسم أو الهاتف --}}
                        <div class="col-12">
                            <label class="form-label text-primary fw-bold"><i class="bi bi-search me-1"></i>بحث عن عميل مسجل (اختياري)</label>
                            <div class="position-relative" id="customer-search-wrap">
                                <input type="text" id="customer_search" class="form-control"
                                       autocomplete="off" placeholder="ابحث بالاسم أو رقم الهاتف لاستدعاء البيانات تلقائياً...">
                                <div id="customer-search-results" style="display:none; position:absolute; top:100%; right:0; left:0; z-index:9999; background:#fff; border:1px solid #ced4da; border-top:0; border-radius:0 0 .25rem .25rem; max-height:220px; overflow-y:auto; box-shadow:0 .5rem 1rem rgba(0,0,0,.15);"></div>
                            </div>
                        </div>

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
                        
                        <!-- تم الإضافة: اختيار الحيوان بناءً على بحث الهاتف الفريد -->
                        <div class="col-md-6">
                            <label class="form-label">
                                الحيوان (اختياري)
                                <span id="animalLoading" class="spinner-border spinner-border-sm text-primary ms-2 d-none" role="status"></span>
                            </label>
                            <div class="input-group">
                                <select name="animal_id" id="animalSelect" class="form-select @error('animal_id') is-invalid @enderror" disabled>
                                    <option value="">-- أدخل رقم الهاتف أولاً --</option>
                                </select>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newAnimalModal" title="إضافة حيوان جديد لهذه الزيارة" style="padding: 0.375rem 0.75rem;">
                                    <i class="bi bi-plus-lg fw-bold"></i> إضافة حيوان
                                </button>
                            </div>
                            @error('animal_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
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
                        {{-- Diagnosis Field --}}
                        <div class="col-12 mt-3">
                            <label class="form-label">تشخيص الحيوان</label>
                            <textarea name="diagnosis" class="form-control" rows="2" placeholder="أدخل التشخيص هنا لهذه الزيارة فقط...">{{ old('diagnosis') }}</textarea>
                        </div>

                        {{-- checkbox لتفعيل قسم التطعيمات --}}
                        <div class="col-12 mt-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="has_vaccination"
                                    onchange="toggleVaccinations(this)">
                                <label class="form-check-label fw-semibold" for="has_vaccination" style="margin-right: 0.5rem;">
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
    <th style="width:27%">{{ __('customers.visit.vaccine_product') }}</th>
    <th style="width:9%">{{ __('customers.visit.vaccine_quantity') }}</th>
    <th style="width:12%">{{ __('customers.visit.unit_price') }}</th>
    <th style="width:15%">{{ __('customers.visit.vaccination_date') }}</th>
    <th style="width:15%">{{ __('customers.visit.next_dose_date') }}</th>
    <th style="width:17%">{{ __('customers.visit.line_total') }}</th>
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

            {{-- === الدفع الجزئي (اختياري) === --}}
            <div class="col-12">
                <div class="card border-0 bg-light">
                    <div class="card-body py-3">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="partial_payment_toggle"
                                   onchange="togglePartialPayment(this)">
                            <label class="form-check-label fw-semibold" for="partial_payment_toggle"
                                   style="margin-right: 0.5rem;">
                                <i class="bi bi-cash-coin me-1 text-warning"></i>
                                دفع جزئي؟ (اتركه بدون تفعيل لو العميل سدّد كامل الفاتورة)
                            </label>
                        </div>

                        {{-- حقول الدفع الجزئي — مخفية بالافتراضي --}}
                        <div id="partial-payment-section" style="display:none;" class="mt-3">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">إجمالي الفاتورة</label>
                                    <input type="text" id="pp_total_display"
                                           class="form-control bg-white" readonly placeholder="0 ج">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">
                                        المدفوع الآن <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="number" name="amount_paid" id="amount_paid_input"
                                               class="form-control" min="0" step="0.01"
                                               placeholder="0"
                                               oninput="updateRemaining()">
                                        <span class="input-group-text">ج</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">المتبقي على العميل</label>
                                    <div class="input-group">
                                        <input type="text" id="pp_remaining_display"
                                               class="form-control text-danger fw-bold bg-white"
                                               readonly placeholder="0 ج">
                                        <span class="input-group-text">ج</span>
                                    </div>
                                </div>
                            </div>
                        </div>
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

    <!-- Modal إضافة حيوان جديد مؤقتاً للزيارة -->
    <div class="modal fade" id="newAnimalModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-primary"><i class="bi bi-plus-circle me-2"></i>إضافة حيوان مؤقتاً للزيارة الحالية</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body row g-3 text-start" dir="rtl">
                    <div class="col-12 mb-2 text-muted small">
                        سيتم حفظ بيانات هذا الحيوان تلقائياً بمجرد ضغطك على الأيقونة وحفظ فاتورة الزيارة الأساسية.
                    </div>
                    <div class="col-md-6 text-end">
                        <label class="form-label">الاسم <span class="text-danger">*</span></label>
                        <input type="text" id="modal_animal_name" class="form-control" placeholder="اسم الحيوان" required>
                    </div>
                    <div class="col-md-6 text-end">
                        <label class="form-label">النوع <span class="text-danger">*</span></label>
                        <input type="text" id="modal_animal_species" class="form-control" placeholder="قطة، كلب..." required>
                    </div>
                    <div class="col-md-6 text-end">
                        <label class="form-label">الفصيلة</label>
                        <input type="text" id="modal_animal_breed" class="form-control">
                    </div>
                    <div class="col-md-6 text-end">
                        <label class="form-label">العمر</label>
                        <input type="text" id="modal_animal_age" class="form-control">
                    </div>
                    <div class="col-md-6 text-end">
                        <label class="form-label">الجنس</label>
                        <select id="modal_animal_gender" class="form-select">
                            <option value="">غير محدد</option>
                            <option value="male">ذكر</option>
                            <option value="female">أنثى</option>
                        </select>
                    </div>
                    <div class="col-md-6 text-end">
                        <label class="form-label">الوزن (كجم)</label>
                        <input type="number" step="0.01" id="modal_animal_weight" class="form-control">
                    </div>
                    <div class="col-md-6 text-end">
                        <label class="form-label">اللون</label>
                        <input type="text" id="modal_animal_color" class="form-control">
                    </div>
                    <div class="col-12 text-end">
                        <label class="form-label">ملاحظات</label>
                        <textarea id="modal_animal_notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-primary px-4" onclick="saveModalAnimal()">حفظ وإدراج للزيارة</button>
                </div>
            </div>
        </div>
    </div>



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
                           <div class="input-group input-group-sm">
        <input type="text"
               class="form-control form-control-sm bg-light vacc-line-total"
               id="vacc-line-total-${idx}"
               placeholder="0"
               readonly>
        <span class="input-group-text">جنيه</span>
    </div>

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
                        priceInput.value = parseFloat(price || 0);
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
                document.getElementById(`vacc-line-total-${idx}`).value = (qty * price);
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
                        priceInput.value = parseFloat(price || 0);
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
                document.getElementById(`line-total-${idx}`).value = (qty * price);
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

            // ── بحث العميل بالهاتف لجلب قائمة الحيوانات (Phase 2) ──────────
            const phoneInput = document.getElementById('phone');
            const animalSelect = document.getElementById('animalSelect');
            const addAnimalLink = document.getElementById('addAnimalLink');
            const animalLoading = document.getElementById('animalLoading');

            let lookupTimeout = null;

            phoneInput.addEventListener('input', function() {
                const phone = this.value.trim();
                
                if (lookupTimeout) clearTimeout(lookupTimeout);

                if (phone.length < 8) {
                    resetAnimalSelect();
                    return;
                }

                lookupTimeout = setTimeout(() => {
                    performCustomerLookup(phone);
                }, 800);
            });

            function resetAnimalSelect() {
                let hasNew = Array.from(animalSelect.options).find(o => o.value === 'new_animal');
                if (hasNew) {
                    animalSelect.innerHTML = '';
                    animalSelect.appendChild(hasNew);
                    animalSelect.disabled = false;
                    animalSelect.value = 'new_animal';
                } else {
                    animalSelect.innerHTML = '<option value="">-- أدخل رقم الهاتف المكتمل أولاً --</option>';
                    animalSelect.disabled = true;
                }
                
                if (typeof addAnimalLink !== 'undefined' && addAnimalLink) {
                    addAnimalLink.classList.add('d-none');
                    addAnimalLink.href = '#';
                }
            }

            function saveModalAnimal() {
                const name = document.getElementById('modal_animal_name').value.trim();
                const species = document.getElementById('modal_animal_species').value.trim();
                if (!name || !species) {
                    alert('الاسم والنوع (قطة، كلب، إلخ..) هما حقلان مطلوبان');
                    return;
                }

                // Collect other values
                const breed = document.getElementById('modal_animal_breed').value.trim();
                const age = document.getElementById('modal_animal_age').value.trim();
                const gender = document.getElementById('modal_animal_gender').value;
                const weight = document.getElementById('modal_animal_weight').value;
                const color = document.getElementById('modal_animal_color').value.trim();
                const notes = document.getElementById('modal_animal_notes').value.trim();

                // Clear previous hidden fields representing new animal
                document.querySelectorAll('.new-animal-field').forEach(el => el.remove());

                // Inject hidden fields into the main form
                const form = document.getElementById('visitForm');
                const fields = { name, species, breed, age, gender, weight, color, notes };
                for (const [k, v] of Object.entries(fields)) {
                    if (v) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = `new_animal[${k}]`;
                        input.value = v;
                        input.className = 'new-animal-field';
                        form.appendChild(input);
                    }
                }

                // Add to dropdown visually
                let option = Array.from(animalSelect.options).find(o => o.value === 'new_animal');
                if (!option) {
                    option = document.createElement('option');
                    option.value = 'new_animal';
                    animalSelect.appendChild(option);
                }
                option.textContent = `${name} (${species}) - مضاف جديد لهذه الزيارة`;
                
                animalSelect.disabled = false;
                animalSelect.value = 'new_animal';

                // Close the modal correctly utilizing window.bootstrap
                const modalEl = document.getElementById('newAnimalModal');
                const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                modal.hide();
            }

            async function performCustomerLookup(phone) {
                animalLoading.classList.remove('d-none');
                
                try {
                    const response = await fetch(`{{ route('customers.lookup-for-visit') }}?phone=${encodeURIComponent(phone)}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    
                    if (!response.ok) throw new Error('Network response error');
                    
                    const data = await response.json();
                    
                    animalSelect.innerHTML = '<option value="">-- اختر الحيوان (اختياري) --</option>';
                    animalSelect.disabled = false;
                    
                    if (data && data.id) {
                        if (data.animals && data.animals.length > 0) {
                            data.animals.forEach(animal => {
                                const option = document.createElement('option');
                                option.value = animal.id;
                                option.textContent = `${animal.name} (${animal.species || 'غير محدد'})`;
                                animalSelect.appendChild(option);
                            });
                        }
                        
                        if (typeof addAnimalLink !== 'undefined' && addAnimalLink) {
                            const indexUrl = `{{ url('customers') }}/${data.id}/animals`;
                            addAnimalLink.href = indexUrl;
                            addAnimalLink.classList.remove('d-none');
                        }
                    } else {
                        const option = document.createElement('option');
                        option.value = "";
                        option.textContent = "-- لا يوجد عميل بهذا الرقم، اضغط الزر الأزرق لإضافة حيوان --";
                        animalSelect.appendChild(option);
                        animalSelect.disabled = true;
                        
                        if (typeof addAnimalLink !== 'undefined' && addAnimalLink) {
                            addAnimalLink.classList.add('d-none');
                            addAnimalLink.href = '#';
                        }
                    }

                    // أعد إرفاق الخيار "إضافة حيوان جديد" إن وجد تم إضافته مؤتمراً كـ Draft
                    let possibleNewAnimalField = document.querySelector('.new-animal-field');
                    if (possibleNewAnimalField && !Array.from(animalSelect.options).find(o => o.value === 'new_animal')) {
                        const opt = document.createElement('option');
                        opt.value = 'new_animal';
                        opt.textContent = document.getElementById('modal_animal_name').value + ' - مضاف جديد لهذه الزيارة';
                        animalSelect.appendChild(opt);
                        animalSelect.value = 'new_animal';
                        animalSelect.disabled = false;
                    }
                } catch (error) {
                    console.error('Customer lookup error:', error);
                    resetAnimalSelect();
                } finally {
                    animalLoading.classList.add('d-none');
                }
            }

            // On page load, if phone has value, trigger lookup
            if (phoneInput.value.trim().length >= 8) {
                performCustomerLookup(phoneInput.value.trim());
            }

            // ── بحث مباشر عن العميل (Live Search) ──────────
            (function () {
                var searchInput   = document.getElementById('customer_search');
                var resultsBox    = document.getElementById('customer-search-results');
                var searchUrl     = '{{ route("customers.search") }}';
                var debounceTimer = null;

                if (!searchInput || !resultsBox) return;

                function renderResults(customers) {
                    if (!customers.length) {
                        resultsBox.innerHTML = '<div style="padding:.5rem .75rem;color:#888;font-size:.875rem;">لا توجد نتائج</div>';
                        resultsBox.style.display = 'block';
                        return;
                    }
                    resultsBox.innerHTML = customers.map(function (c) {
                        return '<div class="customer-result-item" data-id="' + c.id + '" data-name="' + c.name + '" data-phone="' + c.phone + '" data-address="' + (c.address||'') + '" data-type="' + (c.animal_type||'') + '"' +
                               ' style="padding:.4rem .75rem;cursor:pointer;font-size:.875rem;border-bottom:1px solid #f0f0f0;">' +
                               '<strong>' + c.name + '</strong> <span style="color:#888;font-size:.8rem;">(' + c.phone + ')</span></div>';
                    }).join('');
                    resultsBox.style.display = 'block';

                    resultsBox.querySelectorAll('.customer-result-item').forEach(function (el) {
                        el.addEventListener('mouseenter', function() { this.style.background = '#f5f5f5'; });
                        el.addEventListener('mouseleave', function() { this.style.background = ''; });
                        el.addEventListener('click', function () {
                            const pName = this.getAttribute('data-name');
                            const pPhone = this.getAttribute('data-phone');
                            
                            document.getElementById('name').value = pName;
                            document.getElementById('phone').value = pPhone;
                            const addr = document.querySelector('input[name="address"]');
                            if (addr) addr.value = this.getAttribute('data-address');
                            
                            const typeInput = document.querySelector('input[name="animal_type"]');
                            if (typeInput) {
                                typeInput.value = this.getAttribute('data-type');
                            }
                            
                            searchInput.value = '';
                            resultsBox.style.display = 'none';

                            // Trigger phone lookup manually to load animals
                            if (pPhone && pPhone.length >= 8) {
                                performCustomerLookup(pPhone);
                            }
                        });
                    });
                }

                searchInput.addEventListener('input', function () {
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

                document.addEventListener('click', function (e) {
                    if (document.getElementById('customer-search-wrap') && !document.getElementById('customer-search-wrap').contains(e.target)) {
                        if(resultsBox) resultsBox.style.display = 'none';
                    }
                });
            })();

            // ── الدفع الجزئي ────────────────────────────────────────
            window.togglePartialPayment = function(checkbox) {
                const section = document.getElementById('partial-payment-section');
                const input   = document.getElementById('amount_paid_input');
                if (checkbox.checked) {
                    section.style.display = 'block';
                    updateRemaining();
                } else {
                    section.style.display = 'none';
                    // لما الدفع الجزئي مش مفعل، المبلغ المدفوع = الإجمالي كاملاً
                    const totalText = document.getElementById('grand-total-cell').textContent;
                    const total = parseFloat(totalText.replace(/[^\d.]/g, '')) || 0;
                    if (input) input.value = total.toFixed(2);
                }
            };

            window.updateRemaining = function() {
                const totalText = document.getElementById('grand-total-cell').textContent;
                const total     = parseFloat(totalText.replace(/[^\d.]/g, '')) || 0;
                const paid      = parseFloat(document.getElementById('amount_paid_input').value) || 0;
                const remaining = Math.max(0, total - paid);

                document.getElementById('pp_total_display').value    = total.toFixed(2) + ' ج';
                document.getElementById('pp_remaining_display').value = remaining.toFixed(2);
            };

            // تحديث عرض الإجمالي في سيكشن الدفع لما الإجمالي يتغير
            const origRecalcTotal = recalcTotal;
            recalcTotal = function() {
                origRecalcTotal();
                const toggle = document.getElementById('partial_payment_toggle');
                if (toggle && toggle.checked) {
                    updateRemaining();
                } else {
                    const totalText = document.getElementById('grand-total-cell').textContent;
                    const total = parseFloat(totalText.replace(/[^\d.]/g, '')) || 0;
                    const input = document.getElementById('amount_paid_input');
                    if (input) input.value = total.toFixed(2);
                }
            };

            document.getElementById('visitForm').addEventListener('submit', function(e) {
                const toggle = document.getElementById('partial_payment_toggle');
                if (toggle && !toggle.checked) {
                    const totalText = document.getElementById('grand-total-cell').textContent;
                    const total = parseFloat(totalText.replace(/[^\d.]/g, '')) || 0;
                    const input = document.getElementById('amount_paid_input');
                    if (input) input.value = total.toFixed(2);
                }
            });

        </script>
    @endpush

@endsection
