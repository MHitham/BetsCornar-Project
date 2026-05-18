@extends('layouts.app')
@section('title', 'فواتير الشراء')
@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 fw-bold">
            <i class="bi bi-cart-check me-2 text-primary"></i> فواتير الشراء
        </h4>
        <a href="{{ route('purchases.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> فاتورة شراء جديدة
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>رقم الفاتورة</th>
                        <th>المورد</th>
                        <th>تاريخ الشراء</th>
                        <th>إجمالي التكلفة</th>
                        <th>المدفوع</th>
                        <th>المتبقي</th>
                        <th>حالة الدفع</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        @php
                            $remaining = max(0, (float)$order->total_cost - (float)$order->amount_paid);
                            $status    = $order->payment_status;
                        @endphp
                        <tr>
                            <td class="fw-semibold">{{ $order->order_number }}</td>
                            <td>{{ $order->supplier?->name ?? '—' }}</td>
                            <td>{{ $order->purchased_at->format('Y/m/d') }}</td>
                            <td>{{ number_format($order->total_cost, 2) }} ج</td>
                            <td>{{ number_format($order->amount_paid, 2) }} ج</td>
                            <td>
                                @if($remaining > 0)
                                    <span class="text-danger fw-semibold">{{ number_format($remaining, 2) }} ج</span>
                                @else
                                    <span class="text-success">—</span>
                                @endif
                            </td>
                            <td>
                                @if($status === 'paid')
                                    <span class="badge bg-success">مسدد</span>
                                @elseif($status === 'partial')
                                    <span class="badge bg-warning text-dark">جزئي</span>
                                @else
                                    <span class="badge bg-danger">غير مسدد</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('purchases.show', $order) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                لا توجد فواتير شراء بعد
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $orders->links() }}</div>

</div>
@endsection
