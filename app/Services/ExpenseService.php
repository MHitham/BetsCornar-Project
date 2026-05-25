<?php

namespace App\Services;

use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function create(array $validated): Expense
    {
        return DB::transaction(function () use ($validated) {
            return Expense::create([
                ...$validated,
                'created_by' => auth()->id(),
            ]);
        });
    }

    public function update(Expense $expense, array $validated): Expense
    {
        return DB::transaction(function () use ($expense, $validated) {
            $expense->update($validated);

            return $expense->fresh();
        });
    }

    public function delete(Expense $expense): void
    {
        DB::transaction(function () use ($expense) {
            $expense->delete();
        });
    }
}
