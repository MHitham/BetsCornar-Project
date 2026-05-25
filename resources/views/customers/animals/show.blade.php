@extends('layouts.app')

@section('title', 'الملف الطبي: ' . $animal->name)
@section('page-title', 'الملف الطبي: ' . $animal->name)

@section('content')
<div class="row g-4">
    
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-info-circle-fill text-primary me-2"></i>بيانات الحيوان</span>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editAnimalModal">
                    <i class="bi bi-pencil"></i> تعديل
                </button>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between text-start" dir="rtl">
                        <span class="text-muted text-end">المالك:</span>
                        <a href="{{ route('customers.show', $animal->customer) }}" class="fw-bold text-decoration-none text-start">
                            {{ $animal->customer->name }}
                        </a>
                    </li>
                    <li class="list-group-item d-flex justify-content-between text-start" dir="rtl">
                        <span class="text-muted text-end">الفصيلة والنوع:</span>
                        <span class="fw-bold text-start">{{ $animal->species }} / {{ $animal->breed ?? '-' }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between text-start" dir="rtl">
                        <span class="text-muted text-end">العمر / الجنس:</span>
                        <span class="fw-bold text-start">
                            {{ $animal->age ?? '-' }} / 
                            @if($animal->gender === 'male') ذكر @elseif($animal->gender === 'female') أنثى @else - @endif
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between text-start" dir="rtl">
                        <span class="text-muted text-end">اللون / الوزن:</span>
                        <span class="fw-bold text-start">{{ $animal->color ?? '-' }} / {{ $animal->weight ? $animal->weight . ' كجم' : '-' }}</span>
                    </li>
                    @if($animal->notes)
                    <li class="list-group-item">
                        <div class="text-muted mb-1 text-end">ملاحظات عامة:</div>
                        <div class="small fw-bold text-end">{{ $animal->notes }}</div>
                    </li>
                    @endif
                </ul>
            </div>
        </div>
    </div>

    
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <span class="fw-bold"><i class="bi bi-shield-check text-success me-2"></i>سجل التطعيمات</span>
            </div>
            <div class="card-body">
                @if($animal->vaccinations->count() > 0)
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>التطعيم</th>
                                <th>تاريخ التطعيم</th>
                                <th>الجرعة القادمة</th>
                                <th>طبيب/فاتورة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($animal->vaccinations as $vacc)
                            <tr>
                                <td class="fw-bold">{{ $vacc->product->name ?? 'منتج محذوف' }}</td>
                                <td>{{ $vacc->vaccination_date ? \Carbon\Carbon::parse($vacc->vaccination_date)->format('Y-m-d') : '-' }}</td>
                                <td>
                                    @if($vacc->next_dose_date)
                                        @if(\Carbon\Carbon::parse($vacc->next_dose_date)->isPast() && !$vacc->is_completed)
                                            <span class="text-danger fw-bold"><i class="bi bi-exclamation-circle me-1"></i>{{ \Carbon\Carbon::parse($vacc->next_dose_date)->format('Y-m-d') }}</span>
                                        @else
                                            <span class="text-success"><i class="bi bi-calendar-check me-1"></i>{{ \Carbon\Carbon::parse($vacc->next_dose_date)->format('Y-m-d') }}</span>
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if($vacc->invoice_id)
                                    <a href="{{ route('invoices.show', $vacc->invoice_id) }}" class="btn btn-sm btn-link text-decoration-none">
                                        عرض الفاتورة
                                    </a>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="alert alert-secondary mb-0 border-0">لا يوجد سجل تطعيمات مسجل حتى الآن.</div>
                @endif
            </div>
        </div>
    </div>

    
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <span class="fw-bold"><i class="bi bi-journal-medical text-danger me-2"></i>سجل التشخيصات</span>
            </div>
            <div class="card-body">
                @php
                    $diagnoses = $animal->invoices()->whereNotNull('diagnosis')->where('diagnosis', '!=', '')->latest()->get();
                @endphp
                @if($diagnoses->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>تاريخ الزيارة</th>
                                <th>التشخيص</th>
                                <th>الطبيب / الفاتورة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($diagnoses as $diagInvoice)
                            <tr>
                                <td class="fw-bold" style="white-space: nowrap;">{{ $diagInvoice->created_at->format('Y-m-d') }}</td>
                                <td>{!! nl2br(e($diagInvoice->diagnosis)) !!}</td>
                                <td style="white-space: nowrap;">
                                    <a href="{{ route('invoices.show', $diagInvoice->id) }}" class="btn btn-sm btn-outline-info">
                                        عرض التفاصيل
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="alert alert-secondary mb-0 border-0">لا يوجد تشخيصات طبية مسجلة في سجل الزيارات.</div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="mt-4 mb-5">
    <a href="{{ route('customers.animals.index', $animal->customer_id) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-right me-1"></i>عودة لقائمة الحيوانات
    </a>
</div>


<div class="modal fade" id="editAnimalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('animals.update', $animal) }}" method="POST">
            @csrf
            @method('PATCH')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تعديل بيانات الحيوان</h5>
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
@endsection
