<?php

namespace App\Console\Commands;

use App\Actions\Identity\PasswordResets;
use App\Models\User;
use Illuminate\Console\Command;

class IssuePasswordCode extends Command
{
    protected $signature = 'ams:password-code {nik : NIK of the employee requesting the code}';

    protected $description = 'Issue a one-time reset code after verifying a no-email employee in person';

    public function handle(PasswordResets $resets): int
    {
        if (! $this->confirm('Has the employee identity been verified and is private handover ready?')) {
            return self::FAILURE;
        }

        $account = User::query()->whereHas('employee', fn ($query) => $query->where('nik', trim($this->argument('nik'))))->first();
        $code = $account ? $resets->issueHandover($account) : null;

        if ($code === null) {
            $this->error('No eligible pending handover.');

            return self::FAILURE;
        }

        $this->line($code);

        return self::SUCCESS;
    }
}
