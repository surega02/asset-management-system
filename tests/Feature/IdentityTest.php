<?php

namespace Tests\Feature;

use App\Actions\Identity\PasswordResets;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\PasswordResetCode;
use App\Queries\OrganizationScope;
use App\Support\IdentityAccess;
use Database\Seeders\IdentitySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class IdentityTest extends TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as private startMysqlTransaction;
    }

    private const PASSWORD = 'a-test-password-2468';

    public function beginDatabaseTransaction(): void
    {
        if (getenv('AMS_TEST_MYSQL') !== '1') {
            $this->markTestSkipped('Set AMS_TEST_MYSQL=1 with a migrated MySQL test database.');
        }

        $this->assertSame('mysql', config('database.default'));
        $this->assertStringEndsWith('_test', config('database.connections.mysql.database'));
        $this->startMysqlTransaction();
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['hashing.bcrypt.rounds' => 4]);
        (new IdentitySeeder)->run();
    }

    public function test_t014_nik_login_works_without_email_and_guest_employee_or_inactive_account_is_denied(): void
    {
        $account = $this->account();
        $inactive = $this->account(['is_active' => false]);
        $employeeWithoutAccount = $this->employee();

        $this->get('/workspace')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee(__('iam.identifier'));
        foreach ([$inactive->employee, $employeeWithoutAccount] as $employee) {
            $this->from('/login')->post('/login', ['login' => $employee->nik, 'password' => self::PASSWORD])
                ->assertSessionHasErrors('login');
            $this->assertGuest();
        }

        $this->post('/login', ['login' => $account->employee->nik, 'password' => self::PASSWORD])->assertRedirect('/workspace');
        $this->assertAuthenticatedAs($account);
        $this->get('/workspace')->assertOk()->assertSee($account->employee->name);
        $this->post('/language', ['locale' => 'zh-CN'])->assertRedirect('/workspace');
        $this->postJson('/preferences/theme', ['theme' => 'dark'])->assertNoContent();
        $this->assertSame('zh-CN', $account->fresh()->locale);
        $this->assertSame('dark', $account->fresh()->theme);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_login_rate_limit_is_shared_across_email_and_nik_aliases(): void
    {
        $account = $this->account(['email' => ' M02@Example.test ']);
        $this->assertSame('m02@example.test', $account->fresh()->email);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/login')->post('/login', [
                'login' => $attempt % 2 ? 'M02@EXAMPLE.TEST' : $account->employee->nik,
                'password' => 'incorrect',
            ])->assertSessionHasErrors('login');
        }

        $this->post('/login', ['login' => $account->email, 'password' => self::PASSWORD])
            ->assertTooManyRequests()->assertHeader('Retry-After');
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post('/login', ['login' => ' M02@EXAMPLE.TEST ', 'password' => self::PASSWORD])->assertRedirect('/workspace');
    }

    public function test_unknown_identifiers_share_one_limiter_bucket_per_ip(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['login' => 'unknown-'.$attempt, 'password' => 'invalid-password'])
                ->assertSessionHasErrors('login');
        }
        $this->post('/login', ['login' => 'another-unknown', 'password' => 'invalid-password'])->assertStatus(429);
    }

    public function test_employee_nik_is_immutable_and_database_rejects_ambiguous_or_duplicate_identifiers(): void
    {
        $first = $this->account(['email' => 'First@Example.test']);
        $second = $this->account();
        $third = $this->account();
        $this->assertNull($second->email);
        $this->assertNull($third->email);

        $this->assertDatabaseRejects(fn () => $this->employee(['nik' => strtolower($first->employee->nik)]));
        $this->assertDatabaseRejects(fn () => $this->employee(['nik' => 'ambiguous@example.test']));
        $this->assertDatabaseRejects(fn () => $this->account(['email' => 'FIRST@example.test']));
        $this->assertDatabaseRejects(fn () => $first->employee->user()->create(['password' => self::PASSWORD]));

        $this->expectException(\LogicException::class);
        $first->employee->update(['nik' => 'replacement']);
    }

    public function test_admin_requests_reset_without_code_and_verified_handover_is_single_use(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $this->as($admin)->post('/admin/users', ['nik' => $employee->nik])->assertRedirect();
        $account = $employee->user()->firstOrFail();

        $this->post('/admin/users/'.$account->id.'/password-reset')->assertRedirect();
        $this->assertDatabaseHas('password_reset_tokens', ['user_id' => $account->id, 'token_hash' => null]);
        $code = app(PasswordResets::class)->issueHandover($account);
        $this->assertNotNull($code);
        $this->assertNull(app(PasswordResets::class)->issueHandover($account));

        $this->assertDatabaseHas('password_reset_tokens', ['user_id' => $account->id, 'token_hash' => hash('sha256', $code)]);
        Auth::logout();
        $this->post('/reset-password', ['reset_code' => $code, 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])
            ->assertRedirect('/login');
        $this->assertTrue(Hash::check(self::PASSWORD, $account->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['user_id' => $account->id]);
        $this->from('/reset-password')->post('/reset-password', ['reset_code' => $code, 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])
            ->assertSessionHasErrors('reset_code');
        $this->assertNull(session()->getOldInput('reset_code'));
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'subject_id' => $account->id, 'event' => 'account.password_reset_requested']);
        $this->assertDatabaseHas('audit_logs', ['subject_id' => $account->id, 'event' => 'account.password_reset_completed']);
        $this->assertStringNotContainsString($code, DB::table('audit_logs')->get()->toJson());
        $this->assertStringNotContainsString(self::PASSWORD, DB::table('audit_logs')->get()->toJson());
    }

    public function test_email_reset_is_delivered_after_commit_and_expired_codes_are_denied(): void
    {
        Notification::fake();
        $account = $this->account(['email' => 'reset@example.test']);
        $code = null;

        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'RESET@example.test'])
            ->assertRedirect('/forgot-password')->assertSessionHas('status', __('iam.reset_requested'));
        Notification::assertSentOnDemand(PasswordResetCode::class, function ($notification, $channels, $notifiable) use (&$code): bool {
            $code = $notification->toMail($notifiable)->introLines[1];

            return $channels === ['mail'] && $notifiable->routes['mail'] === 'reset@example.test';
        });
        $this->assertDatabaseHas('password_reset_tokens', ['user_id' => $account->id, 'token_hash' => hash('sha256', $code)]);
        $this->travel(31)->minutes();
        $this->from('/reset-password')->post('/reset-password', ['reset_code' => $code, 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])
            ->assertSessionHasErrors('reset_code');
    }

    public function test_password_reset_queue_payload_is_encrypted_and_stale_delivery_is_rejected(): void
    {
        config(['mail.default' => 'array']);
        $account = $this->account(['email' => 'queued@example.test']);
        $previousJob = DB::table('jobs')->max('id') ?? 0;
        $this->from('/forgot-password')->post('/forgot-password', ['email' => $account->email])->assertRedirect();
        $payload = DB::table('jobs')->where('id', '>', $previousJob)->value('payload');
        $this->assertIsString($payload);
        $this->assertStringNotContainsString($account->email, $payload);

        $envelope = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        $job = unserialize(Crypt::decrypt($envelope['data']['command']));
        $notifiable = $job->notifiables->first();
        $this->assertTrue($job->notification->shouldSend($notifiable, 'mail'));
        $this->assertSame(0, Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 1]));
        $this->assertCount(1, Mail::mailer('array')->getSymfonyTransport()->messages());
        $this->assertSame($account->email, Mail::mailer('array')->getSymfonyTransport()->messages()->first()->getEnvelope()->getRecipients()[0]->getAddress());
        $this->travel(31)->minutes();
        $this->assertFalse($job->notification->shouldSend($notifiable, 'mail'));
    }

    public function test_two_factor_enrollment_replay_and_recovery_codes(): void
    {
        $account = $this->account();
        $this->as($account)->post('/security/enroll', ['current_password' => self::PASSWORD])->assertRedirect('/security');
        $secret = session('auth.enrollment.secret');
        $this->get('/security')->assertOk()->assertSee('data:image/svg+xml;base64,', false);
        $otp = app(Google2FA::class)->getCurrentOtp($secret);

        $this->post('/security/confirm', ['current_password' => self::PASSWORD, 'code' => $otp])->assertRedirect('/security');
        $codes = session('new_recovery_codes');
        $this->assertCount(8, $codes);
        $this->assertNotSame($secret, DB::table('users')->where('id', $account->id)->value('two_factor_secret'));
        $this->assertContains(hash('sha256', $codes[0]), $account->fresh()->two_factor_recovery_codes);
        $this->get('/security')->assertOk()->assertSee($codes[0])->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/security')->assertOk()->assertDontSee($codes[0]);

        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['login' => $account->employee->nik, 'password' => self::PASSWORD])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $this->from('/two-factor-challenge')->post('/two-factor-challenge', ['code' => $otp])->assertSessionHasErrors('code');
        $this->post('/two-factor-challenge', ['code' => $codes[0]])->assertRedirect('/workspace');
        $this->assertAuthenticatedAs($account);
        $this->assertNotContains(hash('sha256', $codes[0]), $account->fresh()->two_factor_recovery_codes);

        $this->post('/logout');
        $this->post('/login', ['login' => $account->employee->nik, 'password' => self::PASSWORD]);
        $this->from('/two-factor-challenge')->post('/two-factor-challenge', ['code' => $codes[0]])->assertSessionHasErrors('code');
        $this->assertGuest();

        $totp = app(Google2FA::class);
        $nextCode = $totp->oathTotp($secret, $account->fresh()->two_factor_last_step + 1);
        $this->post('/two-factor-challenge', ['code' => $nextCode])->assertRedirect('/workspace');
        $this->post('/security/recovery-codes', ['current_password' => self::PASSWORD, 'code' => $codes[1]])->assertRedirect('/security');
        $replacement = session('new_recovery_codes');
        $this->assertCount(8, $replacement);
        $this->assertNotContains(hash('sha256', $codes[2]), $account->fresh()->two_factor_recovery_codes);
        $this->delete('/security/two-factor', ['current_password' => self::PASSWORD, 'code' => $replacement[0]])->assertRedirect('/security');
        $this->assertNull($account->fresh()->two_factor_secret);
    }

    public function test_permission_scope_and_direct_url_denial_are_independent(): void
    {
        $actor = $this->account();
        $within = $this->account(['employee_id' => $this->employee(['department_id' => $actor->employee->department_id])->id]);
        $outside = $this->account();
        Permission::findOrCreate('view', 'web');
        Permission::findOrCreate('update-global-settings', 'web');
        $actor->givePermissionTo('users.view', 'users.manage', 'settings.manage', 'view', 'update-global-settings');
        $this->as($actor)->get('/admin/users')->assertOk()->assertDontSee($within->employee->name);
        $this->get('/admin/users/'.$within->id)->assertForbidden();

        DB::table('user_data_scopes')->insert([
            'user_id' => $actor->id,
            'scope_type' => 'department',
            'department_id' => $actor->employee->department_id,
        ]);

        $this->get('/admin/users')->assertOk()->assertSee($within->employee->name)->assertDontSee($outside->employee->name);
        $this->get('/admin/users/'.$within->id)->assertOk();
        $this->get('/admin/users/'.$outside->id)->assertForbidden();
        $this->put('/admin/users/'.$outside->id, ['is_active' => 0])->assertForbidden();
        $this->assertFalse(OrganizationScope::branches($actor)->exists());
        $this->assertFalse(Gate::forUser($actor)->allows('update-global-settings'));

        $division = DB::table('divisions')->join('departments', 'departments.division_id', '=', 'divisions.id')
            ->where('departments.id', $actor->employee->department_id)->first(['divisions.id', 'divisions.branch_id']);
        DB::table('user_data_scopes')->insert(['user_id' => $actor->id, 'scope_type' => 'division', 'division_id' => $division->id]);
        $this->assertTrue(OrganizationScope::divisions($actor)->where('id', $division->id)->exists());
        $this->assertFalse(OrganizationScope::branches($actor)->exists());
        DB::table('user_data_scopes')->insert(['user_id' => $actor->id, 'scope_type' => 'branch', 'branch_id' => $division->branch_id]);
        $this->assertTrue(OrganizationScope::branches($actor)->where('id', $division->branch_id)->exists());
        $this->get('/admin/users/'.$outside->id)->assertForbidden();

        $actor->revokePermissionTo('users.view');
        $this->as($actor->fresh())->get('/admin/users/'.$within->id)->assertForbidden();
        $this->assertTrue(Gate::forUser($this->admin())->allows('update-global-settings'));
    }

    public function test_role_permission_and_scope_changes_are_audited_and_invalid_scope_rolls_back(): void
    {
        $admin = $this->admin();
        $account = $this->account();
        $this->as($admin)->get('/admin/access')->assertOk();
        $this->get('/admin/access/roles/new')->assertOk();
        $this->post('/admin/access/permissions', ['name' => 'assets.test'])->assertRedirect('/admin/access');
        $permission = Permission::findByName('assets.test');
        $this->post('/admin/access/roles', ['name' => 'auditor-test', 'permissions' => [$permission->id]])->assertRedirect('/admin/access');
        $role = Role::findByName('auditor-test');
        $this->get('/admin/access/roles/'.$role->id)->assertOk()->assertSee('assets.test');

        $this->from('/admin/users/'.$account->id)->put('/admin/users/'.$account->id.'/access', [
            'roles' => [$role->id],
            'scopes' => ['department:9999999'],
        ])->assertSessionHasErrors('scopes');
        $this->assertFalse($account->fresh()->hasRole($role));
        $this->put('/admin/users/'.$account->id.'/access', [
            'roles' => [$role->id],
            'scopes' => ['department:'.$account->employee->department_id],
        ])->assertRedirect();
        $this->assertTrue($account->fresh()->checkPermissionTo('assets.test'));
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'event' => 'role.saved', 'subject_id' => $role->id]);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'event' => 'account.access_changed', 'subject_id' => $account->id]);
        $this->assertDatabaseHas('user_data_scopes', ['user_id' => $account->id, 'department_id' => $account->employee->department_id]);
        $this->assertDatabaseRejects(fn () => DB::table('user_data_scopes')->insert(['user_id' => $account->id, 'scope_type' => 'branch']));

        $this->put('/admin/access/roles/'.$role->id, ['name' => 'reviewer-test'])->assertRedirect('/admin/access');
        $this->assertFalse($account->fresh()->checkPermissionTo('assets.test'));
        $this->put('/admin/access/permissions/'.$permission->id, ['name' => 'assets.review-test'])->assertRedirect('/admin/access');
        $this->delete('/admin/access/permissions/'.$permission->id)->assertRedirect('/admin/access');
        $this->put('/admin/users/'.$account->id.'/access')->assertRedirect();
        $this->delete('/admin/access/roles/'.$role->id)->assertRedirect('/admin/access');
        $this->assertDatabaseHas('audit_logs', ['event' => 'role.deleted', 'subject_id' => $role->id]);
    }

    public function test_deactivation_revokes_existing_session_even_after_reactivation(): void
    {
        $admin = $this->admin();
        $account = $this->account();
        $oldVersion = $account->auth_version;

        $this->as($admin)->put('/admin/users/'.$account->id, ['is_active' => 0])->assertRedirect();
        $this->put('/admin/users/'.$account->id, ['is_active' => 1])->assertRedirect();
        $this->assertTrue($account->fresh()->is_active);

        $this->as($account->fresh())->withSession(['auth.version' => $oldVersion])
            ->get('/workspace')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_database_session_expires_after_thirty_minutes_idle(): void
    {
        config(['session.driver' => 'database']);
        $account = $this->account();
        $this->assertSame(30, config('session.lifetime'));
        $this->post('/login', ['login' => $account->employee->nik, 'password' => self::PASSWORD])->assertRedirect('/workspace');
        $sessionId = session()->getId();
        $this->assertDatabaseHas('sessions', ['id' => $sessionId, 'user_id' => $account->id]);

        $this->travel(31)->minutes();
        Auth::forgetGuards();
        session()->flush();
        $this->withCookie(config('session.cookie'), $sessionId)->get('/workspace')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_bootstrap_command_provisions_only_the_first_super_admin(): void
    {
        $employee = $this->employee();
        $other = $this->employee();

        $this->assertSame(0, Artisan::call('ams:bootstrap-admin', ['nik' => $employee->nik]));
        $account = $employee->user()->firstOrFail();
        $this->assertTrue($account->hasRole(IdentityAccess::SUPER_ADMIN));
        $this->assertDatabaseHas('password_reset_tokens', ['user_id' => $account->id, 'token_hash' => null]);
        $this->assertSame(1, Artisan::call('ams:bootstrap-admin', ['nik' => $other->nik]));
        $this->assertFalse($other->user()->exists());
    }

    private function assertDatabaseRejects(callable $write): void
    {
        try {
            $write();
            $this->fail('MySQL accepted an invalid write.');
        } catch (QueryException $exception) {
            $this->assertContains((int) $exception->errorInfo[1], [1062, 3819]);
        }
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth.version' => $user->auth_version]);
    }

    private function admin(): User
    {
        $account = $this->account();
        $account->assignRole(IdentityAccess::SUPER_ADMIN);

        return $account;
    }

    private function account(array $attributes = []): User
    {
        return User::query()->create($attributes + ['employee_id' => $this->employee()->id, 'password' => self::PASSWORD]);
    }

    private function employee(array $attributes = []): Employee
    {
        $code = Str::lower(Str::random(12));
        $branch = DB::table('branches')->insertGetId(['code' => $code, 'name' => 'Branch '.$code]);
        $division = DB::table('divisions')->insertGetId(['branch_id' => $branch, 'code' => $code, 'name' => 'Division '.$code]);
        $department = DB::table('departments')->insertGetId(['division_id' => $division, 'code' => $code, 'name' => 'Department '.$code]);
        $location = DB::table('locations')->insertGetId(['branch_id' => $branch, 'code' => $code, 'name' => 'Location '.$code]);
        $position = DB::table('positions')->insertGetId(['code' => $code, 'name' => 'Position '.$code]);

        return Employee::query()->create($attributes + [
            'nik' => 'EMP-'.$code,
            'name' => 'Employee '.$code,
            'department_id' => $department,
            'position_id' => $position,
            'home_location_id' => $location,
            'joined_at' => '2024-01-01',
            'employment_status' => 'active',
        ]);
    }
}
