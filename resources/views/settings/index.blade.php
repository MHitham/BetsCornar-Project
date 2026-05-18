@extends('layouts.app')

@section('title', 'إعدادات النظام')
@section('page-title', 'إعدادات النظام')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">

            {{-- رسالة النجاح --}}
            @if (session('success'))
                <div class="alert alert-success app-alert mb-4">
                    <div class="app-alert__row">
                        <span class="app-alert__icon">
                            <i class="bi bi-check-circle-fill"></i>
                        </span>
                        <div class="flex-grow-1">
                            <div class="app-alert__title">{{ session('success') }}</div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- بطاقة إعدادات النظام --}}
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white d-flex align-items-center gap-2">
                    <i class="bi bi-gear-fill"></i>
                    <span class="fw-bold">إعدادات النظام</span>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('settings.update') }}" method="POST">
                        @csrf

                        {{-- حقل اسم العيادة --}}
                        <div class="mb-4">
                            <label for="clinic_name" class="form-label fw-semibold">
                                <i class="bi bi-hospital me-1"></i>
                                اسم العيادة
                            </label>
                            <input type="text"
                                   id="clinic_name"
                                   name="clinic_name"
                                   class="form-control @error('clinic_name') is-invalid @enderror"
                                   value="{{ old('clinic_name', $clinicName) }}"
                                   maxlength="100"
                                   required>
                            @error('clinic_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">هذا الاسم سيظهر في الفواتير والقائمة الجانبية وصفحة الدخول.</div>
                        </div>

                        {{-- زر الحفظ --}}
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check-lg me-1"></i>
                            حفظ الإعدادات
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
