<?php

namespace Tests\Feature;

use App\Actions\Employees\ReadEmployeeSpreadsheet;
use App\Models\Employee;
use App\Models\User;
use App\Queries\PeopleScope;
use App\Support\IdentityAccess;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\OrganizationFixtures as Fixture;
use ZipArchive;

class PeopleWorkflowsTest extends TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as private startMysqlTransaction;
    }

    public function beginDatabaseTransaction(): void
    {
        if (getenv('AMS_TEST_MYSQL') !== '1') {
            $this->markTestSkipped('Set AMS_TEST_MYSQL=1 with a migrated MySQL test database.');
        }
        $this->assertSame('mysql', config('database.default'));
        $this->assertStringEndsWith('_test', config('database.connections.mysql.database'));
        $this->startMysqlTransaction();
        (new IdentitySeeder)->run();
    }

    public function test_emp_001_and_iam_001_registration_preserves_permanent_nik_and_does_not_create_an_account(): void
    {
        $tree = Fixture::tree();
        $foreign = Fixture::tree();
        $actor = Fixture::actor($tree, 'department', ['employees.view', 'employees.manage']);
        $other = Fixture::actor($foreign)->employee;
        $this->as($actor)->get('/employees/new')->assertOk();
        $this->post('/employees', $this->employeeData($foreign, 'OUTSIDE'))->assertForbidden();
        $this->post('/employees', $this->employeeData($tree, 'MISPLACED', ['home_location_id' => $foreign['location']]))
            ->assertSessionHasErrors('home_location_id');
        $this->post('/employees', $this->employeeData($tree, '00042'))->assertRedirect();
        $employee = Employee::query()->where('nik', '00042')->sole();
        $this->assertNull($employee->user);
        $this->get('/employees?q=00042')->assertOk()->assertSee('00042')->assertDontSee($other->nik);
        $this->get('/employees/'.$other->id)->assertForbidden();
        $this->get('/employees/'.$employee->id)->assertOk()->assertSee(__('people.tenure'));
        $this->put('/employees/'.$employee->id, $this->profile($employee, ['nik' => 'FORGED']))->assertSessionHasErrors('nik');
        $this->put('/employees/'.$employee->id, $this->profile($employee, ['employment_status' => 'inactive']))->assertRedirect();
        $this->post('/employees', $this->employeeData($tree, '00042'))->assertSessionHasErrors('nik');
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'nik' => '00042', 'employment_status' => 'inactive']);
        $this->assertDatabaseMissing('users', ['employee_id' => $employee->id]);
    }

    public function test_emp_002_transfer_requires_source_and_destination_scope_and_audits_both_organizations(): void
    {
        $source = Fixture::tree();
        $dest = Fixture::tree();
        $employee = Fixture::actor($source)->employee;
        $actor = Fixture::actor($source, 'branch', ['employees.view', 'employees.manage']);
        $data = ['department_id' => $dest['department'], 'home_location_id' => $dest['location'], 'reason' => 'Rotasi antar cabang'];
        $this->as($actor)->post('/employees/'.$employee->id.'/transfer', $data)->assertForbidden();
        $this->as(Fixture::actor($dest, 'branch', ['employees.view', 'employees.manage']))
            ->post('/employees/'.$employee->id.'/transfer', $data)->assertForbidden();
        $this->as(Fixture::actor($source)->assignRole(IdentityAccess::SUPER_ADMIN));
        $this->post('/employees/'.$employee->id.'/transfer', ['reason' => ''] + $data)->assertSessionHasErrors('reason', errorBag: 'transfer');
        $this->post('/employees/'.$employee->id.'/transfer', $data)->assertRedirect();
        $this->assertDatabaseHas('employee_movements', [
            'employee_id' => $employee->id, 'from_branch_id' => $source['branch'], 'to_branch_id' => $dest['branch'],
            'from_division_id' => $source['division'], 'to_division_id' => $dest['division'],
            'from_department_id' => $source['department'], 'to_department_id' => $dest['department'], 'reason' => $data['reason'],
        ]);
        $audit = DB::table('audit_logs')->where('subject_id', $employee->id)->where('event', 'employee.transferred')->sole();
        $this->assertSame($source['branch'], json_decode($audit->old_values, true)['branch_id']);
        $this->assertSame($dest['branch'], json_decode($audit->new_values, true)['branch_id']);
        $this->get('/employees/'.$employee->id)->assertOk()->assertSee($data['reason']);
        $this->as($actor)->get('/employees/'.$employee->id)->assertForbidden();
    }

    public function test_t008_acting_wins_inclusively_and_same_kind_cannot_overlap(): void
    {
        $tree = Fixture::tree();
        $definitive = Fixture::actor($tree)->employee;
        $acting = Fixture::actor($tree)->employee;
        $admin = Fixture::actor($tree)->assignRole(IdentityAccess::SUPER_ADMIN);
        $this->as($admin)->get('/appointments/new')->assertOk();

        $input = ['employee_id' => $definitive->id, 'role_kind' => 'hod', 'scope_key' => 'department:'.$tree['department'],
            'appointment_kind' => 'definitive', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31'];
        $cover = ['employee_id' => $acting->id, 'appointment_kind' => 'acting', 'starts_on' => '2026-05-01', 'ends_on' => '2026-05-31'] + $input;
        $this->post('/appointments', $input)->assertRedirect();
        $this->post('/appointments', $cover)->assertRedirect();
        $this->post('/appointments', ['starts_on' => '2026-05-31', 'ends_on' => '2026-06-20'] + $cover)->assertSessionHasErrors('starts_on');
        $this->post('/appointments', ['starts_on' => '2026-12-31', 'ends_on' => '2027-01-01'] + $input)->assertSessionHasErrors('starts_on');
        $this->post('/appointments', ['role_kind' => 'director'] + $input)->assertSessionHasErrors('scope_type');
        $this->assertSame($acting->id, PeopleScope::effectiveOfficer($admin, 'hod', 'department', $tree['department'], '2026-05-31')->employee_id);
        $this->assertSame($definitive->id, PeopleScope::effectiveOfficer($admin, 'hod', 'department', $tree['department'], '2026-06-01')->employee_id);
        $this->get('/appointments?date=2026-05-31')->assertOk()->assertSee(__('people.effective'));
        $this->post('/appointments', ['role_kind' => 'director', 'scope_key' => 'company', 'ends_on' => null] + $input)->assertRedirect();
        $director = DB::table('structural_appointments')->where('scope_type', 'company')->sole();
        $this->as(Fixture::actor($tree, 'branch', ['appointments.view', 'appointments.manage']))
            ->put('/appointments/'.$director->id, ['ends_on' => '2026-02-01', 'reason' => 'Outside scope'])->assertForbidden();
        $this->as($admin)->put('/appointments/'.$director->id, ['ends_on' => '2026-02-01', 'reason' => 'Akhir periode'])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['event' => 'appointment.period_changed', 'subject_id' => $director->id, 'reason' => 'Akhir periode']);
    }

    public function test_csv_import_is_scoped_partial_resumable_and_success_is_terminal(): void
    {
        $tree = Fixture::tree();
        $other = Fixture::tree();
        $user = Fixture::actor($tree, 'branch', ['employees.view', 'employees.manage', 'employees.import']);
        $header = implode(',', ReadEmployeeSpreadsheet::COLUMNS);
        $this->as($user)->get('/employees/import')->assertOk();
        $this->get('/employees/import/template')->assertOk()->assertSee($header);
        $csv = $header."\n".implode(',', $this->employeeData($tree, '00091'))."\n"
            .implode(',', $this->employeeData($other, 'OUTSIDE'))."\n"
            .implode(',', $this->employeeData($tree, 'BAD-DATE', ['joined_at' => '2026-02-30']))."\n";
        $this->post('/employees/import', ['file' => UploadedFile::fake()->createWithContent('employees.csv', $csv)])->assertRedirect();
        $batch = DB::table('employee_imports')->where('user_id', $user->id)->sole();
        $this->assertDatabaseMissing('employees', ['nik' => '00091']);
        $this->get('/employees/import/'.$batch->id)->assertOk()->assertSee(__('people.process_rows'));
        $this->post('/employees/import/'.$batch->id.'/process')->assertRedirect();
        $employee = Employee::query()->where('nik', '00091')->sole();
        $this->assertDatabaseHas('employee_import_rows', ['employee_import_id' => $batch->id, 'line' => 2, 'status' => 'succeeded', 'employee_id' => $employee->id, 'payload' => null, 'attempts' => 1]);
        $this->assertDatabaseHas('employee_import_rows', ['employee_import_id' => $batch->id, 'line' => 3, 'status' => 'failed', 'error_key' => 'import_scope']);
        $this->assertDatabaseHas('employee_import_rows', ['employee_import_id' => $batch->id, 'line' => 4, 'status' => 'failed', 'error_key' => 'import_invalid_date']);
        $this->post('/employees/import', ['file' => UploadedFile::fake()->createWithContent('employees.csv', $csv)])->assertRedirect('/employees/import/'.$batch->id);
        $this->post('/employees/import/'.$batch->id.'/process', ['retry' => 1])->assertRedirect();
        $this->assertSame(1, DB::table('audit_logs')->where('subject_id', $employee->id)->where('event', 'employee.created')->count());
        $this->assertDatabaseHas('employee_import_rows', ['employee_import_id' => $batch->id, 'line' => 3, 'attempts' => 2]);
        $this->as(Fixture::actor($tree, 'branch', ['employees.view', 'employees.manage', 'employees.import']))
            ->get('/employees/import/'.$batch->id)->assertNotFound();
        $this->as(Fixture::actor($tree, 'branch', ['employees.view', 'employees.manage']))
            ->post('/employees/import/'.$batch->id.'/process')->assertForbidden();
    }

    public function test_xlsx_accepts_literal_cells_and_rejects_formula_rows_and_csv_limit(): void
    {
        $tree = Fixture::tree();
        $this->as(Fixture::actor($tree)->assignRole(IdentityAccess::SUPER_ADMIN));
        $path = storage_path('framework/cache/'.Str::uuid().'.xlsx');
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL));
        $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>');
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Employees" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $content = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ([1 => ReadEmployeeSpreadsheet::COLUMNS, 2 => array_values($this->employeeData($tree, '00012'))] as $line => $values) {
            $content .= '<row r="'.$line.'">';
            foreach ($values as $index => $value) {
                $content .= '<c r="'.chr(65 + $index).$line.'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>';
            }
            $content .= '</row>';
        }
        $content .= '<row r="3"><c r="A3"><f>1+1</f><v>2</v></c></row></sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $content);
        $this->assertTrue($zip->close());

        try {
            $this->post('/employees/import', ['file' => new UploadedFile($path, 'employees.xlsx', test: true)])->assertRedirect();
        } finally {
            unlink($path);
        }
        $batch = DB::table('employee_imports')->where('user_id', auth()->id())->sole();
        $this->post('/employees/import/'.$batch->id.'/process')->assertRedirect();
        $this->assertDatabaseHas('employees', ['nik' => '00012']);
        $this->assertDatabaseHas('employee_import_rows', ['employee_import_id' => $batch->id, 'line' => 3, 'error_key' => 'import_row_invalid']);
        $csv = implode(',', ReadEmployeeSpreadsheet::COLUMNS)."\n".str_repeat(implode(',', $this->employeeData($tree, 'LIMIT'))."\n", 1001);
        $this->post('/employees/import', ['file' => UploadedFile::fake()->createWithContent('limit.csv', $csv)])->assertSessionHasErrors('file');
    }

    private function employeeData(array $tree, string $nik, array $overrides = []): array
    {
        return array_replace(['nik' => $nik, 'name' => 'Karyawan '.$nik, 'department_id' => $tree['department'], 'position_id' => $tree['position'],
            'home_location_id' => $tree['location'], 'joined_at' => '2020-01-15', 'employment_status' => 'active'], $overrides);
    }

    private function profile(Employee $employee, array $overrides = []): array
    {
        return $overrides + [
            'name' => $employee->name, 'position_id' => $employee->position_id, 'home_location_id' => $employee->home_location_id,
            'joined_at' => $employee->joined_at->toDateString(), 'employment_status' => 'active',
        ];
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth.version' => $user->auth_version]);
    }
}
