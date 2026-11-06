<?php

namespace App\Http\Controllers;

use App\Actions\Identity\ManageAccounts;
use App\Models\User;
use App\Queries\ManagedAccounts;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

class AccountController
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        return view('admin.accounts', [
            'accounts' => ManagedAccounts::users($request->user())->with('employee')->orderBy('id')->paginate(25),
        ]);
    }

    public function store(Request $request, ManageAccounts $accounts): RedirectResponse
    {
        Gate::authorize('viewAny', User::class);
        abort_unless($request->user()->checkPermissionTo('users.manage'), 403);
        $input = $request->validate(['nik' => ['required', 'string', 'max:64'], 'email' => ['nullable', 'email:rfc', 'max:254']]);
        $employee = ManagedAccounts::employees($request->user())->where('nik', $input['nik'])->firstOrFail();
        $account = $accounts->create($request->user(), $employee, User::normalizeEmail($input['email'] ?? null));

        return to_route('accounts.show', $account)->with('status', __('iam.account_created'));
    }

    public function show(Request $request, User $account): View
    {
        Gate::authorize('view', $account);
        $canManageAccess = Gate::allows('access', $account);

        return view('admin.account', [
            'account' => $account->load('employee', 'roles'),
            'roles' => $canManageAccess ? Role::query()->where('guard_name', 'web')->orderBy('name')->get() : [],
            'scopeOptions' => $canManageAccess ? $this->scopeOptions() : [],
            'selectedScopes' => DB::table('user_data_scopes')->where('user_id', $account->id)->get()
                ->map(fn ($scope) => $scope->scope_type.':'.$scope->{$scope->scope_type.'_id'})->all(),
        ]);
    }

    public function update(Request $request, User $account, ManageAccounts $accounts): RedirectResponse
    {
        Gate::authorize('manage', $account);
        $input = $request->validate(['email' => ['nullable', 'email:rfc', 'max:254'], 'is_active' => ['required', 'boolean']]);
        $accounts->update($request->user(), $account, User::normalizeEmail($input['email'] ?? null), (bool) $input['is_active']);

        return back()->with('status', __('iam.saved'));
    }

    public function reset(Request $request, User $account, ManageAccounts $accounts): RedirectResponse
    {
        $accounts->reset($request->user(), $account);

        return back()->with('status', __('iam.admin_reset_requested'));
    }

    public function access(Request $request, User $account, ManageAccounts $accounts): RedirectResponse
    {
        Gate::authorize('access', $account);
        $input = $request->validate([
            'roles' => ['sometimes', 'array', 'max:100'],
            'roles.*' => ['required', 'integer', 'distinct', 'min:1'],
            'scopes' => ['sometimes', 'array', 'max:500'],
            'scopes.*' => ['required', 'string', 'distinct', 'regex:/\A(branch|division|department):[1-9][0-9]{0,18}\z/'],
        ]);

        $accounts->access($request->user(), $account, $input['roles'] ?? [], $input['scopes'] ?? []);

        return back()->with('status', __('iam.saved'));
    }

    private function scopeOptions(): array
    {
        return [
            'branch' => DB::table('branches')->orderBy('name')->get(['id', 'name']),
            'division' => DB::table('divisions')->join('branches', 'branches.id', '=', 'divisions.branch_id')
                ->orderBy('branches.name')->orderBy('divisions.name')->get(['divisions.id', 'divisions.name', 'branches.name as branch']),
            'department' => DB::table('departments')->join('divisions', 'divisions.id', '=', 'departments.division_id')
                ->join('branches', 'branches.id', '=', 'divisions.branch_id')
                ->orderBy('branches.name')->orderBy('divisions.name')->orderBy('departments.name')
                ->get(['departments.id', 'departments.name', 'divisions.name as division', 'branches.name as branch']),
        ];
    }
}
