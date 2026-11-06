<?php

namespace App\Http\Controllers;

use App\Actions\Organization\SaveMaster;
use App\Models\OrganizationMaster;
use App\Models\User;
use App\Queries\OrganizationMasters;
use App\Support\MasterType;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class OrganizationMasterController
{
    public function index(Request $request, MasterType $type): View
    {
        Gate::authorize('viewAny', $type->model());
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,archived,all'],
        ]);
        $search = $filters['q'] ?? '';
        $status = $filters['status'] ?? 'active';

        $query = OrganizationMasters::visible($request->user(), $type);
        if ($type->owner() !== null) {
            $query->with('owner');
        }
        if ($type === MasterType::Location) {
            $query->with('parent');
        }
        if ($search !== '') {
            $literal = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(fn ($q) => $q->whereRaw("code LIKE ? ESCAPE '!'", [$literal])->orWhereRaw("name LIKE ? ESCAPE '!'", [$literal]));
        }
        if ($status !== 'all') {
            $query->where('is_active', $status === 'active');
        }

        return view('masters.index', [
            'type' => $type,
            'rows' => $query->orderBy('name')->orderBy('id')->paginate(20)->withQueryString(),
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(Request $request, MasterType $type): View
    {
        Gate::authorize('create', [$type->model(), $type]);

        return $this->form($request->user(), $type);
    }

    public function edit(Request $request, MasterType $type, int $master): View
    {
        $record = $type->model()::query()->findOrFail($master);
        Gate::authorize('view', $record);

        return $this->form($request->user(), $type, $record);
    }

    public function store(Request $request, MasterType $type, SaveMaster $save): RedirectResponse
    {
        $record = $save($request->user(), $type, null, $request->only('master_code', 'name', 'owner_id', 'parent_id', 'is_active'));

        return to_route('masters.index', ['type' => $type, 'status' => $record->is_active ? 'active' : 'archived'])->with('status', __('masters.saved'));
    }

    public function update(Request $request, MasterType $type, int $master, SaveMaster $save): RedirectResponse
    {
        $record = $save($request->user(), $type, $master, $request->only('master_code', 'name', 'owner_id', 'parent_id', 'is_active'));

        return to_route('masters.index', ['type' => $type, 'status' => $record->is_active ? 'active' : 'archived'])->with('status', __('masters.saved'));
    }

    private function form(User $actor, MasterType $type, ?OrganizationMaster $record = null): View
    {
        $canWrite = $record === null || Gate::allows('update', $record);
        $ownerType = $type->owner();
        $owners = collect();
        $parents = collect();

        if ($record === null && $ownerType !== null) {
            $owners = OrganizationMasters::visible($actor, $ownerType)->where('is_active', true)
                ->when($type === MasterType::Department, fn ($q) => $q->whereHas('owner', fn ($branch) => $branch->where('is_active', true)))
                ->with($type === MasterType::Department ? ['owner'] : [])
                ->orderBy('name')->orderBy('id')->get();
        }
        if ($type === MasterType::Location && $canWrite) {
            $parents = OrganizationMasters::visible($actor, $type)
                ->where(function ($query) use ($record, $owners): void {
                    $query->where('is_active', true)
                        ->when($record !== null,
                            fn ($q) => $q->where('branch_id', $record->branch_id)->whereKeyNot($record->id),
                            fn ($q) => $q->whereIn('branch_id', $owners->modelKeys()));
                    if ($record?->parent_id !== null) {
                        $query->orWhere('id', $record->parent_id);
                    }
                })->orderBy('name')->orderBy('id')->get();
        }

        return view('masters.form', compact('type', 'record', 'owners', 'parents', 'canWrite'));
    }
}
