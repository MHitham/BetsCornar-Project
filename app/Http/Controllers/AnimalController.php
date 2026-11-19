<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Vaccination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AnimalController extends Controller
{
    public function index(Customer $customer): View
    {
        $animals = $customer->animals()->withCount('vaccinations')->latest('created_at')->paginate(15);

        return view('customers.animals.index', compact('customer', 'animals'));
    }

    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'species' => ['required', 'string', 'max:255'],
            'breed' => ['nullable', 'string', 'max:255'],
            'age' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', 'in:male,female'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'color' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $customer->animals()->create($validated);

        return back()->with('success', 'تم إضافة الحيوان بنجاح');
    }

    public function show(Animal $animal): View
    {
        $animal->load([
            'customer',
            'vaccinations' => function ($query) {
                $query->with(['product', 'invoice'])->latest('created_at');
            },
            'invoices' => function ($query) {
                $query->with(['items.product'])->latest('created_at');
            },
        ]);

        return view('customers.animals.show', compact('animal'));
    }

    public function update(Request $request, Animal $animal): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'species' => ['required', 'string', 'max:255'],
            'breed' => ['nullable', 'string', 'max:255'],
            'age' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', 'in:male,female'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'color' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $animal->update($validated);

        return back()->with('success', 'تم تحديث بيانات الحيوان بنجاح');
    }

    public function destroy(Animal $animal): RedirectResponse
    {
        // منع الموظف صراحةً من حذف ملف الحيوان وإرجاع 403 بدل إعادة التوجيه.
        abort_unless(auth()->user()?->hasRole('admin'), 403);
        $customerId = $animal->customer_id;

        DB::transaction(function () use ($animal) {
            $animal = Animal::whereKey($animal->id)->lockForUpdate()->firstOrFail();
            // حفظ اسم الحيوان على فواتيره قبل الحذف حتى يبقى ظاهراً في السجل.
            Invoice::where('animal_id', $animal->id)->whereNull('animal_name')->update(['animal_name' => $animal->name]);
            // فصل الفواتير عن الحيوان، فالفواتير لا تُحذف أبداً.
            Invoice::where('animal_id', $animal->id)->update(['animal_id' => null]);
            // حذف التطعيمات الطبية فقط بدون لمس المخزون أو دفعات اللقاح.
            Vaccination::where('animal_id', $animal->id)->delete();
            $animal->delete();
        });

        Cache::forget(dashboardKey('upcoming_vaccinations'));
        Cache::forget('dashboard.total_vaccinations');

        return redirect()->route('customers.show', $customerId)
            ->with('success', 'تم حذف ملف الحيوان وتطعيماته. الفواتير محفوظة.');
    }
}
