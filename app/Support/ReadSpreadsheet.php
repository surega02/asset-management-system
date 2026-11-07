<?php

namespace App\Support;

use DateTimeImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

final class ReadSpreadsheet
{
    private const MAX_ROWS = 1000;

    private const MAX_EXPANDED_BYTES = 12 * 1024 * 1024;

    private const MAX_XML_BYTES = 4 * 1024 * 1024;

    public function records(UploadedFile $file, int $columns, array $dateColumns, array $textColumns, bool $variableWidth = false): array
    {
        $path = $file->getPathname();
        $extension = strtolower($file->getClientOriginalExtension());
        if (! $file->isValid() || $file->getSize() > 10 * 1024 * 1024 || ! in_array($extension, ['csv', 'xlsx'], true)) {
            $this->invalid();
        }

        return $extension === 'csv' ? $this->csv($path) : $this->xlsx($path, $columns, $dateColumns, $textColumns, $variableWidth);
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

    private function xlsx(string $path, int $columns, array $dateColumns, array $textColumns, bool $variableWidth): array
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
                $header = $data[0][1] ?? [];
                $width = $variableWidth && $header !== [] ? count($header) : $columns;
                $values = $this->xlsxRow($row, $shared, $dateSystem === '1' || $dateSystem === 'true', $line, $width,
                    $header, $dateColumns, $textColumns);
                if ($variableWidth && $data === [] && $values !== null) {
                    while ($values !== [] && end($values) === '') {
                        array_pop($values);
                    }
                }
                if ($values !== [] && ($values === null || implode('', $values) !== '')) {
                    $this->append($data, $line, $values);
                }
            }

            return $data;
        } finally {
            $zip->close();
        }
    }

    private function xlsxRow(SimpleXMLElement $row, array $shared, bool $date1904, int $line, int $width, array $header, array $dateColumns, array $textColumns): ?array
    {
        $values = array_fill(0, $width, '');
        $seen = [];
        foreach ($row->xpath('./*[local-name()="c"]') as $cell) {
            $type = (string) $cell['t'];
            $value = (string) ($cell->xpath('./*[local-name()="v"]')[0] ?? '');
            if ($cell->xpath('./*[local-name()="f"]') !== [] || $type === 'e') {
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
            if (! preg_match('/^([A-Z]{1,2})([1-9][0-9]*)$/D', (string) $cell['r'], $reference) || (int) $reference[2] !== $line || isset($seen[$reference[1]])) {
                return null;
            }
            $seen[$reference[1]] = true;
            $column = 0;
            foreach (str_split($reference[1]) as $letter) {
                $column = 26 * $column + ord($letter) - 64;
            }
            $column--;
            if ($column >= $width) {
                return null;
            }
            $key = trim($header[$column] ?? '');
            if ($type === 'b' && (! str_starts_with($key, 'attr.') || ! in_array($value, ['0', '1'], true))) {
                return null;
            }
            if (in_array($key, $textColumns, true) && in_array($type, ['', 'n'], true)) {
                return null; // Preserve leading zeroes and full identifier precision.
            }
            if (in_array($key, $dateColumns, true) && in_array($type, ['', 'n'], true)) {
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
