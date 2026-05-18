@extends('layouts.app')

@section('title', 'الإعدادات - النسخ الاحتياطية')
@section('page-title', 'النسخ الاحتياطية')

@section('content')

{{-- رأس الصفحة مع أزرار الإجراءات --}}
<div class="d-flex align-items-start justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h5 class="fw-bold mb-1">النسخ الاحتياطية</h5>
        <p class="text-muted mb-0 small">إدارة نسخ قاعدة البيانات</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        {{-- زر فتح مجلد النسخ --}}
        <button type="button" class="btn btn-outline-primary" onclick="openBackupFolder()">
            <i class="bi bi-folder2-open me-1"></i>
            فتح مجلد النسخ
        </button>

        {{-- زر إنشاء نسخة احتياطية الآن --}}
        <form method="POST" action="{{ route('backup.store') }}"
              onsubmit="return confirm('هل تريد إنشاء نسخة احتياطية الآن؟')">
            @csrf
            <button type="submit" class="btn btn-success">
                <i class="bi bi-cloud-arrow-down-fill me-1"></i>
                نسخ احتياطي الآن
            </button>
        </form>
    </div>
</div>

{{-- تنبيه معلوماتي --}}
<div class="alert alert-info d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-info-circle-fill fs-5"></i>
    <span>
        يتم إنشاء نسخة احتياطية تلقائياً كل يوم الساعة 2 صباحاً &mdash; أقصى عدد للنسخ: <strong>15</strong>
    </span>
</div>

{{-- جدول النسخ الاحتياطية --}}
<div class="card">
    <div class="card-header fw-bold">
        <i class="bi bi-archive-fill text-success me-1"></i>
        النسخ المحفوظة
    </div>
    <div class="card-body p-0">
        @if(count($backups) > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px">#</th>
                            <th>اسم الملف</th>
                            <th>تاريخ الإنشاء</th>
                            <th>الحجم (MB)</th>
                            <th class="text-center" style="width:130px">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($backups as $index => $backup)
                            <tr>
                                <td class="text-muted small">{{ $index + 1 }}</td>
                                <td class="fw-semibold font-monospace small">{{ $backup['filename'] }}</td>
                                <td>{{ $backup['created_at']->format('Y-m-d H:i') }}</td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $backup['size_mb'] }} MB</span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        {{-- زر الاستعادة مع تأكيد --}}
                                        <form method="POST"
                                              action="{{ route('backup.restore', $backup['filename']) }}"
                                              onsubmit="return confirm('⚠️ تحذير: سيتم استبدال قاعدة البيانات الحالية بهذه النسخة.\n\nسيتم حفظ نسخة احتياطية تلقائية من الحالة الراهنة أولاً.\n\nهل تريد المتابعة؟')">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="استعادة">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>

                                        {{-- زر الحذف مع تأكيد --}}
                                        <form method="POST"
                                              action="{{ route('backup.destroy', $backup['filename']) }}"
                                              onsubmit="return confirm('هل أنت متأكد من حذف هذه النسخة؟ لا يمكن التراجع.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="حذف">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-5 text-muted">
                <i class="bi bi-archive fs-1 d-block mb-2"></i>
                لا توجد نسخ احتياطية بعد
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
    // فتح مجلد النسخ الاحتياطية عبر NativePHP Shell
    function openBackupFolder() {
        fetch('{{ route('backup.open-folder') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        });
    }
</script>
@endpush

@endsection
