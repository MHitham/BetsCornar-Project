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
    public function index()
    {

        $users = User::with('roles')->latest()->paginate(15);

        return view('users.index', compact('users'));
    }

    public function create()
    {

        $roles = Role::pluck('name', 'name')->all();

        return view('users.create', compact('roles'));
    }

    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->assignRole($validated['role']);

        Cache::forget('users_list');

        return redirect()->route('users.index')
            ->with('success', 'تم إضافة الموظف بنجاح.');
    }

    public function edit(User $user)
    {

        $roles = Role::pluck('name', 'name')->all();
        $userRole = $user->roles->pluck('name')->first();

        return view('users.edit', compact('user', 'roles', 'userRole'));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $validated = $request->validated();

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (! empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);
        $user->syncRoles([$validated['role']]);

        Cache::forget('users_list');

        return redirect()->route('users.index')
            ->with('success', 'تم تحديث بيانات الموظف بنجاح.');
    }

    public function destroy(User $user)
    {

        if (auth()->id() === $user->id) {
            return redirect()->route('users.index')
                ->with('error', 'لا يمكنك حذف حسابك الشخصي.');
        }

        $user->delete();

        Cache::forget('users_list');

        return redirect()->route('users.index')
            ->with('success', 'تم حذف الموظف بنجاح.');
    }
}
