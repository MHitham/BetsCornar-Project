<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        $suppliers = Supplier::query()
            ->withSum('purchaseOrders', 'total_cost')
            ->withSum('purchaseOrders', 'amount_paid')
            ->latest()
            ->paginate(20);

        return view('suppliers.index', compact('suppliers'));
    }

    public function create(): View
    {
        return view('suppliers.create');
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        Supplier::create($request->validated() + ['is_active' => true]);

        return redirect()->route('suppliers.index')
            ->with('success', 'تم إضافة المورد بنجاح');
    }

    public function edit(Supplier $supplier): View
    {
        return view('suppliers.edit', compact('supplier'));
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()->route('suppliers.index')
            ->with('success', 'تم تعديل بيانات المورد بنجاح');
    }

    public function toggleActive(Supplier $supplier): RedirectResponse
    {
        $supplier->update(['is_active' => ! $supplier->is_active]);

        $message = $supplier->is_active ? 'تم تفعيل المورد' : 'تم تعطيل المورد';

        return redirect()->route('suppliers.index')->with('success', $message);
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->purchaseOrders()->exists()) {
            return redirect()->route('suppliers.index')
                ->with('error', 'لا يمكن حذف المورد لأن لديه فواتير شراء مرتبطة به');
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')
            ->with('success', 'تم حذف المورد بنجاح');
    }
}
