<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemRateAccessTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Admin;

use App\Models\{PerDiemRate, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verpflegungspauschalen sind globale, mandantenübergreifende Stammdaten:
 * Schreibzugriff nur für den Plattform-Betreiber (Whitebox 2026-07, Fund 2).
 * Ein org-lokaler Admin darf lesen, aber nicht schreiben.
 */
class PerDiemRateAccessTest extends TestCase {
    use RefreshDatabase;

    /** @return array<string, string> */
    private function payload(): array {
        return [
            'country' => 'DE',
            'valid_from' => '2030-01-01',
            'full_day_amount' => '28.00',
            'partial_day_amount' => '14.00',
            'overnight_amount' => '20.00',
            'currency' => 'EUR',
        ];
    }

    public function test_org_local_admin_can_read_but_not_write_global_rates(): void {
        $admin = User::factory()->admin()->create();
        $rate = PerDiemRate::factory()->create();

        // Lesen erlaubt.
        $this->actingAs($admin)->get(route('admin.per-diem-rates.index'))->assertOk();

        // Schreiben verboten.
        $this->actingAs($admin)->post(route('admin.per-diem-rates.store'), $this->payload())->assertForbidden();
        $this->actingAs($admin)->delete(route('admin.per-diem-rates.destroy', $rate))->assertForbidden();

        $this->assertDatabaseHas('per_diem_rates', ['id' => $rate->id]);
    }

    public function test_platform_admin_can_write_global_rates(): void {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.per-diem-rates.store'), $this->payload())
            ->assertRedirect(route('admin.per-diem-rates.index'));

        $this->assertDatabaseHas('per_diem_rates', ['country' => 'DE', 'valid_from' => '2030-01-01 00:00:00']);
    }
}
