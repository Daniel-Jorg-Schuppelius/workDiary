<?php

/*
 * Filename     : KioskAndCheckinTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Terminal;

use App\Enums\Attendance\{AttendanceSource, CheckpointKind};
use App\Models\{Attendance, AttendanceCheckpoint, AttendanceTerminal, Organization, Site, User, Vehicle};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Kiosk-Modus und QR-/NFC-Check-in (MVP-800, Features 001/004 — entschieden im
 * Vollscan 2026-09-15).
 */
final class KioskAndCheckinTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    // ── Kiosk ──────────────────────────────────────────────────────────

    public function test_kiosk_page_renders_for_an_active_terminal_without_login(): void {
        [$terminal, $token] = AttendanceTerminal::issue($this->organization->id, 'Werkstatt Tablet');

        $this->get(route('kiosk.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Werkstatt Tablet')
            ->assertSee('data-ingest-url="' . route('api.terminal.ingest', ['token' => $token]) . '"', false)
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Cache-Control', 'no-store, private');

        $terminal->forceFill(['active' => false])->save();
        $this->get(route('kiosk.show', ['token' => $token]))->assertNotFound();
    }

    public function test_kiosk_rejects_unknown_tokens(): void {
        $this->get(route('kiosk.show', ['token' => 'term_unbekannt']))->assertNotFound();
    }

    public function test_admin_sees_the_kiosk_address_once_after_registering(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)->post(route('admin.terminals.store'), ['name' => 'Empfang'])->assertRedirect();

        $this->actingAs($admin)->get(route('admin.terminals.index'))
            ->assertOk()
            ->assertSee(url('/kiosk/term_'), false);
    }

    // ── Check-in-Punkte ────────────────────────────────────────────────

    public function test_checkin_clocks_in_and_out_with_the_checkpoint_recorded(): void {
        $user = $this->member();
        $checkpoint = $this->checkpoint(['name' => 'Halle 3']);

        $this->actingAs($user)->get(route('checkin.show', $checkpoint->token))
            ->assertOk()
            ->assertSee('Halle 3')
            ->assertSee('name="action" value="in"', false)
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        $this->actingAs($user)->post(route('checkin.stamp', $checkpoint->token), ['action' => 'in'])
            ->assertRedirect(route('checkin.show', $checkpoint->token));

        $attendance = Attendance::query()->where('user_id', $user->id)->sole();
        $this->assertSame(AttendanceSource::Checkin, $attendance->source);
        $this->assertSame($checkpoint->id, $attendance->started_checkpoint_id);
        $this->assertNull($attendance->ended_at);

        // Doppelt getippt: kein Umschalten auf Gehen.
        $this->actingAs($user)->post(route('checkin.stamp', $checkpoint->token), ['action' => 'in'])
            ->assertSessionHasErrors('action');
        $this->assertNull($attendance->refresh()->ended_at);

        $this->actingAs($user)->post(route('checkin.stamp', $checkpoint->token), ['action' => 'out'])->assertRedirect();
        $attendance->refresh();
        $this->assertNotNull($attendance->ended_at);
        $this->assertSame($checkpoint->id, $attendance->ended_checkpoint_id);
    }

    public function test_foreign_and_disabled_checkpoints_are_not_found(): void {
        $user = $this->member();
        $foreign = AttendanceCheckpoint::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $disabled = $this->checkpoint(['active' => false]);

        $this->actingAs($user)->get(route('checkin.show', $foreign->token))->assertNotFound();
        $this->actingAs($user)->post(route('checkin.stamp', $foreign->token), ['action' => 'in'])->assertNotFound();
        $this->actingAs($user)->get(route('checkin.show', $disabled->token))->assertNotFound();
        $this->assertSame(0, Attendance::query()->withoutGlobalScopes()->count());
    }

    public function test_checkin_requires_login(): void {
        $checkpoint = $this->checkpoint();

        $this->get(route('checkin.show', $checkpoint->token))->assertRedirect(route('login'));
    }

    public function test_radius_is_enforced_and_the_position_is_not_stored(): void {
        $user = $this->member();
        // Mittelpunkt Kölner Dom, Umkreis 100 m.
        $checkpoint = $this->checkpoint(['latitude' => 50.9413, 'longitude' => 6.9583, 'radius_m' => 100]);

        // Ohne Freigabe blockierte die app-weite Permissions-Policy die Ortsabfrage.
        $this->actingAs($user)->get(route('checkin.show', $checkpoint->token))
            ->assertOk()
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), payment=()');

        $this->actingAs($user)->post(route('checkin.stamp', $checkpoint->token), ['action' => 'in'])
            ->assertSessionHasErrors('location');
        $this->actingAs($user)->post(route('checkin.stamp', $checkpoint->token), ['action' => 'in', 'latitude' => 50.9375, 'longitude' => 6.9603])
            ->assertSessionHasErrors('location');
        $this->assertSame(0, Attendance::query()->count());

        $this->actingAs($user)->post(route('checkin.stamp', $checkpoint->token), ['action' => 'in', 'latitude' => 50.9416, 'longitude' => 6.9585])
            ->assertRedirect();
        $attendance = Attendance::query()->sole();
        $this->assertNull($attendance->started_lat);
        $this->assertNull($attendance->started_lng);
    }

    public function test_site_coordinates_serve_as_center_when_the_point_has_none(): void {
        $site = Site::factory()->create(['organization_id' => $this->organization->id, 'geo_lat' => '50.9413', 'geo_lng' => '6.9583']);
        $checkpoint = $this->checkpoint(['site_id' => $site->id, 'radius_m' => 100]);

        $this->assertSame([50.9413, 6.9583], $checkpoint->center());
    }

    // ── Verwaltung ─────────────────────────────────────────────────────

    public function test_admin_creates_vehicle_checkpoint_and_prints_the_qr_code(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id, 'license_plate' => 'K-WD 800']);

        $this->actingAs($admin)->post(route('admin.terminals.checkpoints.store'), [
            'name' => 'Transporter 1',
            'kind' => CheckpointKind::Vehicle->value,
            'vehicle' => $vehicle->sqid,
        ])->assertRedirect();

        $checkpoint = AttendanceCheckpoint::query()->where('name', 'Transporter 1')->sole();
        $this->assertSame(CheckpointKind::Vehicle, $checkpoint->kind);
        $this->assertSame($vehicle->id, $checkpoint->vehicle_id);

        $this->actingAs($admin)->get(route('admin.terminals.checkpoints.qr', $checkpoint->sqid))
            ->assertOk()
            ->assertSee('data:image/svg+xml;base64,', false)
            ->assertSee(route('checkin.show', $checkpoint->token));
    }

    public function test_radius_without_any_center_is_rejected(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)->post(route('admin.terminals.checkpoints.store'), [
            'name' => 'Lager',
            'kind' => CheckpointKind::Site->value,
            'radius_m' => 50,
        ])->assertSessionHasErrors('radius_m');

        $this->assertSame(0, AttendanceCheckpoint::query()->count());
    }

    public function test_checkpoint_management_is_admin_only(): void {
        $this->actingAs($this->member())->get(route('admin.terminals.checkpoints.create'))->assertForbidden();
    }

    private function member(): User {
        return User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function checkpoint(array $attributes = []): AttendanceCheckpoint {
        return AttendanceCheckpoint::factory()->create(['organization_id' => $this->organization->id] + $attributes);
    }
}
