<?php

namespace Tests\Support;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DeadlockWorker
{
    public static function run(int $branch, string $barrier): void
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        if (getenv('AMS_TEST_MYSQL') !== '1' || ! str_ends_with(config('database.connections.mysql.database'), '_test')) {
            exit(2);
        }
        DB::beginTransaction();
        try {
            // A heavier transaction makes the registration the InnoDB deadlock victim.
            $positions = [];
            for ($number = 0; $number < 100; $number++) {
                $positions[] = ['code' => 'DEADLOCK-'.$branch.'-'.$number, 'name' => 'Synthetic'];
            }
            DB::table('positions')->insert($positions);
            DB::table('branches')->where('id', $branch)->lockForUpdate()->sole();
            file_put_contents($barrier.'.ready', 'ready');
            $deadline = microtime(true) + 10;
            while (! is_file($barrier.'.inserted')) {
                if (microtime(true) > $deadline) {
                    throw new RuntimeException('Registration did not reach the deadlock barrier.');
                }
                usleep(10000);
            }
            DB::table('asset_number_sequences')->where('key', 'AST')->lockForUpdate()->sole();
            echo 'deadlock-released';
        } finally {
            DB::rollBack();
        }
    }
}
