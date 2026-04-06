@extends('layouts.app')

@section('title', __('expenses.actions.add'))
@section('page-title', __('expenses.actions.add'))

@section('content')

    {{-- تم الإضافة: نموذج إنشاء مصروف جديد --}}
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('expenses.store') }}" class="row g-3">
                @csrf

                <div class="col-md-6">
                    <label for="title" class="form-label">{{ __('expenses.fields.title') }}</label>
                    <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror"
                        value="{{ old('title') }}" required>
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label for="amount" class="form-label">{{ __('expenses.fields.amount') }}</label>
                    {{-- تم التعديل: تحديث القيم الافتراضية للحقل الرقمي --}}
                    <input type="number" step="0.01" min="0" name="amount" id="amount"
                        class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" placeholder="0" required>
                    @error('amount')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label for="expense_date" class="form-label">{{ __('expenses.fields.expense_date') }}</label>
                    <input type="date" name="expense_date" id="expense_date"
                        class="form-control @error('expense_date') is-invalid @enderror"
                        value="{{ old('expense_date', now()->toDateString()) }}" required>
                    @error('expense_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label for="notes" class="form-label">{{ __('expenses.fields.notes') }}</label>
                    <textarea name="notes" id="notes" rows="4" class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                    @error('notes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 d-flex gap-2 justify-content-end">
                    <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary">{{ __('messages.cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('expenses.actions.save') }}</button>
                </div>
            </form>
        </div>
    </div>

@endsection