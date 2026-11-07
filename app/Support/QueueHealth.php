<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class QueueHealth
{
    public const KEY = 'system.queue-health';

    public static function check(): void
    {
        $now = now();
        $connection = config('queue.connections.database');
        $jobs = DB::connection($connection['connection'])->table($connection['table']);
        $overdue = $jobs->where('available_at', '<=', $now->timestamp - 300)
            ->where(fn ($query) => $query->whereNull('reserved_at')->orWhere('reserved_at', '<=', $now->timestamp - (int) $connection['retry_after']))->count();
        Cache::put(self::KEY, [
            'observed_at' => $now->toDateTimeString(),
            'overdue' => $overdue,
            'failed' => DB::table('failed_jobs')->where('failed_at', '>=', $now->copy()->subDay())->count(),
        ], now()->addMinutes(3));
    }
}
