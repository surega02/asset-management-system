<?php

namespace App\Actions\Assets;

use App\Models\Asset;
use App\Models\User;
use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class AssetFiles
{
    public const DISK = 'asset-private';

    private const IMAGES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** Stage verified files once, including when the database transaction is retried. */
    public function run(array $uploads, callable $operation): mixed
    {
        $files = [];
        try {
            foreach ($uploads as $kind => $upload) {
                if ($upload !== null) {
                    $files[] = $this->stage($kind, $upload);
                }
            }

            $result = $operation($files);
            if ($files !== [] && DB::transactionLevel() > 0) {
                DB::afterRollBack(fn () => $this->clean($files));
            }

            return $result;
        } catch (Throwable $exception) {
            $this->clean($files);
            throw $exception;
        }
    }

    public function attach(Asset $asset, User $actor, array $files): array
    {
        $ids = [];
        foreach ($files as $file) {
            $ids[$file['kind']] = $asset->attachments()->create($file + ['uploaded_by' => $actor->id])->id;
        }

        return $ids;
    }

    private function stage(string $kind, mixed $upload): array
    {
        $photo = $kind === 'photo';
        if (! in_array($kind, ['photo', 'invoice', 'deactivation'], true) || ! $upload instanceof UploadedFile || ! $upload->isValid()) {
            $this->invalid($kind);
        }
        $size = $upload->getSize();
        if (! is_int($size) || $size < 1 || $size > ($photo ? 5 : 10) * 1024 * 1024) {
            $this->invalid($kind);
        }

        $path = $upload->getPathname();
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        $extensions = $photo ? self::IMAGES : ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
        if (! isset($extensions[$mime])) {
            $this->invalid($kind);
        }

        $bytes = file_get_contents($path);
        if (! is_string($bytes) || ! ($mime === 'application/pdf' ? $this->validPdf($bytes) : $this->validImage($bytes, $mime))) {
            $this->invalid($kind);
        }

        $stored = bin2hex(random_bytes(24)).'.'.$extensions[$mime];
        if (! Storage::disk(self::DISK)->put($stored, $bytes)) {
            throw new RuntimeException('Could not save a private asset attachment.');
        }

        return ['kind' => $kind, 'disk' => self::DISK, 'storage_path' => $stored, 'mime_type' => $mime, 'size_bytes' => $size];
    }

    private function validImage(string $bytes, string $mime): bool
    {
        $image = @getimagesizefromstring($bytes);

        return $image !== false && $image['mime'] === $mime
            && $image[0] > 0 && $image[1] > 0 && $image[0] * $image[1] <= 24000000
            && @imagecreatefromstring($bytes) !== false;
    }

    private function validPdf(string $bytes): bool
    {
        if (! preg_match('/\A%PDF-(?:1\.[0-7]|2\.0)[\r\n]/', $bytes)
            || ! preg_match('/startxref\s+([0-9]{1,9})\s+%%EOF[ \t\r\n]*\z/', substr($bytes, -2048), $match)) {
            return false;
        }

        $xref = substr($bytes, (int) $match[1]);
        if (preg_match('/\Axref\s+[0-9]+\s+[0-9]+\s+[0-9]{10}\s+[0-9]{5}\s+[fn]\b/', $xref)) {
            $trailer = strrpos($xref, 'trailer');

            return $trailer !== false && preg_match('/\/(?:Root\s+[0-9]+\s+[0-9]+\s+R|Prev\s+[0-9]+)\b/', substr($xref, $trailer)) === 1;
        }

        // A stream-based cross-reference uses a typed indirect object.
        $header = strstr(substr($xref, 0, 4096), 'stream', true);

        return $header !== false && preg_match('/\A[0-9]+\s+[0-9]+\s+obj\b[\s\S]*\/Type\s*\/XRef\b/', $header) === 1;
    }

    private function clean(array $files): void
    {
        foreach ($files as $file) {
            try {
                Storage::disk(self::DISK)->delete($file['storage_path']);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    private function invalid(string $field): never
    {
        throw ValidationException::withMessages([$field => __('assets.invalid_'.$field)]);
    }
}
