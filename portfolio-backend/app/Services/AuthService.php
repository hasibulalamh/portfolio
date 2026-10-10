<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthService
{
    /** Failed logins allowed from one IP/email pair before it is throttled. */
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    /** Most tokens one admin account may keep; the rest are pruned at login. */
    private const MAX_TOKENS_PER_USER = 5;

    /**
     * Verify credentials and mint a Sanctum token.
     *
     * @return array{token: string, user: User}|null  null when credentials are wrong
     */
    public function attemptLogin(string $email, string $password, string $ip): ?array
    {
        $user = User::query()->where('email', $email)->first();

        // Hash::check on a dummy hash when the user is missing, so a wrong
        // email costs the same time as a wrong password and cannot be
        // distinguished by response timing.
        if (! $user) {
            Hash::check($password, '$2y$12$'.str_repeat('0', 53));

            return null;
        }

        if (! Hash::check($password, $user->password)) {
            return null;
        }

        $token = $user->createToken('admin-panel')->plainTextToken;

        // Opportunistic hygiene at login. Production runs on Render's free
        // tier, which has no cron, so a scheduled `sanctum:prune-expired`
        // cannot be relied on — this is the always-on replacement. Run after
        // the new token is minted so the current session is never pruned.
        $this->pruneTokens($user);

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

    /**
     * Drop tokens this user can no longer use, then cap the remainder.
     *
     *  1. Deletes every token Sanctum would already reject — expired by the
     *     configured lifetime (sanctum.expiration, checked against created_at,
     *     matching Guard::isValidAccessToken) or by an explicit expires_at.
     *  2. Keeps only the newest MAX_TOKENS_PER_USER tokens, so repeated admin
     *     logins cannot pile tokens up without bound.
     *
     * Called after the new token is minted, so the session just created is
     * always among the survivors.
     */
    public function pruneTokens(User $user): void
    {
        $expirationMinutes = (int) config('sanctum.expiration');

        $user->tokens()
            ->where(function ($query) use ($expirationMinutes) {
                if ($expirationMinutes > 0) {
                    $query->where('created_at', '<=', now()->subMinutes($expirationMinutes));
                }

                $query->orWhere(function ($query) {
                    $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
                });
            })
            ->delete();

        $survivors = $user->tokens()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::MAX_TOKENS_PER_USER)
            ->pluck('id');

        $user->tokens()->whereNotIn('id', $survivors)->delete();
    }

    /** Throttle key for a login attempt, scoped to email + source IP. */
    public function throttleKey(string $email, string $ip): string
    {
        return 'login:'.Str::lower($email).'|'.$ip;
    }

    public function tooManyAttempts(string $key): bool
    {
        return RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS);
    }

    public function recordFailure(string $key): void
    {
        RateLimiter::hit($key, self::DECAY_SECONDS);
    }

    public function clearAttempts(string $key): void
    {
        RateLimiter::clear($key);
    }

    public function secondsUntilRetry(string $key): int
    {
        return RateLimiter::availableIn($key);
    }
}
