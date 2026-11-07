<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

final class IdentityAttempts
{
    public static function check(string $bucket, string $identity, ?string $ip, Closure $attempt): mixed
    {
        // Keep identifier counters in one expiring record per IP, including unknown identifiers.
        $key = 'identity:'.$bucket.':'.hash('sha256', (string) $ip);
        $member = hash('sha256', $identity);

        try {
            return Cache::lock($key.':lock', 10)->block(3, function () use ($key, $member, $attempt) {
                $now = now()->timestamp;
                $counters = array_filter(Cache::get($key, []), fn ($counter) => $counter['expires'] > $now);
                $counter = $counters[$member] ?? ['count' => 0, 'expires' => $now + 60];
                abort_if($counter['count'] >= 5, 429, __('iam.throttled'), [
                    'Retry-After' => (string) ($counter['expires'] - $now),
                ]);

                $result = $attempt();

                if ($result === null) {
                    $counter['count']++;
                    $counters[$member] = $counter;
                } else {
                    unset($counters[$member]);
                }
                Cache::put($key, $counters, 60);

                return $result;
            });
        } catch (LockTimeoutException) {
            abort(429, __('iam.throttled'), ['Retry-After' => '3']);
        }
    }
}
