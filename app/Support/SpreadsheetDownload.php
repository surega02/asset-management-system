<?php

namespace App\Support;

use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use XMLWriter;
use ZipArchive;

final class SpreadsheetDownload
{
    public static function csv(string $name, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($stream, array_map(self::safeCell(...), $row), escape: '');
            }
            fclose($stream);
        }, $name.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function xlsxTemplate(string $name, array $header): Response
    {
        $path = self::workbook([$header]);
        try {
            $content = file_get_contents($path);
            abort_if($content === false, 500);

            return response($content, headers: [
                'Content-Type' => self::xlsxMime(),
                'Content-Disposition' => 'attachment; filename="'.$name.'.xlsx"',
            ]);
        } finally {
            unlink($path);
        }
    }

    public static function xlsx(string $name, iterable $rows): BinaryFileResponse
    {
        $path = self::workbook($rows);
        clearstatcache(true, $path);

        return response()->download($path, $name.'.xlsx', ['Content-Type' => self::xlsxMime()])->deleteFileAfterSend(true);
    }

    private static function xlsxMime(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    private static function workbook(iterable $rows): string
    {
        if (! class_exists(ZipArchive::class) || ! class_exists(XMLWriter::class)) {
            throw ValidationException::withMessages(['file' => __('people.import_extension')]);
        }
        $path = $sheet = false;
        $complete = false;
        try {
            $path = tempnam(sys_get_temp_dir(), 'ams-xlsx-');
            $sheet = tempnam(sys_get_temp_dir(), 'ams-sheet-');
            abort_if($path === false || $sheet === false, 500);
            self::writeSheet($sheet, $rows);
            $zip = new ZipArchive;
            abort_unless($zip->open($path, ZipArchive::OVERWRITE) === true, 500);
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $zip->addFromString('_rels/.rels', self::relationship('officeDocument', 'xl/workbook.xml'));
            $zip->addFromString('xl/_rels/workbook.xml.rels', self::relationship('worksheet', 'worksheets/sheet1.xml'));
            $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
                .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="Assets" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $added = $zip->addFile($sheet, 'xl/worksheets/sheet1.xml');
            $saved = $zip->close();
            abort_unless($added && $saved, 500);
            $complete = true;

            return $path;
        } finally {
            if ($sheet !== false) {
                unlink($sheet);
            }
            if (! $complete && $path !== false) {
                unlink($path);
            }
        }
    }

    private static function writeSheet(string $path, iterable $rows): void
    {
        $xml = new XMLWriter;
        abort_unless($xml->openUri($path), 500);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElementNS(null, 'worksheet', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $xml->startElement('sheetData');
        $line = 0;
        foreach ($rows as $row) {
            abort_if(++$line > 1048576, 422, __('reports.xlsx_limit'));
            $xml->startElement('row');
            $xml->writeAttribute('r', (string) $line);
            foreach (array_values($row) as $index => $value) {
                $column = '';
                for ($position = $index + 1; $position > 0; $position = intdiv($position - 1, 26)) {
                    $column = chr(65 + ($position - 1) % 26).$column;
                }
                $xml->startElement('c');
                $xml->writeAttribute('r', $column.$line);
                if (is_int($value)) {
                    $xml->writeElement('v', (string) $value);
                } else {
                    $xml->writeAttribute('t', 'inlineStr');
                    $xml->startElement('is');
                    $xml->startElement('t');
                    $xml->writeAttribute('xml:space', 'preserve');
                    $xml->text(preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string) $value) ?? '');
                    $xml->endElement();
                    $xml->endElement();
                }
                $xml->endElement();
            }
            $xml->endElement();
        }
        $xml->endDocument();
        $xml->flush();
    }

    private static function relationship(string $type, string $target): string
    {
        return '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/'.$type.'" Target="'.$target.'"/>'
            .'</Relationships>';
    }

    private static function safeCell(mixed $cell): string
    {
        $text = (string) $cell;

        return preg_match('/\A(?:[\t\r\n]|[\s\p{Z}\p{Cc}\p{Cf}]*[=+\-@])/u', $text) ? "'".$text : $text;
    }
}
