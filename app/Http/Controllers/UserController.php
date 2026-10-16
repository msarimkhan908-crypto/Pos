<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:users.view',   only: ['index']),
            new Middleware('permission:users.create', only: ['create', 'store']),
            new Middleware('permission:users.edit',   only: ['edit', 'update']),
            new Middleware('permission:users.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->limit(100)->toString();
        $role   = $request->string('role')->toString();

        $users = User::query()
            ->with('roles:id,name')
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($role, fn ($q) => $q->whereHas('roles', fn ($q) => $q->where('name', $role)))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $roles = Role::orderBy('name')->pluck('name', 'name')->all();

        return view('users.index', compact('users', 'search', 'role', 'roles'));
    }

    public function create(): View
    {
        $roles = Role::orderBy('name')->pluck('name', 'name')->all();

        return view('users.create', ['user' => new User, 'roles' => $roles]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::create($request->only('name', 'email', 'password'));
        $user->syncRoles([$request->input('role')]);

        return redirect()->route('users.index')
            ->with('success', __('User ":name" created.', ['name' => $user->name]));
    }

    public function edit(User $user): View
    {
        $roles = Role::orderBy('name')->pluck('name', 'name')->all();

        return view('users.edit', compact('user', 'roles'));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->only('name', 'email');
        if ($request->filled('password')) {
            $data['password'] = $request->input('password');
        }
        $user->update($data);
        $user->syncRoles([$request->input('role')]);

        return redirect()->route('users.index')
            ->with('success', __('User ":name" updated.', ['name' => $user->name]));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', __('You cannot delete your own account.'));
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', __('User ":name" deleted.', ['name' => $user->name]));
    }
}
