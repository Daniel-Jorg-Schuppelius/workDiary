<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevConfigSettingsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1010: Leere Felder der DATEV-Konfiguration entfernen den gespeicherten Wert. */
final class DatevConfigSettingsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_cleared_fields_are_removed_and_others_stay(): void {
        $this->setUpOrganization();
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)->put(route('finance.datev.config.update'), ['datev' => ['advisor_number' => '12345', 'client_number' => '67890', 'revenue_account' => '8400']])
            ->assertSessionHasNoErrors();
        $this->assertSame('8400', $this->organization->refresh()->settings['datev']['revenue_account'] ?? null);

        $this->actingAs($admin)->put(route('finance.datev.config.update'), ['datev' => ['advisor_number' => '12345', 'revenue_account' => '']])
            ->assertSessionHasNoErrors();
        $datev = $this->organization->refresh()->settings['datev'] ?? [];
        $this->assertArrayNotHasKey('revenue_account', $datev);
        $this->assertSame('67890', (string) ($datev['client_number'] ?? ''));
    }
}
