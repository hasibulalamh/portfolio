<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Token hygiene for the admin API: because production has no cron, expired
 * tokens are pruned opportunistically at login instead of by a scheduled
 * `sanctum:prune-expired`. These tests pin the three promises that behaviour
 * makes:
 *
 *  1. logging in deletes the user's already-expired tokens;
 *  2. logging in never touches tokens still inside the expiry window;
 *  3. repeated logins cannot pile tokens up beyond the cap.
 *
 * A fourth test pins the unchanged logout contract: it revokes only the token
 * on the current request, so a second device stays signed in.
 *
 * Expiry is exercised by back-dating `created_at` — the same field Sanctum's
 * Guard checks against the configured lifetime — so no test waits real time.
 */
class SanctumTokenHygieneTest extends TestCase
{
    use RefreshDatabase;

    /** UserFactory's default password. */
    private const PASSWORD = 'password';

    /**
     * Mint one token per age (minutes ago), back-dating created_at so the
     * login prune sees an old token without any wait.
     *
     * @param  array<int, int>  $minutesAgo
     */
    private function seedTokens(User $user, array $minutesAgo): void
    {
        foreach ($minutesAgo as $minutes) {
            $token = $user->createToken('admin-panel');

            $token->accessToken->forceFill([
                'created_at' => now()->subMinutes($minutes),
            ])->save();
        }
    }

    private function login(User $user)
    {
        return $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ]);
    }

    public function test_login_removes_expired_tokens_and_keeps_active_ones(): void
    {
        $user = User::factory()->create();

        // Two past the 7-day lifetime (30d, 8d) and two inside it (6d, 1d).
        $this->seedTokens($user, [
            60 * 24 * 30,
            60 * 24 * 8,
            60 * 24 * 6,
            60 * 24,
        ]);

        $this->assertSame(4, $user->tokens()->count());

        $response = $this->login($user)->assertOk();

        // Expired gone, active kept, plus the token this login just minted.
        $this->assertSame(3, $user->tokens()->count());
        $this->assertSame(
            0,
            $user->tokens()->where('created_at', '<=', now()->subDays(7))->count(),
        );

        // The session this login created is valid and the older active tokens
        // were not collaterally revoked.
        $this->withToken($response->json('data.token'))
            ->getJson('/api/admin/me')
            ->assertOk();
    }

    public function test_login_caps_stored_tokens_at_five(): void
    {
        $user = User::factory()->create();

        // Seven tokens, all comfortably inside the expiry window.
        $this->seedTokens($user, [1, 2, 3, 4, 5, 6, 7]);

        $this->assertSame(7, $user->tokens()->count());

        $response = $this->login($user)->assertOk();

        // Newest five survive — including the just-minted session token.
        $this->assertSame(5, $user->tokens()->count());
        $this->withToken($response->json('data.token'))
            ->getJson('/api/admin/me')
            ->assertOk();
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();

        $first = $this->login($user)->json('data.token');
        $second = $this->login($user)->json('data.token');

        // A request-scoped guard memoises the request it was built with, so
        // clear it before each HTTP call in this multi-request test.
        $this->app['auth']->forgetGuards();
        $this->withToken($first)->postJson('/api/logout')->assertOk();

        // The revoked token row is gone; the other session's row survives.
        $this->assertNull(PersonalAccessToken::findToken($first));
        $this->assertNotNull(PersonalAccessToken::findToken($second));

        // And a fresh request with the dead token is rejected, while the
        // untouched session still authenticates.
        $this->app['auth']->forgetGuards();
        $this->withToken($first)->getJson('/api/admin/me')->assertStatus(401);

        $this->app['auth']->forgetGuards();
        $this->withToken($second)->getJson('/api/admin/me')->assertOk();

        $this->assertSame(1, $user->tokens()->count());
    }
}
