<?php

namespace App\Actions\Employees;

use App\Support\ReadSpreadsheet;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class ReadEmployeeSpreadsheet
{
    public const COLUMNS = ['nik', 'name', 'department_id', 'position_id', 'home_location_id', 'joined_at', 'employment_status'];

    public function __construct(private ReadSpreadsheet $reader) {}

    public function read(UploadedFile $file): array
    {
        $data = $this->reader->records($file, count(self::COLUMNS), ['joined_at'], ['nik']);
        $header = array_shift($data);
        if ($header === null || $header[1] === null || array_map('trim', $header[1]) !== self::COLUMNS || $data === []) {
            throw ValidationException::withMessages(['file' => __('people.import_header')]);
        }

        $rows = [];
        foreach ($data as [$line, $values]) {
            if ($values !== null && count($values) === count(self::COLUMNS)
                && ! array_filter($values, fn (string $value) => strlen($value) > 1024 || ! mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0"))) {
                $rows[$line] = array_combine(self::COLUMNS, $values);
            } else {
                $rows[$line] = null;
            }
        }

        return $rows;
    }
}
