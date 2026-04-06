<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use App\Services\ExpenseService;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenseService,
    ) {}

    public function index(Request $request)
    {
        // تم الإضافة: فلتر المصروفات بالشهر مع إجمالي المصروفات الشهرية
        $month = $request->input('month');

        $query = Expense::query()->with('creator');

        if ($month && preg_match('/^(\d{4})-(\d{2})$/', $month, $matches)) {
            $query->whereYear('expense_date', (int) $matches[1])
                ->whereMonth('expense_date', (int) $matches[2]);
        }

        $totalMonthly = (float) (clone $query)->sum('amount');

        $expenses = $query->latest('expense_date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('expenses.index', compact('expenses', 'month', 'totalMonthly'));
    }

    public function create()
    {
        return view('expenses.create');
    }

    public function store(StoreExpenseRequest $request)
    {
        $this->expenseService->create($request->validated());

        return redirect()
            ->route('expenses.index')
            ->with('success', __('expenses.messages.created'));
    }

    public function edit(Expense $expense)
    {
        return view('expenses.edit', compact('expense'));
    }

    public function update(UpdateExpenseRequest $request, Expense $expense)
    {
        $this->expenseService->update($expense, $request->validated());

        return redirect()
            ->route('expenses.index')
            ->with('success', __('expenses.messages.updated'));
    }

    public function destroy(Expense $expense)
    {
        $this->expenseService->delete($expense);

        return redirect()
            ->route('expenses.index')
            ->with('success', __('expenses.messages.deleted'));
    }
}