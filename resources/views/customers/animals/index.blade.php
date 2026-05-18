@extends('layouts.app')

@section('title', 'حيوانات العميل: ' . $customer->name)
@section('page-title', 'حيوانات العميل: ' . $customer->name)

@section('content')
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>حيوانات العميل</h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAnimalModal">
            <i class="bi bi-plus-lg me-1"></i> إضافة حيوان جديد
        </button>
    </div>
    <div class="card-body">
        @if($animals->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>الاسم</th>
                        <th>النوع الفصيلة</th>
                        <th>العمر / الجنس</th>
                        <th>الوزن</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($animals as $animal)
                    <tr>
                        <td class="fw-bold">{{ $animal->name }}</td>
                        <td>{{ $animal->species }} - {{ $animal->breed ?? 'غير محدد' }}</td>
                        <td>{{ $animal->age ?? 'غير محدد' }} / {{ $animal->gender === 'male' ? 'ذكر' : ($animal->gender === 'female' ? 'أنثى' : 'غير محدد') }}</td>
                        <td>{{ $animal->weight ? $animal->weight . ' كجم' : 'غير محدد' }}</td>
                        <td class="text-center">
                            <a href="{{ route('animals.show', $animal) }}" class="btn btn-sm btn-info" title="عرض الملف">
                                <i class="bi bi-eye"></i>
                            </a>
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editAnimalModal{{ $animal->id }}" title="تعديل">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form action="{{ route('animals.destroy', $animal) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger" title="حذف">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>

                    <!-- Edit Modal -->
                    <div class="modal fade" id="editAnimalModal{{ $animal->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('animals.update', $animal) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">تعديل حيوان: {{ $animal->name }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body row g-3 text-start" dir="rtl">
                                        <div class="col-md-6 text-end">
                                            <label class="form-label">الاسم <span class="text-danger">*</span></label>
                                            <input type="text" name="name" class="form-control" value="{{ $animal->name }}" required>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <label class="form-label">النوع <span class="text-danger">*</span></label>
                                            <input type="text" name="species" class="form-control" value="{{ $animal->species }}" required>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <label class="form-label">الفصيلة</label>
                                            <input type="text" name="breed" class="form-control" value="{{ $animal->breed }}">
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <label class="form-label">العمر</label>
                                            <input type="text" name="age" class="form-control" value="{{ $animal->age }}">
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <label class="form-label">الجنس</label>
                                            <select name="gender" class="form-select">
                                                <option value="">غير محدد</option>
                                                <option value="male" {{ $animal->gender == 'male' ? 'selected' : '' }}>ذكر</option>
                                                <option value="female" {{ $animal->gender == 'female' ? 'selected' : '' }}>أنثى</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <label class="form-label">الوزن (كجم)</label>
                                            <input type="number" step="0.01" name="weight" class="form-control" value="{{ $animal->weight }}">
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <label class="form-label">اللون</label>
                                            <input type="text" name="color" class="form-control" value="{{ $animal->color }}">
                                        </div>
                                        <div class="col-12 text-end">
                                            <label class="form-label">ملاحظات</label>
                                            <textarea name="notes" class="form-control" rows="2">{{ $animal->notes }}</textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                        <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            {{ $animals->links() }}
        </div>
        @else
        <div class="alert alert-info border-0 d-flex align-items-center mb-0">
            <i class="bi bi-info-circle-fill me-2 fs-5"></i>
            <div>لا يوجد حيوانات مسجلة لهذا العميل. قم بإضافة حيوان جديد من الزر أعلاه.</div>
        </div>
        @endif
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addAnimalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('customers.animals.store', $customer) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة حيوان جديد للعميل {{ $customer->name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body row g-3 text-start" dir="rtl">
                    <div class="col-md-6 text-end">
                        <label class="form-label">الاسم <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                    </div>
                    <div class="col-md-6 text-end">
                        <label class="form-label">النوع <span class="text-danger">*</span></label>
                        <input type="text" name="species" class="form-control" value="{{ old('species', $customer->animal_type ?? '') }}" required>
                    </div>
                    <div class="col-md-6 text-end">
                        <label class="form-label">الفصيلة</label>
                        <input type="text" name="breed" class="form-control" value="{{ old('breed') }}">
                    </div>
                    <div class="col-md-6 text-end">
                        <label class="form-label">العمر</label>
                        <input type="text" name="age" class="form-control" value="{{ old('age') }}">
                    </div>
                    <div class="col-md-6 text-end">
                        <label class="form-label">الجنس</label>
                        <select name="gender" class="form-select">
                            <option value="">غير محدد</option>
                            <option value="male">ذكر</option>
                            <option value="female">أنثى</option>
                        </select>
                    </div>
                    <div class="col-md-6 text-end">
                        <label class="form-label">الوزن (كجم)</label>
                        <input type="number" step="0.01" name="weight" class="form-control" value="{{ old('weight') }}">
                    </div>
                    <div class="col-md-6 text-end">
                        <label class="form-label">اللون</label>
                        <input type="text" name="color" class="form-control" value="{{ old('color') }}">
                    </div>
                    <div class="col-12 text-end">
                        <label class="form-label">ملاحظات</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">إضافة</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="mt-3">
    <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-right me-1"></i>عودة لقائمة العملاء
    </a>
</div>
@endsection
