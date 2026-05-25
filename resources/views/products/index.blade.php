@extends('layouts.app')

@section('title', __('products.title'))
@section('page-title', __('products.title'))

@section('content')

    
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('products.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">بحث</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}"
                            placeholder="ابحث بالاسم...">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('products.fields.type') }}</label>
                    <select name="type" class="form-select">
                        <option value="">{{ __('products.filters.all_types') }}</option>
                        @foreach (['product' => 'منتج', 'service' => 'خدمة', 'vaccination' => 'تطعيم'] as $val => $label)
                            <option value="{{ $val }}" @selected(($filters['type'] ?? '') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('products.fields.status') }}</label>
                    <select name="status" class="form-select">
                        <option value="">{{ __('products.statuses.all') }}</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('products.statuses.active') }}</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('products.statuses.inactive') }}
                        </option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="bi bi-search"></i>
                    </button>
                    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    
    <div class="d-flex align-items-center justify-content-between mb-3">
        <span class="fw-bold">
            <i class="bi bi-box-seam-fill text-primary me-1"></i>
            {{ $products->total() }} منتج
        </span>
        <a href="{{ route('products.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>{{ __('products.actions.add') }}
        </a>
    </div>

    
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('products.fields.name') }}</th>
                        <th>{{ __('products.fields.type') }}</th>
                        <th>{{ __('products.fields.price') }}</th>
                        <th>{{ __('products.fields.quantity') }}</th>
                        <th>{{ __('products.fields.stock_status') }}</th>
                        <th>{{ __('products.fields.is_active') }}</th>
                        <th class="text-center">{{ __('messages.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @if($products->isNotEmpty())
                        @foreach($products as $product)
                            <tr>
                            <td class="fw-semibold">{{ $product->name }}</td>
                            <td>
                                @php
                                    $typeMap = [
                                        'product' => ['منتج', 'primary'],
                                        'service' => ['خدمة', 'success'],
                                        'vaccination' => ['تطعيم', 'info'],
                                    ];
                                    [$typeLabel, $typeColor] = $typeMap[$product->type] ?? [
                                        $product->type,
                                        'secondary',
                                    ];
                                @endphp
                                <span class="badge bg-{{ $typeColor }} text-white">
                                    {{ $typeLabel }}
                                </span>
                            </td>
                            <td class="font-monospace fw-semibold">
                                {{ number_format($product->price) }} {{ __('messages.currency') }}
                            </td>
                            <td class="font-monospace">{{ number_format($product->quantity) }}</td>
                            <td>
                                @if (!$product->track_stock)
                                    <span class="badge bg-secondary text-white border">—</span>
                                @elseif($product->stock_status === 'out_of_stock')
                                    <span class="badge bg-danger text-white">
                                        <i class="bi bi-x-circle me-1"></i>نفذ المخزون
                                    </span>
                                @elseif($product->stock_status === 'low')
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-exclamation-triangle me-1"></i>منخفض
                                    </span>
                                @else
                                    <span class="badge bg-success text-white">
                                        <i class="bi bi-check-circle me-1"></i>متوفر
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if ($product->is_active)
                                    <span class="badge bg-success text-white">
                                        <i class="bi bi-circle-fill me-1" style="font-size:.5rem;"></i>نشط
                                    </span>
                                @else
                                    <span class="badge bg-secondary text-white">
                                        <i class="bi bi-circle me-1" style="font-size:.5rem;"></i>غير نشط
                                    </span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex gap-1 justify-content-center flex-wrap">
                                    <a href="{{ route('products.edit', $product) }}"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>

                                    <form method="POST" action="{{ route('products.toggle-active', $product) }}"
                                        class="d-inline">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-warning"
                                            title="{{ $product->is_active ? 'تعطيل' : 'تفعيل' }}">
                                            <i class="bi bi-{{ $product->is_active ? 'pause-fill' : 'play-fill' }}"></i>
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('products.destroy', $product) }}"
                                        onsubmit="return confirm('{{ __('products.messages.confirm_delete') }}')"
                                        class="d-inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="bi bi-box-seam text-muted"></i>
                                    <p>{{ __('products.messages.no_results') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    
    <div class="mt-4">{{ $products->links() }}</div>

@endsection
