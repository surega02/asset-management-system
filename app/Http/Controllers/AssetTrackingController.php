<?php

namespace App\Http\Controllers;

use App\Actions\Assets\AssetTargets;
use App\Actions\Assets\MoveAssetLocation;
use App\Actions\Assets\ReassignAsset;
use App\Models\Asset;
use App\Queries\Assets;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class AssetTrackingController
{
    public function show(Request $request, Asset $asset, AssetTargets $targets): View
    {
        return DB::transaction(function () use ($request, $asset, $targets): View {
            // Read the current state, histories and form versions from the same snapshot.
            $asset = Asset::query()->with('location.owner', 'activeAssignment.employee', 'activeAssignment.department', 'activeAssignment.location')->findOrFail($asset->id);
            Gate::authorize('view', $asset);
            $actor = $request->user();
            $canReassign = $asset->is_active && Gate::allows('reassign', $asset);
            $canMove = $asset->is_active && Gate::allows('move', $asset);

            $assignments = $asset->assignments()->with('employee', 'department', 'location', 'actor.employee:id,name')
                ->orderByDesc('started_at')->orderByDesc('id')->simplePaginate(10, pageName: 'assignment_page')->withQueryString();
            $moves = DB::table('asset_location_histories as history')->where('history.asset_id', $asset->id)
                ->leftJoin('locations as origin', 'origin.id', '=', 'history.from_location_id')
                ->leftJoin('branches as origin_branch', 'origin_branch.id', '=', 'origin.branch_id')
                ->join('locations as destination', 'destination.id', '=', 'history.to_location_id')
                ->join('branches as destination_branch', 'destination_branch.id', '=', 'destination.branch_id')
                ->join('users as actors', 'actors.id', '=', 'history.actor_id')
                ->join('employees as actor_employees', 'actor_employees.id', '=', 'actors.employee_id')
                ->orderByDesc('history.changed_at')->orderByDesc('history.id')
                ->simplePaginate(10, [
                    'history.changed_at', 'history.reason', 'origin.name as origin', 'origin_branch.name as origin_branch',
                    'destination.name as destination', 'destination_branch.name as destination_branch', 'actor_employees.name as actor_name',
                ], 'location_page')->withQueryString();

            return view('assets.tracking', [
                'asset' => $asset, 'assignments' => $assignments, 'moves' => $moves, 'canReassign' => $canReassign, 'canMove' => $canMove,
                'targets' => $canReassign ? $targets->options($actor, Assets::REASSIGN) : [],
                'destinations' => $canMove ? Assets::locations($actor, Assets::MOVE)->where('is_active', true)
                    ->whereKeyNot($asset->physical_location_id)->with('owner')->orderBy('name')->orderBy('id')->get() : collect(),
                'locationVersion' => $canMove ? DB::table('asset_location_histories')->where('asset_id', $asset->id)
                    ->orderByDesc('changed_at')->orderByDesc('id')->value('id') : null,
            ]);
        });
    }

    public function reassign(Request $request, Asset $asset, ReassignAsset $reassign): RedirectResponse
    {
        Gate::authorize('reassign', $asset);

        try {
            $reassign($request->user(), $asset,
                $request->only('assignment_id', 'reason') + AssetTargets::decodeSelection($request->input('assignee')));
        } catch (ValidationException $exception) {
            throw $exception->errorBag('assignmentChange');
        }

        return to_route('assets.tracking', $asset)->with('status', __('tracking.assignment_saved'));
    }

    public function move(Request $request, Asset $asset, MoveAssetLocation $move): RedirectResponse
    {
        Gate::authorize('move', $asset);

        try {
            $move($request->user(), $asset, $request->only('location_history_id', 'physical_location_id', 'reason'));
        } catch (ValidationException $exception) {
            throw $exception->errorBag('locationMove');
        }

        return to_route('assets.tracking', $asset)->with('status', __('tracking.move_saved'));
    }
}
