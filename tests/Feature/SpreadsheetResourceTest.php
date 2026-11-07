<?php

namespace Tests\Feature;

use App\Support\ReadSpreadsheet;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class SpreadsheetResourceTest extends TestCase
{
    public function test_csv_rejects_a_delimiter_bomb_before_allocating_its_cells(): void
    {
        $file = UploadedFile::fake()->createWithContent('bomb.csv', str_repeat(',', 9_000_000)."\n");
        $this->expectException(ValidationException::class);
        app(ReadSpreadsheet::class)->records($file, 64, [], []);
    }

    public function test_csv_preserves_multiline_escaped_quotes_bom_and_invalid_row_numbers(): void
    {
        $file = UploadedFile::fake()->createWithContent('valid.csv',
            "\xEF\xBB\xBFcode,name\r\nAST-1,\"First, \"\"quoted\"\"\nsecond\"\r\n"
            ."\nAST-2,normal\nAST-3,extra,column\nAST-4,last\n");
        $rows = app(ReadSpreadsheet::class)->records($file, 2, [], []);
        $this->assertSame([
            [1, ['code', 'name']], [2, ['AST-1', "First, \"quoted\"\nsecond"]],
            [4, ['AST-2', 'normal']], [5, null], [6, ['AST-4', 'last']],
        ], $rows);
    }

    public function test_shared_xlsx_strings_cannot_expand_a_small_archive_without_bounds(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ams-xlsx-');
        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path, ZipArchive::OVERWRITE));
            $zip->addFromString('[Content_Types].xml', '<Types><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>');
            $zip->addFromString('xl/workbook.xml', '<workbook xmlns:r="urn:r"><sheets><sheet name="Units" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships><Relationship Id="rId1" Type="urn:office/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
            $zip->addFromString('xl/sharedStrings.xml', '<sst><si><t>'.str_repeat('x', 8000).'</t></si></sst>');
            $rows = '';
            for ($line = 1; $line <= 1000; $line++) {
                $cells = '';
                foreach (range('A', 'P') as $column) {
                    $cells .= '<c r="'.$column.$line.'" t="s"><v>0</v></c>';
                }
                $rows .= '<row r="'.$line.'">'.$cells.'</row>';
            }
            $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet><sheetData>'.$rows.'</sheetData></worksheet>');
            $this->assertTrue($zip->close());
            $this->expectException(ValidationException::class);
            app(ReadSpreadsheet::class)->records(new UploadedFile($path, 'bomb.xlsx', test: true), 64, [], []);
        } finally {
            unlink($path);
        }
    }

    public function test_login_has_an_outer_ip_limit_even_when_identifiers_rotate(): void
    {
        $route = app('router')->getRoutes()->getByName('login.store');
        $this->assertContains('throttle:30,1', $route->gatherMiddleware());
        $this->assertContains('throttle:10,1', app('router')->getRoutes()->getByName('employees.import.store')->gatherMiddleware());
    }
}
