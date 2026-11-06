<?php

namespace App\Http\Controllers;

use App\Actions\Assets\AssetFiles;
use App\Models\Asset;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AssetAttachmentController
{
    public function __invoke(Asset $asset, int $attachment): StreamedResponse
    {
        Gate::authorize('view', $asset);
        $file = $asset->attachments()->findOrFail($attachment);
        $disk = Storage::disk(AssetFiles::DISK);
        abort_unless($file->disk === AssetFiles::DISK && $disk->exists($file->storage_path), 404);
        $extension = pathinfo($file->storage_path, PATHINFO_EXTENSION);

        return $disk->download($file->storage_path, $asset->asset_code.'-'.$file->kind.'.'.$extension, [
            'Content-Type' => $file->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
