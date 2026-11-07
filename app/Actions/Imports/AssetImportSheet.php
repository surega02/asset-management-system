<?php

namespace App\Actions\Imports;

use App\Support\ReadSpreadsheet;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class AssetImportSheet
{
    public const COLUMNS = [
        'asset_code', 'name', 'category_id', 'asset_type_id', 'physical_location_id', 'assignee',
        'status_id', 'condition_id', 'purchase_date', 'acquisition_cost', 'serial_number', 'invoice_number',
        'brand_id', 'vendor_id', 'warranty_ends_on', 'reason',
    ];

    private const MAX_COLUMNS = 64;

    public function __construct(private ReadSpreadsheet $reader) {}

    public function read(UploadedFile $file): array
    {
        $records = $this->reader->records($file, self::MAX_COLUMNS, ['purchase_date', 'warranty_ends_on'],
            ['asset_code', 'serial_number', 'invoice_number'], variableWidth: true);
        $header = array_map('trim', array_shift($records)[1] ?? []);
        if (! in_array('asset_code', $header, true) || $records === [] || ! self::validColumns($header)) {
            throw ValidationException::withMessages(['file' => __('asset_import.invalid_header')]);
        }

        $rows = [];
        foreach ($records as [$line, $values]) {
            if ($values === null || count($values) !== count($header) || array_filter($values, fn ($value) => ! self::validValue((string) $value))) {
                $rows[$line] = null;

                continue;
            }

            $payload = array_combine($header, array_map(fn ($value) => trim((string) $value), $values));
            if (implode('', $payload) !== '') {
                $rows[$line] = $payload;
            }
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['file' => __('asset_import.invalid_header')]);
        }

        return $rows;
    }

    public static function correction(array $before, array $values): array
    {
        if (! self::validColumns(array_keys($values)) || array_diff_key($values, array_flip(self::COLUMNS) + $before) !== []
            || array_filter($values, fn ($value) => $value !== null && (! is_string($value) || ! self::validValue($value)))) {
            throw ValidationException::withMessages(['values' => __('asset_import.invalid_row')]);
        }

        $result = array_replace($before, array_map(fn ($value) => trim((string) $value), $values));
        $result = array_filter($result, fn (string $value, string $key) => $value !== '' || array_key_exists($key, $before), ARRAY_FILTER_USE_BOTH);
        if (count($result) > self::MAX_COLUMNS) {
            throw ValidationException::withMessages(['values' => __('asset_import.invalid_row')]);
        }

        return $result;
    }

    private static function validColumns(array $columns): bool
    {
        if (count($columns) > self::MAX_COLUMNS || count(array_unique($columns)) !== count($columns)) {
            return false;
        }
        foreach ($columns as $column) {
            if (! is_string($column) || (! in_array($column, self::COLUMNS, true) && ! preg_match('/\Aattr\.[a-z][a-z0-9_]{0,63}\z/', $column))) {
                return false;
            }
        }

        return true;
    }

    private static function validValue(string $value): bool
    {
        return strlen($value) <= 8000 && mb_check_encoding($value, 'UTF-8') && ! str_contains($value, "\0");
    }
}
