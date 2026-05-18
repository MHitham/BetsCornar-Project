@extends('layouts.app')

@section('title', 'تعديل بيانات المورد')

@section('content')
<div class="container-fluid">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="bi bi-arrow-right"></i>
        </a>
        <h4 class="mb-0 fw-bold">تعديل بيانات المورد</h4>
    </div>

    <div class="card shadow-sm" style="max-width: 600px;">
        <div class="card-body">
            <form action="{{ route('suppliers.update', $supplier) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label fw-semibold">اسم المورد <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $supplier->name) }}" placeholder="مثال: شركة الدواء المصرية" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">التليفون</label>
                    <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                           value="{{ old('phone', $supplier->phone) }}" placeholder="01xxxxxxxxx">
                    @error('phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">العنوان</label>
                    <input type="text" name="address" class="form-control"
                           value="{{ old('address', $supplier->address) }}" placeholder="عنوان المورد">
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="3"
                              placeholder="أي ملاحظات إضافية">{{ old('notes', $supplier->notes) }}</textarea>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> حفظ التعديلات
                    </button>
                    <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary">إلغاء</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
