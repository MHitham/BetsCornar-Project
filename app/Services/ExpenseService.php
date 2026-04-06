<?php

namespace App\Services;

use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    // تم الإضافة: حفظ مصروف جديد داخل transaction واحدة
    public function create(array $validated): Expense
    {
        return DB::transaction(function () use ($validated) {
            return Expense::create([
                ...$validated,
                'created_by' => auth()->id(),
            ]);
        });
    }

    // تم الإضافة: تحديث المصروف داخل transaction واحدة
    public function update(Expense $expense, array $validated): Expense
    {
        return DB::transaction(function () use ($expense, $validated) {
            $expense->update($validated);

            return $expense->fresh();
        });
    }

    // تم الإضافة: حذف ناعم للمصروف داخل transaction واحدة
    public function delete(Expense $expense): void
    {
        DB::transaction(function () use ($expense) {
            $expense->delete();
        });
    }
}