<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TerminalExtensionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Terminal;

use App\Models\{AttendanceTerminal, FlexBalance, FlexEligibility, User, UserBadge, VacationEntitlement};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-516 (Feature 103): Badge-Gültigkeitszeiträume, opt-in Status-Antwort,
 * Token-Rotation und Pufferstand-Meldung.
 */
final class TerminalExtensionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const BADGE = 'EF56-GH78';

    private AttendanceTerminal $terminal;

    private string $token;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Terminal Tester']);
        [$this->terminal, $this->token] = AttendanceTerminal::issue($this->organization->id, 'Halle Süd');
    }

    /** @param array<string, mixed> $overrides */
    private function badge(array $overrides = []): UserBadge {
        return UserBadge::query()->create(array_merge([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'badge_hash' => UserBadge::hashBadge(self::BADGE),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<\Illuminate\Http\Response>
     */
    private function scan(array $payload = [], ?string $token = null): TestResponse {
        return $this->postJson(
            '/api/terminal/ingest/' . ($token ?? $this->token),
            array_merge(['badge_uid' => self::BADGE], $payload),
        );
    }

    public function test_badge_outside_validity_window_is_rejected(): void {
        $this->badge(['valid_until' => now()->subDay()->toDateString()]);

        $this->scan()->assertOk()->assertJson(['status' => 'unknown_badge']);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_badge_within_validity_window_clocks_in(): void {
        $this->badge([
            'valid_from' => now()->subDay()->toDateString(),
            'valid_until' => now()->addDay()->toDateString(),
        ]);

        $this->scan()->assertOk()->assertJson(['status' => 'clocked_in']);
    }

    public function test_status_response_requires_opt_in(): void {
        $this->badge();

        $response = $this->scan();
        $response->assertOk()->assertJson(['status' => 'clocked_in']);
        $this->assertArrayNotHasKey('employee', $response->json());
    }

    public function test_status_response_contains_balances_when_enabled(): void {
        $this->terminal->forceFill(['show_status' => true])->save();
        $this->badge();
        FlexEligibility::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'valid_from' => '2020-01-01',
        ]);
        FlexBalance::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'target_minutes' => 9600,
            'actual_minutes' => 9927,
            'balance_minutes' => 327,
            'carry_over_minutes' => 0,
        ]);
        VacationEntitlement::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'year' => (int) now()->year,
            'entitled_days' => 30,
            'carryover_days' => 0,
        ]);

        $response = $this->scan();

        $response->assertOk()
            ->assertJson([
                'status' => 'clocked_in',
                'employee' => 'Terminal Tester',
                'flex_balance_minutes' => 327,
            ]);
        $this->assertSame(30.0, (float) $response->json('vacation_days_remaining'));
    }

    public function test_reported_buffer_size_is_stored_and_flagged_in_diagnostics(): void {
        $this->badge();

        $this->scan(['queued' => 7])->assertOk();

        $this->assertSame(7, (int) $this->terminal->fresh()->last_buffer_size);

        $section = app(\App\Services\Diagnostics\DiagnosticsService::class)->checkTerminals();
        $this->assertSame(7, $section->metrics['buffered'] ?? null);
    }

    public function test_token_rotation_invalidates_old_token(): void {
        $this->badge();

        $newToken = $this->terminal->rotate();

        $this->scan()->assertStatus(401);
        $this->scan(token: $newToken)->assertOk()->assertJson(['status' => 'clocked_in']);
    }

    public function test_admin_can_store_badge_with_validity(): void {
        $admin = $this->orgAdmin();

        $this->actingAs($admin)
            ->post(route('admin.terminals.badges.store'), [
                'user' => $this->user->sqid,
                'badge_uid' => 'NEW-99',
                'valid_from' => '2026-09-01',
                'valid_until' => '2026-12-31',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_badges', [
            'user_id' => $this->user->id,
            'valid_from' => '2026-09-01',
            'valid_until' => '2026-12-31',
        ]);
    }
}
