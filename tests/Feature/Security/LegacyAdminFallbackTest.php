<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyAdminFallbackTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Legacy\Support\LegacyRoleResolver;
use App\Models\{Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Sicherheitsscan 2026-08-23, S-01: **ein selbst gesetzter Anzeigename ist
 * keine Identität.**
 *
 * Bis dahin verglich `LegacyRoleResolver` den frei editierbaren `users.name`
 * bzw. den E-Mail-Localpart gegen `admin,administrator,chef` — ohne dass ein
 * Altsystem überhaupt erreichbar sein musste. Wer sich im eigenen Profil
 * „chef" nannte, war ab dem nächsten Request Org-Admin.
 */
class LegacyAdminFallbackTest extends TestCase {
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function namesProvider(): array {
        return [
            'admin' => ['admin'],
            'administrator' => ['administrator'],
            'chef' => ['chef'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('namesProvider')]
    public function test_umbenennen_macht_niemanden_zum_admin(string $name): void {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $name . '@example.test',
        ]);

        $this->assertFalse($user->isAdmin());
        $this->assertFalse(LegacyRoleResolver::isAdmin($user));
    }

    public function test_auch_eine_gesetzte_altliste_wirkt_nicht_mehr(): void {
        // Der Schlüssel ist entfallen; eine .env aus der Zeit davor darf ihn
        // nicht wiederbeleben.
        Config::set('legacy.fallback_admins', 'admin,administrator,chef');

        $user = User::factory()->create(['name' => 'chef', 'email' => 'chef@example.test']);

        $this->assertFalse($user->isAdmin());
    }

    public function test_die_verknuepfte_legacy_id_entscheidet(): void {
        $legacyAdmin = User::factory()->create(['legacy_user_id' => 2]);
        $legacyUser = User::factory()->create(['legacy_user_id' => 42]);

        $this->assertTrue(LegacyRoleResolver::isAdmin($legacyAdmin));
        $this->assertFalse(LegacyRoleResolver::isAdmin($legacyUser));
    }

    public function test_ohne_verknuepfung_wird_nicht_gesucht(): void {
        // Früher hätte der Resolver hier im Altsystem nach dem Anzeigenamen
        // gesucht und den Nutzer an ein fremdes Konto gehängt.
        $user = User::factory()->create(['name' => 'admin', 'legacy_user_id' => null]);

        $this->assertSame(0, LegacyRoleResolver::resolveLegacyUserId($user));
        $this->assertNull($user->refresh()->legacy_user_id);
    }

    public function test_org_admin_einer_gesperrten_org_landet_nicht_in_fremder_org(): void {
        // S-04/S-01: Schritt 3 des Organisationskontexts band früher jeden
        // isAdmin() an die erste aktive Organisation — mit Policy-Bypass.
        $fremde = Organization::factory()->create(['is_active' => true]);
        $eigene = Organization::factory()->create(['is_active' => false]);

        $orgAdmin = User::factory()->admin()->create(['organization_id' => $eigene->id]);

        $this->actingAs($orgAdmin)->get(route('dashboard'));

        $bound = app()->bound('currentOrganization') ? app('currentOrganization') : null;

        $this->assertNotSame($fremde->id, $bound?->id, 'Org-Admin wurde an eine fremde Organisation gebunden.');
    }
}
