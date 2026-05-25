@extends('layouts.app')

@section('title', 'تعديل بيانات المستخدم')
@section('page-title', 'تعديل بيانات المستخدم')

@section('content')

    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header fw-bold">
                    <i class="bi bi-pencil-fill me-1 text-primary"></i> تعديل: {{ $user->name }}
                </div>
                <div class="card-body">
                    
                    <form method="POST" action="{{ route('users.update', $user) }}">
                        @csrf
                        @method('PUT')

                        
                        <div class="mb-3">
                            <label for="name" class="form-label">الاسم <span class="text-danger">*</span></label>
                            <input type="text"
                                   id="name"
                                   name="name"
                                   class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', $user->name) }}"
                                   placeholder="اسم الموظف"
                                   required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        
                        <div class="mb-3">
                            <label for="email" class="form-label">البريد الإلكتروني <span class="text-danger">*</span></label>
                            <input type="email"
                                   id="email"
                                   name="email"
                                   class="form-control @error('email') is-invalid @enderror"
                                   value="{{ old('email', $user->email) }}"
                                   placeholder="example@betscornar.com"
                                   required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        
                        <div class="mb-3">
                            <label for="password" class="form-label">
                                كلمة المرور
                                <span class="text-muted small">(اتركها فارغة للإبقاء على الحالية)</span>
                            </label>
                            <input type="password"
                                   id="password"
                                   name="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   placeholder="أدخل كلمة مرور جديدة فقط إذا أردت تغييرها">
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        
                        <div class="mb-4">
                            <label for="role" class="form-label">الدور <span class="text-danger">*</span></label>
                            <select id="role"
                                    name="role"
                                    class="form-select @error('role') is-invalid @enderror"
                                    required>
                                <option value="">-- اختر الدور --</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role }}"
                                        @selected(old('role', $userRole) === $role)>
                                        {{ $role === 'admin' ? 'مدير' : 'موظف' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('role')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> حفظ التعديلات
                            </button>
                            <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-right me-1"></i> رجوع
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
