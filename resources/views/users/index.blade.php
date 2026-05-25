@extends('layouts.app')

@section('title', 'إدارة المستخدمين')
@section('page-title', 'إدارة المستخدمين')

@section('content')

    
    <div class="d-flex align-items-center justify-content-between mb-3">
        <span class="fw-bold">
            <i class="bi bi-person-badge-fill text-primary me-1"></i>
            {{ $users->total() }} مستخدم
        </span>
        <a href="{{ route('users.create') }}" class="btn btn-primary">
            <i class="bi bi-person-plus-fill me-1"></i>إضافة موظف
        </a>
    </div>

    
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الاسم</th>
                        <th>البريد الإلكتروني</th>
                        <th>الدور</th>
                        <th>تاريخ الإنشاء</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($users->isNotEmpty())
                        @foreach ($users as $user)
                            <tr>
                                
                                <td class="text-muted small">
                                    {{ $users->firstItem() + $loop->index }}
                                </td>

                                
                                <td class="fw-semibold">
                                    {{ $user->name }}
                                    @if ($user->id === auth()->id())
                                        <span class="badge bg-secondary ms-1">أنت</span>
                                    @endif
                                </td>

                                
                                <td class="text-muted">{{ $user->email }}</td>

                                
                                <td>
                                    @php
                                        $role = $user->roles->first()?->name;
                                    @endphp
                                    @if ($role === 'admin')
                                        <span class="badge bg-danger">
                                            <i class="bi bi-shield-fill-check me-1"></i>مدير
                                        </span>
                                    @elseif ($role === 'employee')
                                        <span class="badge bg-info text-dark">
                                            <i class="bi bi-person-fill me-1"></i>موظف
                                        </span>
                                    @else
                                        <span class="badge bg-secondary">—</span>
                                    @endif
                                </td>

                                
                                <td class="text-muted small">
                                    {{ $user->created_at->format('d/m/Y') }}
                                </td>

                                
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        
                                        <a href="{{ route('users.edit', $user) }}"
                                           class="btn btn-sm btn-outline-primary"
                                           title="تعديل">
                                            <i class="bi bi-pencil-fill"></i>
                                        </a>

                                        
                                        @if ($user->id !== auth()->id())
                                            <form method="POST"
                                                  action="{{ route('users.destroy', $user) }}"
                                                  onsubmit="return confirm('هل أنت متأكد من حذف هذا المستخدم؟')"
                                                  class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="btn btn-sm btn-outline-danger"
                                                        title="حذف">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </form>
                                        @else
                                            
                                            <button class="btn btn-sm btn-outline-danger"
                                                    disabled
                                                    title="لا يمكنك حذف حسابك الشخصي">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="bi bi-people text-muted"></i>
                                    <p>لا يوجد مستخدمون حتى الآن.</p>
                                </div>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    
    <div class="mt-4">{{ $users->links() }}</div>

@endsection
