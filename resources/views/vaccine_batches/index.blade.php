@extends('layouts.app')

@section('title', __('vaccine_batches.title'))
@section('page-title', __('vaccine_batches.title'))

@section('content')

    
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('vaccine-batches.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">{{ __('vaccine_batches.actions.search') }}</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}"
                            placeholder="ابحث عن كود الدُفعة...">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('vaccine_batches.fields.product') }}</label>
                    <select name="product_id" class="form-select">
                        <option value="">{{ __('vaccine_batches.filters.all_vaccines') }}</option>
                        @foreach ($vaccines as $vaccine)
                            <option value="{{ $vaccine->id }}" @selected((int) ($filters['product_id'] ?? 0) === $vaccine->id)>
                                {{ $vaccine->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="bi bi-search me-1"></i>{{ __('vaccine_batches.actions.search') }}
                    </button>
                    <a href="{{ route('vaccine-batches.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    
    <div class="d-flex align-items-center justify-content-between mb-3">
        <span class="fw-bold">
            <i class="bi bi-capsule-pill text-primary me-1"></i>
            {{ $batches->total() }} دُفعة
        </span>
        <a href="{{ route('vaccine-batches.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>{{ __('vaccine_batches.actions.add') }}
        </a>
    </div>

    
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('vaccine_batches.fields.product') }}</th>
                        <th>{{ __('vaccine_batches.fields.batch_code') }}</th>
                        <th>{{ __('vaccine_batches.fields.received_date') }}</th>
                        <th>{{ __('vaccine_batches.fields.expiry_date') }}</th>
                        <th>{{ __('vaccine_batches.fields.quantity_received') }}</th>
                        <th>{{ __('vaccine_batches.fields.quantity_remaining') }}</th>
                        <th>{{ __('vaccine_batches.fields.status') }}</th>
                        <th class="text-center">{{ __('messages.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @if($batches->isNotEmpty())
                        @foreach($batches as $batch)
                            @php
                            $isExpired = $batch->expiry_date->isBefore(today());
                            $isExpiringSoon = !$isExpired && $batch->expiry_date->lte(today()->addDays(5));
                        @endphp
                        <tr @class([
                            'table-danger bg-opacity-10' => $isExpired,
                            'table-warning bg-opacity-10' => $isExpiringSoon,
                        ])>
                            <td class="fw-semibold">{{ $batch->product->name }}</td>
                            <td class="font-monospace text-muted">{{ $batch->batch_code ?: '—' }}</td>
                            <td>{{ $batch->received_date->format('Y-m-d') }}</td>
                            <td class="font-monospace fw-semibold">{{ $batch->expiry_date->format('Y-m-d') }}</td>
                            <td class="font-monospace">{{ number_format($batch->quantity_received) }}</td>
                            <td class="font-monospace fw-bold">{{ number_format($batch->quantity_remaining) }}</td>
                            <td>
                                @if ($isExpired)
                                    <span class="badge bg-danger text-white">
                                        <i
                                            class="bi bi-x-octagon-fill me-1"></i>{{ __('vaccine_batches.statuses.expired') }}
                                    </span>
                                @elseif($isExpiringSoon)
                                    <span class="badge bg-warning text-dark">
                                        <i
                                            class="bi bi-exclamation-triangle-fill me-1"></i>{{ __('vaccine_batches.statuses.expiring_soon') }}
                                    </span>
                                @else
                                    <span class="badge bg-success text-white">
                                        <i
                                            class="bi bi-check-circle-fill me-1"></i>{{ __('vaccine_batches.statuses.usable') }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex gap-1 justify-content-center">
                                    <a href="{{ route('vaccine-batches.edit', $batch) }}"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>
                                    <form method="POST" action="{{ route('vaccine-batches.destroy', $batch) }}"
                                        onsubmit="return confirm('{{ __('vaccine_batches.messages.confirm_delete') }}')"
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
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="bi bi-capsule-pill text-muted"></i>
                                    <p>{{ __('vaccine_batches.messages.no_results') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    
    <div class="mt-4">{{ $batches->links() }}</div>

@endsection
