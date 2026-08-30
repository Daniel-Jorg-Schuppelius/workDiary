<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InactiveOrganizationAccessTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Models\{Customer, Organization, User};
use App\Services\OrganizationLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sicherheitsscan 2026-08-23, S-04: **eine abgeschaltete Organisation darf
 * nicht zur Generalvollmacht werden.**
 *
 * `OrganizationScope` filtert über die im Container gebundene Organisation.
 * Wurde keine gebunden — genau das passierte bei inaktiver Organisation —,
 * war der Scope ein No-Op: jede Liste, deren Policy `viewAny` mit `true`
 * beantwortet, lief über **alle** Mandanten. Das Abschalten eines Mandanten
 * (Offboarding, Zahlungsverzug) öffnete also die Tür, statt sie zu schließen.
 */
class InactiveOrganizationAccessTest extends TestCase {
    use RefreshDatabase;

    public function test_nutzer_einer_abgeschalteten_org_kommt_nicht_mehr_hinein(): void {
        $fremde = Organization::factory()->create(['is_active' => true]);
        Customer::factory()->create(['organization_id' => $fremde->id, 'name' => 'Fremdkunde AG']);

        $gesperrt = Organization::factory()->create(['is_active' => false]);
        $user = User::factory()->create(['organization_id' => $gesperrt->id]);

        $this->actingAs($user)
            ->get(route('customers.index'))
            ->assertStatus(423);
    }

    public function test_die_api_antwortet_ebenfalls_gesperrt(): void {
        $gesperrt = Organization::factory()->create(['is_active' => false]);
        $user = User::factory()->create(['organization_id' => $gesperrt->id]);

        $this->actingAs($user)
            ->getJson('/api/absences')
            ->assertStatus(423)
            ->assertJsonPath('error', 'organization_inactive');
    }

    public function test_abmelden_bleibt_moeglich(): void {
        // Sonst säße der Nutzer in einer Sitzung fest, die er nicht beenden kann.
        $gesperrt = Organization::factory()->create(['is_active' => false]);
        $user = User::factory()->create(['organization_id' => $gesperrt->id]);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect();

        $this->assertGuest();
    }

    public function test_plattform_betreiber_bleibt_arbeitsfaehig(): void {
        $gesperrt = Organization::factory()->create(['is_active' => false]);
        Organization::factory()->create(['is_active' => true]);

        $operator = User::factory()->platformAdmin()->create(['organization_id' => $gesperrt->id]);

        $this->actingAs($operator)
            ->get(route('customers.index'))
            ->assertSuccessful();
    }

    public function test_abschalten_entwertet_tokens_und_angemeldet_bleiben(): void {
        $org = Organization::factory()->create(['is_active' => true]);
        $user = User::factory()->create(['organization_id' => $org->id, 'remember_token' => 'bleibt-nicht']);
        $user->createToken('mobil');

        $this->assertSame(1, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count());

        app(OrganizationLifecycleService::class)->deactivate($org->fresh(), null);

        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count());
        $this->assertNull($user->refresh()->remember_token);
    }

    public function test_anmeldung_wird_abgewiesen(): void {
        $org = Organization::factory()->create(['is_active' => false]);
        $user = User::factory()->create([
            'organization_id' => $org->id,
            'email' => 'gesperrt@example.test',
            'password' => bcrypt('geheim-1234'),
            'is_new_system' => true,
        ]);

        // Angemeldet wird mit der E-Mail (LegacyUserProvider reicht sie als
        // `email` an den Standard-Provider weiter).
        $this->post(route('login'), [
            'username' => $user->email,
            'password' => 'geheim-1234',
        ]);

        $this->assertGuest();
    }
}
