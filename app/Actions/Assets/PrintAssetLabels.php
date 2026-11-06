<?php

namespace App\Actions\Assets;

use App\Models\Asset;
use App\Models\User;
use App\Queries\Assets;
use App\Support\AssetQr;
use App\Support\IdentityAccess;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class PrintAssetLabels
{
    public const OPTIONAL_FIELDS = ['name', 'category', 'status'];

    public const LIMIT = 50;

    public function prepare(User $actor, array $ids, int $templateId, array $fields): int
    {
        return DB::transaction(function () use ($actor, $ids, $templateId, $fields): int {
            $actor = IdentityAccess::lockScopedActor($actor);
            $this->assets($actor, $ids);
            $template = DB::table('asset_label_templates')->where('is_active', true)->sharedLock()->find($templateId);
            if ($template === null) {
                throw ValidationException::withMessages(['template_id' => __('qr.template_unavailable')]);
            }
            $this->checkFields($fields, $template);

            $job = DB::table('asset_label_print_jobs')->insertGetId([
                'template_id' => $template->id, 'requested_by' => $actor->id, 'selected_fields' => json_encode($fields, JSON_THROW_ON_ERROR),
                'status' => 'prepared', 'created_at' => now(),
            ]);
            DB::table('asset_label_print_job_items')->insert(array_map(fn (int $id) => ['job_id' => $job, 'asset_id' => $id], $ids));

            return $job;
        }, 3);
    }

    public function preview(User $actor, int $id): array
    {
        return DB::transaction(function () use ($actor, $id): array {
            $actor = IdentityAccess::lockScopedActor($actor);
            $job = DB::table('asset_label_print_jobs')->where('requested_by', $actor->id)->find($id);
            abort_if($job === null, 404);

            $ids = DB::table('asset_label_print_job_items')->where('job_id', $id)->orderBy('id')->pluck('asset_id')->all();
            $assets = $this->assets($actor, $ids);
            $template = DB::table('asset_label_templates')->where('is_active', true)->sharedLock()->find($job->template_id);
            abort_if($template === null, 404);
            $fields = json_decode($job->selected_fields, true, flags: JSON_THROW_ON_ERROR);
            $this->checkFields($fields, $template);

            $assets->load('type.category', 'status');
            $labels = $assets->map(fn (Asset $asset) => [
                'code' => $asset->asset_code, 'name' => $asset->name, 'category' => $asset->type->category->name,
                'status' => $asset->is_active ? $asset->status->name : __('assets.inactive'),
                'qr' => AssetQr::dataImage($asset->public_token),
            ]);

            return compact('labels', 'fields', 'template');
        }, 3);
    }

    private function assets(User $actor, array $ids): Collection
    {
        Gate::forUser($actor)->authorize('printAny', Asset::class);
        abort_if($ids === [] || count($ids) > self::LIMIT || count(array_unique($ids)) !== count($ids), 403);
        $assets = Assets::operational($actor, Assets::PRINT_LABEL)->whereKey($ids)->orderBy('id')->sharedLock()->get();
        abort_unless($assets->count() === count($ids), 403);

        return $assets;
    }

    private function checkFields(array $fields, object $template): void
    {
        $allowed = array_intersect(self::OPTIONAL_FIELDS, json_decode($template->allowed_fields, true, flags: JSON_THROW_ON_ERROR));
        if (array_diff($fields, $allowed) !== []) {
            throw ValidationException::withMessages(['fields' => __('qr.field_unavailable')]);
        }
    }
}
