<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // تخزين قائمة المستخدمين في الكاش لمدة ساعة لتخفيف الضغط على قاعدة البيانات
        $users = Cache::remember('users_list', 3600, function () {
            return User::with('roles')->latest()->paginate(15);
        });

        return view('users.index', compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // تم الإضافة: جلب الأدوار المتاحة لعرضها في القائمة
        $roles = Role::pluck('name', 'name')->all();

        return view('users.create', compact('roles'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

        // تم الإضافة: إنشاء الموظف وتعيين دوره
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->assignRole($validated['role']);

        // مسح كاش قائمة المستخدمين بعد الإضافة
        Cache::forget('users_list');

        return redirect()->route('users.index')
            ->with('success', 'تم إضافة الموظف بنجاح.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        // تم الإضافة: جلب الدور الحالي للمستخدم للتعديل
        $roles = Role::pluck('name', 'name')->all();
        $userRole = $user->roles->pluck('name')->first();

        return view('users.edit', compact('user', 'roles', 'userRole'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $validated = $request->validated();

        // تم الإضافة: تحديث بيانات الموظف والصلاحية
        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        // تحديث كلمة المرور فقط إذا تم إدخالها
        if (! empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);
        $user->syncRoles([$validated['role']]);

        // مسح كاش قائمة المستخدمين بعد التعديل
        Cache::forget('users_list');

        return redirect()->route('users.index')
            ->with('success', 'تم تحديث بيانات الموظف بنجاح.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        // تم الإضافة: منع الأدمن من حذف نفسه
        if (auth()->id() === $user->id) {
            return redirect()->route('users.index')
                ->with('error', 'لا يمكنك حذف حسابك الشخصي.');
        }

        $user->delete();

        // مسح كاش قائمة المستخدمين بعد الحذف
        Cache::forget('users_list');

        return redirect()->route('users.index')
            ->with('success', 'تم حذف الموظف بنجاح.');
    }
}
