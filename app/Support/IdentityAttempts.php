<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

final class IdentityAttempts
{
    public static function check(string $bucket, string $identity, ?string $ip, Closure $attempt): mixed
    {
        $key = 'identity:'.$bucket.':'.hash('sha256', $identity.'|'.$ip);

        try {
            return Cache::lock($key.':lock', 10)->block(3, function () use ($key, $attempt) {
                abort_if(RateLimiter::tooManyAttempts($key, 5), 429, __('iam.throttled'), [
                    'Retry-After' => (string) RateLimiter::availableIn($key),
                ]);

                $result = $attempt();

                if ($result === null) {
                    RateLimiter::hit($key, 60);
                } else {
                    RateLimiter::clear($key);
                }

                return $result;
            });
        } catch (LockTimeoutException) {
            abort(429, __('iam.throttled'), ['Retry-After' => '3']);
        }
    }
}
