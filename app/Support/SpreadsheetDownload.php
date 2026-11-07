<?php

namespace App\Support;

use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
        if (! class_exists(ZipArchive::class)) {
            throw ValidationException::withMessages(['file' => __('people.import_extension')]);
        }
        $path = tempnam(sys_get_temp_dir(), 'ams-xlsx-');
        abort_if($path === false, 500);
        try {
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
            $cells = '';
            foreach ($header as $index => $value) {
                // This fixed template has fewer than 26 columns.
                $cells .= '<c r="'.chr(65 + $index).'1" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>';
            }
            $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                .'<sheetData><row r="1">'.$cells.'</row></sheetData></worksheet>');
            abort_unless($zip->close(), 500);
            $content = file_get_contents($path);
            abort_if($content === false, 500);

            return response($content, headers: [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$name.'.xlsx"',
            ]);
        } finally {
            unlink($path);
        }
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
