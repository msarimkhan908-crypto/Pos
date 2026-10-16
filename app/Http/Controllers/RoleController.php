<?php

namespace App\Http\Controllers;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller implements HasMiddleware
{
    /** System roles that cannot be edited or deleted. */
    private const SYSTEM_ROLES = ['Super Admin'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:roles.view',   only: ['index', 'show']),
            new Middleware('permission:roles.create', only: ['create', 'store']),
            new Middleware('permission:roles.edit',   only: ['edit', 'update']),
            new Middleware('permission:roles.delete', only: ['destroy']),
        ];
    }

    public function index(): View
    {
        $roles = Role::withCount(['users', 'permissions'])->orderBy('name')->get();

        return view('roles.index', compact('roles'));
    }

    public function show(Role $role): View
    {
        $role->load('permissions');

        $grouped = $this->groupedPermissions($role->permissions->pluck('name')->all());

        return view('roles.show', compact('role', 'grouped'));
    }

    public function create(): View
    {
        $grouped = $this->groupedPermissions([]);

        return view('roles.create', ['role' => new Role, 'grouped' => $grouped]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:64', 'unique:roles,name'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('roles.show', $role)
            ->with('success', __('Role ":name" created.', ['name' => $role->name]));
    }

    public function edit(Role $role): View
    {
        abort_if(in_array($role->name, self::SYSTEM_ROLES, true), 403);

        $role->load('permissions');
        $grouped = $this->groupedPermissions($role->permissions->pluck('name')->all());

        return view('roles.edit', compact('role', 'grouped'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if(in_array($role->name, self::SYSTEM_ROLES, true), 403);

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:64', 'unique:roles,name,'.$role->id],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('roles.show', $role)
            ->with('success', __('Role ":name" updated.', ['name' => $role->name]));
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_if(in_array($role->name, self::SYSTEM_ROLES, true), 403);

        if ($role->users()->exists()) {
            return back()->with('error', __('Role ":name" has users assigned and cannot be deleted.', ['name' => $role->name]));
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('roles.index')
            ->with('success', __('Role ":name" deleted.', ['name' => $role->name]));
    }

    /**
     * Group all defined permissions, marking which ones are active for a given role.
     *
     * @param  list<string>  $active
     * @return array<string, array{label: string, permissions: array<string, array{name: string, label: string, active: bool}>}>
     */
    private function groupedPermissions(array $active): array
    {
        $grouped = [];

        foreach (RolesAndPermissionsSeeder::PERMISSIONS as $module => $actions) {
            $grouped[$module] = [
                'label'       => ucfirst(str_replace('_', ' ', $module)),
                'permissions' => [],
            ];
            foreach ($actions as $action) {
                $name = "{$module}.{$action}";
                $grouped[$module]['permissions'][$action] = [
                    'name'   => $name,
                    'label'  => ucfirst($action),
                    'active' => in_array($name, $active, true),
                ];
            }
        }

        return $grouped;
    }
}
