<?php

namespace App\Actions\Employees;

use DateTimeImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

final class ReadEmployeeSpreadsheet
{
    public const COLUMNS = ['nik', 'name', 'department_id', 'position_id', 'home_location_id', 'joined_at', 'employment_status'];

    private const MAX_ROWS = 1000;

    private const MAX_EXPANDED_BYTES = 12 * 1024 * 1024;

    private const MAX_XML_BYTES = 4 * 1024 * 1024;

    public function read(UploadedFile $file): array
    {
        $path = $file->getPathname();
        $extension = strtolower($file->getClientOriginalExtension());
        if (! $file->isValid() || $file->getSize() > 10 * 1024 * 1024 || ! in_array($extension, ['csv', 'xlsx'], true)) {
            $this->invalid();
        }

        $data = $extension === 'csv' ? $this->csv($path) : $this->xlsx($path);
        $header = array_shift($data);
        if ($header === null || $header[1] === null || array_map('trim', $header[1]) !== self::COLUMNS || $data === []) {
            $this->invalid('import_header');
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

    private function csv(string $path): array
    {
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            $this->invalid();
        }

        $data = [];
        try {
            if (fread($stream, 3) !== "\xEF\xBB\xBF") {
                rewind($stream);
            }
            for ($line = 1; ($row = fgetcsv($stream, separator: ',', escape: '')) !== false; $line++) {
                if ($row !== [null]) {
                    $this->append($data, $line, $row);
                }
            }
        } finally {
            fclose($stream);
        }

        return $data;
    }

    private function xlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class) || ! function_exists('simplexml_load_string')) {
            $this->invalid('import_extension');
        }
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            $this->invalid();
        }

        try {
            $expanded = 0;
            if ($zip->numFiles > 2048) {
                $this->invalid();
            }
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                if ($entry === false || ($expanded += $entry['size']) > self::MAX_EXPANDED_BYTES) {
                    $this->invalid();
                }
            }

            $types = $this->xml($zip, '[Content_Types].xml');
            $workbookType = $types->xpath('/*/*[@PartName="/xl/workbook.xml"]')[0] ?? null;
            if ((string) ($workbookType['ContentType'] ?? '') !== 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml') {
                $this->invalid();
            }
            $workbook = $this->xml($zip, 'xl/workbook.xml');
            $sheet = $workbook->xpath('//*[local-name()="sheet"]')[0] ?? null;
            $sheetId = (string) ($sheet?->xpath('@*[local-name()="id"]')[0] ?? '');
            $relationships = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
            $target = '';
            foreach ($relationships->xpath('/*/*') as $relationship) {
                if ((string) $relationship['Id'] === $sheetId
                    && str_ends_with((string) $relationship['Type'], '/worksheet')
                    && (string) $relationship['TargetMode'] !== 'External') {
                    $target = preg_replace('~^/?xl/~', '', (string) $relationship['Target']);
                    break;
                }
            }
            if (! preg_match('~^worksheets/[A-Za-z0-9_.-]+\.xml$~D', $target)) {
                $this->invalid();
            }
            $shared = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                foreach ($this->xml($zip, 'xl/sharedStrings.xml')->xpath('/*/*[local-name()="si"]') as $text) {
                    $shared[] = $this->text($text);
                }
            }
            $dateSystem = (string) (($workbook->xpath('//*[local-name()="workbookPr"]')[0] ?? null)['date1904'] ?? '');
            $data = [];
            $lastLine = 0;
            $worksheet = $this->xml($zip, 'xl/'.$target);
            foreach ($worksheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
                $line = isset($row['r']) ? (int) $row['r'] : $lastLine + 1;
                if ($line <= $lastLine || $line > 65535) {
                    $this->invalid();
                }
                $lastLine = $line;
                $values = $this->xlsxRow($row, $shared, $dateSystem === '1' || $dateSystem === 'true', $line, $data === []);
                if ($values !== [] && ($values === null || implode('', $values) !== '')) {
                    $this->append($data, $line, $values);
                }
            }

            return $data;
        } finally {
            $zip->close();
        }
    }

    private function xlsxRow(SimpleXMLElement $row, array $shared, bool $date1904, int $line, bool $header): ?array
    {
        $values = array_fill(0, count(self::COLUMNS), '');
        $seen = [];
        foreach ($row->xpath('./*[local-name()="c"]') as $cell) {
            $type = (string) $cell['t'];
            $value = (string) ($cell->xpath('./*[local-name()="v"]')[0] ?? '');
            if ($cell->xpath('./*[local-name()="f"]') !== [] || in_array($type, ['e', 'b'], true)) {
                return null;
            }
            if ($type === 's') {
                if (! ctype_digit($value) || ! isset($shared[(int) $value])) {
                    return null;
                }
                $value = $shared[(int) $value];
            } elseif ($type === 'inlineStr') {
                $value = $this->text($cell);
            }
            if ($value === '') {
                continue;
            }
            if (! preg_match('/^([A-G])([1-9][0-9]*)$/D', (string) $cell['r'], $reference) || (int) $reference[2] !== $line || isset($seen[$reference[1]])) {
                return null;
            }
            $seen[$reference[1]] = true;
            $column = ord($reference[1]) - ord('A');
            if (! $header && $column === 0 && in_array($type, ['', 'n'], true)) {
                return null; // NIK must be text so leading zeroes cannot be lost.
            }
            if (! $header && $column === 5 && in_array($type, ['', 'n'], true)) {
                if (! ctype_digit($value) || (int) $value > 2958465 || (! $date1904 && (int) $value === 60)) {
                    return null;
                }
                $days = (int) $value;
                $epoch = $date1904 ? '1904-01-01' : ($days < 60 ? '1899-12-31' : '1899-12-30');
                $value = (new DateTimeImmutable($epoch))->modify('+'.$days.' days')->format('Y-m-d');
            }
            $values[$column] = $value;
        }

        return $values;
    }

    private function text(SimpleXMLElement $xml): string
    {
        return implode('', $xml->xpath('.//*[local-name()="t" and not(ancestor::*[local-name()="rPh"])]'));
    }

    private function xml(ZipArchive $zip, string $entry): SimpleXMLElement
    {
        $data = $zip->getFromName($entry, self::MAX_XML_BYTES + 1);
        if (! is_string($data) || strlen($data) > self::MAX_XML_BYTES || substr_count($data, '<') > 100000
            || ! mb_check_encoding($data, 'UTF-8') || str_contains($data, "\0")
            || preg_match('/<!DOCTYPE|<!ENTITY/i', $data)) {
            $this->invalid();
        }
        $xml = simplexml_load_string($data, options: LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if ($xml === false) {
            $this->invalid();
        }

        return $xml;
    }

    private function append(array &$data, int $line, ?array $values): void
    {
        if (count($data) >= self::MAX_ROWS + 1 || $line > 65535) {
            $this->invalid('import_limit');
        }
        $data[] = [$line, $values];
    }

    private function invalid(string $key = 'import_file_invalid'): never
    {
        throw ValidationException::withMessages(['file' => __('people.'.$key)]);
    }
}
