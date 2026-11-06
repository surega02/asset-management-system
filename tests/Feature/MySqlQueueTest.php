<?php

namespace Tests\Feature;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MySqlQueueTest extends TestCase
{
    public function test_mysql_migration_and_queue_commit_boundary(): void
    {
        if (getenv('AMS_TEST_MYSQL') !== '1') {
            $this->markTestSkipped('Set AMS_TEST_MYSQL=1 with a migrated MySQL test database.');
        }

        $connection = DB::connection();
        $this->assertSame('mysql', $connection->getDriverName());
        $this->assertStringEndsWith('_test', $connection->getDatabaseName());
        $this->assertSame('mysql', $connection->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME));
        foreach (['jobs', 'job_batches', 'failed_jobs'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing queue table: {$table}");
        }
        $this->assertSame('database', config('queue.default'));

        $queueName = 'm01-'.Str::uuid();
        $jobs = fn () => $connection->table('jobs')->where('queue', $queueName);

        try {
            $connection->beginTransaction();
            try {
                Queue::push(new FoundationQueueProbe, queue: $queueName);
                $this->assertFalse($jobs()->exists());
            } finally {
                $connection->rollBack();
            }
            $this->assertFalse($jobs()->exists());

            $connection->transaction(function () use ($jobs, $queueName): void {
                Queue::push(new FoundationQueueProbe, queue: $queueName);
                $this->assertFalse($jobs()->exists());
            });
            $this->assertSame(1, $jobs()->count());

            $exitCode = Artisan::call('queue:work', [
                'connection' => 'database',
                '--queue' => $queueName,
                '--once' => true,
                '--tries' => 1,
            ]);

            $this->assertSame(0, $exitCode, Artisan::output());
            $this->assertFalse($jobs()->exists());
            $this->assertFalse($connection->table('failed_jobs')->where('queue', $queueName)->exists());
        } finally {
            $jobs()->delete();
            $connection->table('failed_jobs')->where('queue', $queueName)->delete();
        }
    }
}

final class FoundationQueueProbe implements ShouldQueue
{
    public function handle(): void {}
}
