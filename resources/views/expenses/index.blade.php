@extends('layouts.app')

@section('title', __('expenses.title'))
@section('page-title', __('expenses.title'))

@section('content')

    {{-- تم الإضافة: فلتر المصروفات بالشهر مع ملخص إجمالي الشهر --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('expenses.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="month" class="form-label">{{ __('expenses.filters.month_label') }}</label>
                    <input type="month" name="month" id="month" value="{{ $month }}" class="form-control"
                        onchange="this.form.submit()">
                </div>
                <div class="col-md-4">
                    <div class="border rounded-4 p-3 bg-light h-100 d-flex flex-column justify-content-center">
                        <span class="text-muted small mb-1">{{ __('expenses.filters.total_monthly') }}</span>
                        <span class="fw-bold fs-5">{{ number_format($totalMonthly, 2) }} {{ __('messages.currency') }}</span>
                    </div>
                </div>
                <div class="col-md-4 d-flex gap-2 justify-content-md-end">
                    <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle me-1"></i>{{ __('messages.clear') }}
                    </a>
                    <a href="{{ route('expenses.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i>{{ __('expenses.actions.add') }}
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- تم الإضافة: جدول المصروفات مع إجراءات التعديل والحذف --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('expenses.fields.title') }}</th>
                        <th>{{ __('expenses.fields.amount') }}</th>
                        <th>{{ __('expenses.fields.expense_date') }}</th>
                        <th>{{ __('expenses.fields.notes') }}</th>
                        <th>{{ __('expenses.fields.created_by') }}</th>
                        <th class="text-center">{{ __('messages.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($expenses as $expense)
                        <tr>
                            <td class="fw-semibold">{{ $expense->title }}</td>
                            <td class="font-monospace fw-bold">{{ number_format($expense->amount, 2) }} {{ __('messages.currency') }}</td>
                            <td>{{ $expense->expense_date->format('Y-m-d') }}</td>
                            <td>{{ $expense->notes ?: '—' }}</td>
                            <td>{{ $expense->creator?->name ?? '—' }}</td>
                            <td class="text-center">
                                <div class="d-flex gap-1 justify-content-center flex-wrap">
                                    <a href="{{ route('expenses.edit', $expense) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil-fill me-1"></i>{{ __('expenses.actions.edit') }}
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger expense-delete-button"
                                        data-bs-toggle="modal" data-bs-target="#deleteExpenseModal"
                                        data-action="{{ route('expenses.destroy', $expense) }}"
                                        data-title="{{ $expense->title }}">
                                        <i class="bi bi-trash-fill me-1"></i>{{ __('expenses.actions.delete') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="bi bi-wallet2 text-muted"></i>
                                    <p>{{ __('expenses.messages.no_results') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $expenses->links() }}</div>

    {{-- تم الإضافة: مودال تأكيد حذف المصروف بدون أي تغيير في المنطق --}}
    <div class="modal fade" id="deleteExpenseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('expenses.actions.delete') }}</h5>
                    <button type="button" class="btn-close ms-0 me-auto" data-bs-dismiss="modal"
                        aria-label="{{ __('messages.close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" id="delete-expense-message">{{ __('expenses.messages.confirm_delete') }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        {{ __('messages.cancel') }}
                    </button>
                    <form method="POST" id="delete-expense-form" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">{{ __('expenses.actions.delete') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- تم الإضافة: ربط زر الحذف بالمودال المشترك باستخدام JavaScript عادي فقط --}}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const deleteButtons = document.querySelectorAll('.expense-delete-button');
            const deleteForm = document.getElementById('delete-expense-form');
            const deleteMessage = document.getElementById('delete-expense-message');
            const baseMessage = @json(__('expenses.messages.confirm_delete'));

            deleteButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    deleteForm.action = button.dataset.action;
                    deleteMessage.textContent = `${baseMessage} (${button.dataset.title})`;
                });
            });
        });
    </script>

@endsection