<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnimalController extends Controller
{
    // تم الإضافة: قائمة الحيوانات الخاصة بالعميل (مسار تحت العميل)
    public function index(Customer $customer): View
    {
       $animals = $customer->animals()->latest('created_at')->paginate(15);

        return view('customers.animals.index', compact('customer', 'animals'));
    }

    // تم الإضافة: حفظ حيوان جديد مرتبط بالعميل
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

    // تم الإضافة: صفحة بروفايل الحيوان (مسار مستقل تحت /animals/{animal})
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

    // تم الإضافة: تحديث بيانات الحيوان
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

    // تم الإضافة: حذف الحيوان فقط إن لم يكن مرتبطًا بفواتير أو تطعيمات
    public function destroy(Animal $animal): RedirectResponse
    {
        if ($animal->vaccinations()->exists() || $animal->invoices()->exists()) {
            return back()->withErrors([
                'animal_delete' => 'لا يمكن حذف حيوان مرتبط بسجلات طبية أو فواتير',
            ]);
        }

        $animal->delete();

        return back()->with('success', 'تم حذف الحيوان بنجاح');
    }
}
