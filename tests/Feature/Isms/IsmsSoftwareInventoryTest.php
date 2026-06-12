<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsSoftwareInventoryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Isms;

use App\Enums\Isms\SupportStatus;
use App\Models\Isms\{IsmsSoftwareInstallation, IsmsSoftwareProduct};
use App\Models\{Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IsmsSoftwareInventoryTest extends TestCase {
    use RefreshDatabase;

    public function test_admin_can_create_and_update_software_product(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('isms.software.index'))
            ->post(route('isms.software.store'), [
                'name' => 'Ubuntu Server',
                'vendor' => 'Canonical',
                'product_version' => '24.04 LTS',
                'category' => 'os',
                'support_status' => 'supported',
                'eol_on' => now()->addYears(3)->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('isms_software_products', [
            'name' => 'Ubuntu Server',
            'organization_id' => $admin->organization_id,
            'vendor' => 'Canonical',
            'support_status' => SupportStatus::Supported->value,
        ]);

        $product = IsmsSoftwareProduct::query()->withoutGlobalScopes()->firstOrFail();

        $this->actingAs($admin)
            ->put(route('isms.software.update', $product), [
                'name' => 'Ubuntu Server',
                'vendor' => 'Canonical Ltd.',
                'product_version' => '24.04.2 LTS',
                'category' => 'os',
                'support_status' => 'extendedSupport',
            ])
            ->assertRedirect();

        $product->refresh();
        $this->assertSame('Canonical Ltd.', $product->vendor);
        $this->assertSame(SupportStatus::ExtendedSupport, $product->support_status);
    }

    public function test_eol_in_the_past_forces_end_of_life_status(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('isms.software.store'), [
                'name' => 'Windows Server 2012',
                'support_status' => 'supported',
                'eol_on' => now()->subYear()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('isms_software_products', [
            'name' => 'Windows Server 2012',
            'support_status' => SupportStatus::EndOfLife->value,
        ]);

        // Auch beim Aktualisieren greift die Automatik.
        $product = $this->makeProduct($admin, ['support_status' => SupportStatus::Supported->value]);
        $this->actingAs($admin)
            ->put(route('isms.software.update', $product), [
                'name' => $product->name,
                'support_status' => 'supported',
                'eol_on' => now()->subDay()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame(SupportStatus::EndOfLife, $product->refresh()->support_status);
    }

    public function test_product_with_installations_cannot_be_deleted(): void {
        $admin = User::factory()->admin()->create();
        $product = $this->makeProduct($admin);
        IsmsSoftwareInstallation::factory()->create([
            'organization_id' => $admin->organization_id,
            'isms_software_product_id' => $product->id,
        ]);

        $this->actingAs($admin)
            ->from(route('isms.software.index'))
            ->delete(route('isms.software.destroy', $product))
            ->assertRedirect()
            ->assertSessionHasErrors('product');

        $this->assertDatabaseHas('isms_software_products', ['id' => $product->id, 'deleted_at' => null]);

        // Ohne Installationen klappt das Löschen (Soft-Delete).
        $product->installations()->forceDelete();
        $this->actingAs($admin)
            ->delete(route('isms.software.destroy', $product))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('isms_software_products', ['id' => $product->id]);
    }

    public function test_installation_crud_via_product(): void {
        $admin = User::factory()->admin()->create();
        $product = $this->makeProduct($admin);

        $this->actingAs($admin)
            ->from(route('isms.software.index'))
            ->post(route('isms.software.installations.store', $product), [
                'installed_version' => '24.04.1',
                'asset_ref' => 'Server SRV-01',
                'location' => 'Serverraum HQ',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('isms_software_installations', [
            'isms_software_product_id' => $product->id,
            'organization_id' => $admin->organization_id,
            'asset_ref' => 'Server SRV-01',
        ]);

        $installation = IsmsSoftwareInstallation::query()->withoutGlobalScopes()->firstOrFail();

        $this->actingAs($admin)
            ->put(route('isms.software.installations.update', $installation), [
                'installed_version' => '24.04.2',
                'asset_ref' => 'Server SRV-02',
            ])
            ->assertRedirect();
        $this->assertSame('Server SRV-02', $installation->refresh()->asset_ref);

        $this->actingAs($admin)
            ->delete(route('isms.software.installations.destroy', $installation))
            ->assertRedirect();
        $this->assertSoftDeleted('isms_software_installations', ['id' => $installation->id]);
    }

    public function test_index_shows_products_with_filters_and_eol_badge(): void {
        $admin = User::factory()->admin()->create();
        $this->makeProduct($admin, ['name' => 'Filterprodukt Alpha', 'category' => 'application']);
        $this->makeProduct($admin, [
            'name' => 'EOL-Produkt Beta',
            'category' => 'os',
            'support_status' => SupportStatus::EndOfLife->value,
            'eol_on' => now()->subMonth()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('isms.software.index'))
            ->assertOk()
            ->assertSee('Filterprodukt Alpha')
            ->assertSee('EOL-Produkt Beta')
            ->assertSee(__('isms.software.eol_reached'));

        // Kategorie-Filter blendet das andere Produkt aus.
        $this->actingAs($admin)
            ->get(route('isms.software.index', ['category' => 'application']))
            ->assertOk()
            ->assertSee('Filterprodukt Alpha')
            ->assertDontSee('EOL-Produkt Beta');

        // Suche nach Name.
        $this->actingAs($admin)
            ->get(route('isms.software.index', ['q' => 'Beta']))
            ->assertOk()
            ->assertSee('EOL-Produkt Beta')
            ->assertDontSee('Filterprodukt Alpha');
    }

    public function test_regular_user_cannot_access_or_manage_software(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->get(route('isms.software.index'))->assertForbidden();

        $this->actingAs($user)
            ->post(route('isms.software.store'), [
                'name' => 'Verboten',
                'support_status' => 'unknown',
            ])
            ->assertForbidden();
    }

    public function test_geschaeftsfuehrung_can_view_but_not_manage(): void {
        $gf = User::factory()->geschaeftsfuehrung()->create();

        $this->actingAs($gf)->get(route('isms.software.index'))->assertOk();

        $this->actingAs($gf)
            ->post(route('isms.software.store'), [
                'name' => 'Nur lesen',
                'support_status' => 'unknown',
            ])
            ->assertForbidden();
    }

    public function test_cross_organization_product_is_not_accessible(): void {
        $admin = User::factory()->admin()->create();
        $otherOrg = Organization::factory()->create(['slug' => 'isms-sw-cross']);
        $otherAdmin = User::factory()->admin()->create(['organization_id' => $otherOrg->id]);
        $foreignProduct = $this->makeProduct($otherAdmin);

        $this->actingAs($admin)
            ->put(route('isms.software.update', $foreignProduct), [
                'name' => 'Hijack',
                'support_status' => 'unknown',
            ])
            ->assertNotFound();

        $this->assertNotSame('Hijack', $foreignProduct->refresh()->name);
    }

    private function makeProduct(User $owner, array $overrides = []): IsmsSoftwareProduct {
        app()->instance('currentOrganization', $owner->organization);

        return IsmsSoftwareProduct::factory()->create([
            'organization_id' => $owner->organization_id,
            ...$overrides,
        ]);
    }
}
