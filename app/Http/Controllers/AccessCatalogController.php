<?php

namespace App\Http\Controllers;

use App\Actions\Identity\ManageAccessCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AccessCatalogController
{
    public function index(): View
    {
        Gate::authorize('manage-access-catalog');

        return view('admin.catalog', [
            'roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->paginate(25, pageName: 'roles'),
            'permissions' => Permission::query()->where('guard_name', 'web')->orderBy('name')->paginate(25, pageName: 'permissions'),
        ]);
    }

    public function role(?Role $role = null): View
    {
        Gate::authorize('manage-access-catalog');

        return view('admin.role', [
            'role' => $role,
            'permissions' => Permission::query()->where('guard_name', 'web')->orderBy('name')->get(),
        ]);
    }

    public function saveRole(Request $request, ManageAccessCatalog $catalog, ?Role $role = null): RedirectResponse
    {
        Gate::authorize('manage-access-catalog');
        $input = $request->validate([
            'name' => ['required', 'string', 'max:125', 'regex:/\A[a-z][a-z0-9.-]*\z/'],
            'permissions' => ['sometimes', 'array', 'max:500'],
            'permissions.*' => ['required', 'integer', 'distinct', 'min:1'],
        ]);
        $catalog->saveRole($request->user(), $role, $input['name'], $input['permissions'] ?? []);

        return to_route('catalog.index')->with('status', __('iam.saved'));
    }

    public function permission(?Permission $permission = null): View
    {
        Gate::authorize('manage-access-catalog');

        return view('admin.permission', ['permission' => $permission]);
    }

    public function savePermission(Request $request, ManageAccessCatalog $catalog, ?Permission $permission = null): RedirectResponse
    {
        Gate::authorize('manage-access-catalog');
        $input = $request->validate(['name' => ['required', 'string', 'max:125', 'regex:/\A[a-z][a-z0-9.-]*\z/']]);
        $catalog->savePermission($request->user(), $permission, $input['name']);

        return to_route('catalog.index')->with('status', __('iam.saved'));
    }

    public function deleteRole(Request $request, Role $role, ManageAccessCatalog $catalog): RedirectResponse
    {
        $catalog->delete($request->user(), $role);

        return to_route('catalog.index')->with('status', __('iam.saved'));
    }

    public function deletePermission(Request $request, Permission $permission, ManageAccessCatalog $catalog): RedirectResponse
    {
        $catalog->delete($request->user(), $permission);

        return to_route('catalog.index')->with('status', __('iam.saved'));
    }
}
