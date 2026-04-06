<form method="POST" action="{{ $action }}">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-box-seam-fill text-primary me-1"></i>
            <span class="fw-bold">{{ $submitLabel }}</span>
        </div>
        <div class="card-body row g-3">

            {{-- Name --}}
            <div class="col-md-6">
                <label class="form-label" for="name">{{ __('products.fields.name') }} <span
                        class="text-danger">*</span></label>
                <input type="text" id="name" name="name"
                    class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $product->name) }}"
                    placeholder="أدخل اسم المنتج" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            {{-- Type --}}
            <div class="col-md-6">
                <label class="form-label" for="type">{{ __('products.fields.type') }} <span
                        class="text-danger">*</span></label>
                <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                    @foreach (['product' => 'منتج', 'service' => 'خدمة', 'vaccination' => 'تطعيم'] as $val => $label)
                        <option value="{{ $val }}" @selected(old('type', $product->type) === $val)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('type')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            {{-- Price --}}
            <div class="col-md-4">
                <label class="form-label" for="price">{{ __('products.fields.price') }} <span
                        class="text-danger">*</span></label>
                <div class="input-group">
                    {{-- تم التعديل: تحديث القيم الافتراضية للحقل الرقمي --}}
                    <input type="number" step="0.01" min="0" id="price" name="price"
                        class="form-control @error('price') is-invalid @enderror"
                        value="{{ old('price', $product->price) }}" placeholder="0" required>
                    <span class="input-group-text">{{ __('messages.currency') }}</span>
                </div>
                @error('price')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            {{-- الكمية  --}}
            <div class="col-md-4 stock-field quantity-field">
                <label class="form-label" for="quantity">{{ __('products.fields.quantity') }} <span
                        class="text-danger">*</span></label>
                <input type="number" step="1" min="0" id="quantity" name="quantity"
                    class="form-control @error('quantity') is-invalid @enderror"
                    value="{{ old('quantity', $product->quantity) }}" placeholder="0" required>
                @error('quantity')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            {{-- حد المخزون المنخفض --}}
            <div class="col-md-4 stock-field threshold-field">
                <label class="form-label"
                    for="low_stock_threshold">{{ __('products.fields.low_stock_threshold') }}</label>
                <input type="number" step="1" min="0" id="low_stock_threshold" name="low_stock_threshold"
                    class="form-control @error('low_stock_threshold') is-invalid @enderror"
                    value="{{ old('low_stock_threshold', $product->low_stock_threshold) }}" placeholder="0">
                @error('low_stock_threshold')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            {{-- هل المنتج نشط --}}
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active"
                        value="1" @checked(old('is_active', $product->is_active ?? true))>
                    <label class="form-check-label fw-semibold" for="is_active">
                        المنتج نشط
                    </label>
                </div>
            </div>

            {{-- Service/Vaccination note --}}
            <div class="col-12" id="service-note" style="display:none;">
                <div class="alert alert-info py-2 mb-0">
                    <i class="bi bi-info-circle-fill me-1"></i>
                    الخدمات لا تتتبع المخزون. كمية التطعيمات تُحسب تلقائيًا من الدفعات.
                </div>
            </div>

            {{-- Notes --}}
            <div class="col-12">
                <label class="form-label" for="notes">{{ __('products.fields.notes') }}</label>
                <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="ملاحظات إضافية (اختياري)">{{ old('notes', $product->notes) }}</textarea>
            </div>

        </div>
    </div>

    <div class="d-flex gap-3">
        <button type="submit" class="btn btn-primary btn-lg px-5">
            <i class="bi bi-save-fill me-1"></i>{{ $submitLabel }}
        </button>
        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary btn-lg">
            <i class="bi bi-arrow-right me-1"></i>{{ __('messages.back') }}
        </a>
    </div>
</form>

@push('scripts')
    <script>
        (function() {
            const typeSelect = document.getElementById('type');
            const quantity = document.getElementById('quantity');
            const threshold = document.getElementById('low_stock_threshold');
            const stockFields = document.querySelectorAll('.stock-field');
            const quantityField = document.querySelector('.quantity-field');
            const serviceNote = document.getElementById('service-note');

            function handleTypeChange() {
                const isService = typeSelect.value === 'service';
                const isVaccination = typeSelect.value === 'vaccination';

                stockFields.forEach(f => f.style.display = isService ? 'none' : '');
                if (quantityField) quantityField.style.display = isVaccination ? 'none' : '';
                serviceNote.style.display = (isService || isVaccination) ? '' : 'none';

                if (isService || isVaccination) quantity.value = '0';
                quantity.readOnly = isVaccination;

                if (isService) threshold.value = '0';
            }

            typeSelect.addEventListener('change', handleTypeChange);
            handleTypeChange();
        })();
    </script>
@endpush
