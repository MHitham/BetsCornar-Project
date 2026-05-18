@extends('layouts.app')

@section('title', 'الموردين')

@section('content')
<div class="container-fluid">

    {{-- رأس الصفحة --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 fw-bold">
            <i class="bi bi-truck me-2 text-primary"></i> الموردين
        </h4>
        <a href="{{ route('suppliers.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> إضافة مورد
        </a>
    </div>

    {{-- رسائل النجاح --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- جدول الموردين --}}
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>المورد</th>
                        <th>التليفون</th>
                        <th>إجمالي المشتريات</th>
                        <th>المدفوع</th>
                        <th>المتبقي عليك</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($suppliers as $supplier)
                        @php
                            $totalCost  = (float) ($supplier->purchase_orders_sum_total_cost ?? 0);
                            $totalPaid  = (float) ($supplier->purchase_orders_sum_amount_paid ?? 0);
                            $balance    = max(0, $totalCost - $totalPaid);
                        @endphp
                        <tr class="{{ $supplier->is_active ? '' : 'text-muted' }}">
                            <td class="fw-semibold">{{ $supplier->name }}</td>
                            <td>{{ $supplier->phone ?? '—' }}</td>
                            <td>{{ number_format($totalCost, 2) }} ج</td>
                            <td>{{ number_format($totalPaid, 2) }} ج</td>
                            <td>
                                @if($balance > 0)
                                    <span class="badge bg-danger fs-6">
                                        {{ number_format($balance, 2) }} ج
                                    </span>
                                @else
                                    <span class="badge bg-success">مسدد</span>
                                @endif
                            </td>
                            <td>
                                @if($supplier->is_active)
                                    <span class="badge bg-success">نشط</span>
                                @else
                                    <span class="badge bg-secondary">معطل</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('suppliers.edit', $supplier) }}"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="{{ route('suppliers.toggle-active', $supplier) }}"
                                          method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit"
                                                class="btn btn-sm {{ $supplier->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}">
                                            <i class="bi bi-{{ $supplier->is_active ? 'pause' : 'play' }}-fill"></i>
                                        </button>
                                    </form>
                                    <form action="{{ route('suppliers.destroy', $supplier) }}"
                                          method="POST" class="d-inline"
                                          onsubmit="return confirm('هل أنت متأكد من حذف هذا المورد؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                لا يوجد موردين بعد
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- pagination --}}
    <div class="mt-3">
        {{ $suppliers->links() }}
    </div>

</div>
@endsection

