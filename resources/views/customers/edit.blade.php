@extends('layouts.app')

@section('title', __('customers.edit.title'))
@section('page-title', __('customers.edit.title'))

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                {{-- نموذج تعديل بيانات العميل --}}
                <form method="POST" action="{{ route('customers.update', $customer) }}">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label fw-semibold">
                                {{ __('customers.fields.name') }} <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                id="name" name="name" 
                                value="{{ old('name', $customer->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="phone" class="form-label fw-semibold">
                                {{ __('customers.fields.phone') }} <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control @error('phone') is-invalid @enderror" 
                                id="phone" name="phone" 
                                value="{{ old('phone', $customer->phone) }}" required>
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="animal_type" class="form-label fw-semibold">
                                {{ __('customers.fields.animal_type') }} <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control @error('animal_type') is-invalid @enderror" 
                                id="animal_type" name="animal_type" 
                                value="{{ old('animal_type', $customer->animal_type) }}" required>
                            @error('animal_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="address" class="form-label fw-semibold">
                                {{ __('customers.fields.address') }}
                            </label>
                            <input type="text" class="form-control @error('address') is-invalid @enderror" 
                                id="address" name="address" 
                                value="{{ old('address', $customer->address) }}">
                            @error('address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label for="notes" class="form-label fw-semibold">
                                {{ __('customers.fields.notes') }}
                            </label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" 
                                id="notes" name="notes" rows="3">{{ old('notes', $customer->notes) }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 mt-4">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg me-1"></i>{{ __('customers.actions.save') }}
                                </button>
                                <a href="{{ route('customers.show', $customer) }}" class="btn btn-outline-secondary">
                                    {{ __('customers.actions.cancel') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
