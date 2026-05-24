@extends('layouts.app')

@section('title', __('messages.dashboard'))
@section('page-title', __('messages.dashboard'))

@section('content')

    {{-- Stats Row --}}
    <div class="row g-3 mb-4">
        {{-- Today Visits --}}
        <div class="col-6 col-md-3">
            <div class="stat-card bg-white h-100 border">
                <div class="stat-icon" style="background:#dbeafe; color:#1d4ed8;">🏥</div>
                <div>
                    <div class="stat-value text-primary">{{ $todayVisits }}</div>
                    <div class="stat-label text-secondary">{{ __('messages.today_visits') }}</div>
                </div>
            </div>
        </div>
        {{-- Today Revenue --}}
        <div class="col-6 col-md-3">
            <div class="stat-card bg-white h-100 border">
                <div class="stat-icon" style="background:#d1fae5; color:#065f46;">💰</div>
                <div>
                    <div class="stat-value" style="color:#065f46;">{{ number_format($todayRevenue, 0) }}</div>
                    <div class="stat-label text-secondary">{{ __('messages.today_revenue') }}
                        ({{ __('messages.currency') }})</div>
                </div>
            </div>
        </div>
        {{-- Total Products --}}
        <div class="col-6 col-md-3">
            <div class="stat-card bg-white h-100 border">
                <div class="stat-icon" style="background:#fef3c7; color:#92400e;">📦</div>
                <div>
                    <div class="stat-value" style="color:#92400e;">{{ $totalProducts }}</div>
                    <div class="stat-label text-secondary">{{ __('messages.total_products') }}</div>
                </div>
            </div>
        </div>
        {{-- Total Vaccinations --}}
        <div class="col-6 col-md-3">
            <div class="stat-card bg-white h-100 border">
                <div class="stat-icon" style="background:#ede9fe; color:#6d28d9;">💉</div>
                <div>
                    <div class="stat-value" style="color:#6d28d9;">{{ number_format($totalVaccinations, 0) }}</div>
                    <div class="stat-label text-secondary">{{ __('messages.total_vaccinations') }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="bi bi-lightning-charge-fill text-warning"></i>
                    <span class="fw-bold">{{ __('messages.quick_actions') }}</span>
                </div>
                <div class="card-body d-flex gap-3 flex-wrap">
                    <a href="{{ route('customers.create') }}" class="btn btn-primary">
                        <i class="bi bi-person-plus-fill me-1"></i> زيارة عميل جديدة
                    </a>
                    <a href="{{ route('invoices.create') }}" class="btn btn-success">
                        <i class="bi bi-lightning-fill me-1"></i> بيع سريع
                    </a>
                    <a href="{{ route('products.create') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-box-seam me-1"></i> إضافة منتج
                    </a>
                    <a href="{{ route('vaccine-batches.create') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-capsule-pill me-1"></i> إضافة دُفعة تطعيمات
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Alerts Row --}}
    <div class="row g-3 mb-4">
        {{-- Low Stock --}}
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="fw-bold"><i
                            class="bi bi-exclamation-triangle-fill text-warning me-1"></i>{{ __('messages.low_stock_items') }}</span>
                    <span class="badge bg-warning text-dark">{{ $lowStockProducts->count() }}</span>
                </div>
                <div class="card-body p-0" style="max-height:450px;overflow-y:auto;">
                    @if($lowStockProducts->isNotEmpty())
                        @foreach($lowStockProducts as $product)
                            <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom gap-2">
                                <span class="fw-semibold">{{ $product->name }}</span>
                                <span
                                    class="badge {{ $product->stock_status === 'out_of_stock' ? 'bg-danger text-white' : 'bg-warning text-dark' }}">
                                    {{ $product->stock_status === 'out_of_stock' ? 'نفذ المخزون' : 'منخفض' }}
                                    — {{ number_format($product->quantity) }}
                                </span>
                            </div>
                        @endforeach
                    @else
                        <div class="empty-state">
                            <i class="bi bi-check-circle text-success"></i>
                            <p>{{ __('messages.no_low_stock') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Expiry Alerts --}}
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="fw-bold"><i class="bi bi-clock-history text-danger me-1"></i>تنبيهات انتهاء صلاحية
                       التطعيمات</span>
                    <span
                        class="badge bg-danger text-white">{{ $expiredBatches->count() + $expiringSoonBatches->count() }}</span>
                </div>
                <div class="card-body p-0" style="max-height:450px;overflow-y:auto;">
                    @foreach ($expiredBatches as $batch)
                        <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom gap-2">
                            <span class="fw-semibold">{{ $batch->product->name }}</span>
                            <span class="badge bg-danger text-white">🚨 منتهي —
                                {{ $batch->expiry_date->format('Y-m-d') }}</span>
                        </div>
                    @endforeach
                    @foreach ($expiringSoonBatches as $batch)
                        <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom gap-2">
                            <span class="fw-semibold">{{ $batch->product->name }}</span>
                            <span class="badge bg-warning text-dark">⚠️ قريب —
                                {{ $batch->expiry_date->format('Y-m-d') }}</span>
                        </div>
                    @endforeach
                    @if ($expiredBatches->isEmpty() && $expiringSoonBatches->isEmpty())
                        <div class="empty-state">
                            <i class="bi bi-shield-check text-success"></i>
                            <p>{{ __('messages.no_expiry_alerts') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Upcoming Vaccinations --}}
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span class="fw-bold"><i
                    class="bi bi-calendar-heart-fill text-primary me-1"></i>{{ __('messages.upcoming_vaccinations') }}
                (خلال 3 أيام)</span>
            <span class="badge bg-primary text-white">{{ $upcomingVaccinations->count() }}</span>
        </div>
        <div class="card-body p-0">
            @if($upcomingVaccinations->isNotEmpty())
                @foreach($upcomingVaccinations as $vacc)
                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom gap-2">
                        <div>
                            <span class="fw-semibold">{{ $vacc->customer->name }}</span>
                            <span class="text-muted small me-2">{{ $vacc->customer->phone }}</span>
                            <span class="badge bg-light text-dark border">{{ $vacc->product->name }}</span>
                        </div>
                        <span class="badge bg-primary text-white">{{ $vacc->next_dose_date->format('Y-m-d') }}</span>
                    </div>
                @endforeach
            @else
                <div class="empty-state">
                    <i class="bi bi-calendar-check text-success"></i>
                    <p>{{ __('messages.no_upcoming') }}</p>
                </div>
            @endif
        </div>
    </div>

@endsection
