<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeldestelleTwoFactorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Whistleblowing;

use App\Models\{Organization, User};
use App\Services\Whistleblowing\WhistleblowingPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * 2FA-Pflicht fuer die Meldestelle (Abschnitt 5): interne Fallbearbeitung nur
 * mit aktiver Zwei-Faktor-Authentifizierung. Nicht-Berechtigte fallen weiterhin
 * auf die normale 403-Pruefung durch (kein 2FA-Redirect).
 */
class MeldestelleTwoFactorTest extends TestCase {
    use RefreshDatabase;

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function meldestelle(Organization $org, bool $withTwoFactor): User {
        $user = User::factory()->create(['organization_id' => $org->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole(WhistleblowingPermissions::ROLE_MELDESTELLE);
        if ($withTwoFactor) {
            $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();
        }

        return $user;
    }

    public function test_meldestelle_without_two_factor_is_redirected_to_setup(): void {
        $org = Organization::factory()->create();
        $user = $this->meldestelle($org, withTwoFactor: false);

        $this->actingAs($user)->get('/compliance/meldungen')
            ->assertRedirect(route('account.2fa.show'));
    }

    public function test_meldestelle_with_two_factor_may_enter(): void {
        $org = Organization::factory()->create();
        $user = $this->meldestelle($org, withTwoFactor: true);

        $this->actingAs($user)->get('/compliance/meldungen')->assertOk();
    }

    public function test_non_handler_without_two_factor_gets_403_not_redirect(): void {
        $org = Organization::factory()->create();
        $plain = User::factory()->create(['organization_id' => $org->id]);

        // Keine Meldestellen-Permission → 2FA-Gate greift NICHT, normale 403.
        $this->actingAs($plain)->get('/compliance/meldungen')->assertForbidden();
    }
}
