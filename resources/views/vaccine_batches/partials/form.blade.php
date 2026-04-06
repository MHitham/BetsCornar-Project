<form method="POST" action="{{ $action }}">
    @csrf
    @if($method !== 'POST') @method($method) @endif

    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-capsule-pill text-primary me-1"></i>
            <span class="fw-bold">{{ $submitLabel }}</span>
        </div>
        <div class="card-body row g-3">

            {{-- Vaccine Product --}}
            <div class="col-md-6">
                <label class="form-label" for="product_id">{{ __('vaccine_batches.fields.product') }} <span class="text-danger">*</span></label>
                <select id="product_id" name="product_id"
                        class="form-select @error('product_id') is-invalid @enderror" required>
                    <option value="">اختر لقاحًا...</option>
                    @foreach($vaccines as $vaccine)
                        <option value="{{ $vaccine->id }}"
                            @selected((int) old('product_id', $batch->product_id) === $vaccine->id)>
                            {{ $vaccine->name }}
                        </option>
                    @endforeach
                </select>
                @error('product_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            {{-- Batch Code --}}
            <div class="col-md-6">
                <label class="form-label" for="batch_code">{{ __('vaccine_batches.fields.batch_code') }}</label>
                <input type="text" id="batch_code" name="batch_code"
                       class="form-control @error('batch_code') is-invalid @enderror"
                       value="{{ old('batch_code', $batch->batch_code) }}"
                       placeholder="كود الدُفعة (اختياري)">
                @error('batch_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            {{-- Received Date --}}
            <div class="col-md-6">
                <label class="form-label" for="received_date">{{ __('vaccine_batches.fields.received_date') }} <span class="text-danger">*</span></label>
                <input type="date" id="received_date" name="received_date"
                       class="form-control @error('received_date') is-invalid @enderror"
                       value="{{ old('received_date', optional($batch->received_date)->format('Y-m-d')) }}" required>
                @error('received_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            {{-- Expiry Date --}}
            <div class="col-md-6">
                <label class="form-label" for="expiry_date">{{ __('vaccine_batches.fields.expiry_date') }} <span class="text-danger">*</span></label>
                <input type="date" id="expiry_date" name="expiry_date"
                       class="form-control @error('expiry_date') is-invalid @enderror"
                       value="{{ old('expiry_date', optional($batch->expiry_date)->format('Y-m-d')) }}" required>
                @error('expiry_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            {{-- Quantity Received --}}
            <div class="col-md-6">
                <label class="form-label" for="quantity_received">{{ __('vaccine_batches.fields.quantity_received') }} <span class="text-danger">*</span></label>
                {{-- تم التعديل: تحديث القيم الافتراضية للحقل الرقمي --}}
                <input type="number" step="1" min="0" id="quantity_received" name="quantity_received"
                       class="form-control @error('quantity_received') is-invalid @enderror"
                       value="{{ old('quantity_received', $batch->quantity_received) }}"
                       placeholder="0" required>
                @error('quantity_received')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            {{-- Quantity Remaining --}}
            <div class="col-md-6">
                <label class="form-label" for="quantity_remaining">{{ __('vaccine_batches.fields.quantity_remaining') }}</label>
                {{-- تم التعديل: تحديث القيم الافتراضية للحقل الرقمي --}}
                <input type="number" step="1" min="0" id="quantity_remaining" name="quantity_remaining"
                       class="form-control @error('quantity_remaining') is-invalid @enderror"
                       value="{{ old('quantity_remaining', $batch->quantity_remaining) }}"
                       placeholder="0" @required($method !== 'POST')>
                @if($showRemainingHint ?? false)
                    <div class="form-text text-primary">
                        <i class="bi bi-info-circle me-1"></i>{{ __('vaccine_batches.messages.remaining_hint') }}
                    </div>
                @endif
                @error('quantity_remaining')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

        </div>
    </div>

    <div class="d-flex gap-3">
        <button type="submit" class="btn btn-primary btn-lg px-5">
            <i class="bi bi-save-fill me-1"></i>{{ $submitLabel }}
        </button>
        <a href="{{ route('vaccine-batches.index') }}" class="btn btn-outline-secondary btn-lg">
            <i class="bi bi-arrow-right me-1"></i>{{ __('messages.cancel') }}
        </a>
    </div>
</form>

@push('scripts')
<script>
(function () {
    const received  = document.getElementById('quantity_received');
    const remaining = document.getElementById('quantity_remaining');
    const isCreate  = @json($method === 'POST');
    if (!isCreate) return;

    received.addEventListener('input', function () {
        if (remaining.value === '' || parseFloat(remaining.value) === 0) {
            remaining.value = this.value;
        }
    });
})();
</script>
@endpush
