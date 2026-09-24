<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgScopedSlugTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Org;

use App\Models\Customer\Customer;
use App\Models\Supplier\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * UI-Formular-Fuzz 2026-09-21 (F1): Der zweite gleichnamige Kunde endete in
 * 1062 customers_org_slug_unique, weil die Slug-Prüfung vor dem Setzen der
 * organization_id (BelongsToOrganization::creating) lief.
 */
class OrgScopedSlugTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_customer_slug_is_unique_when_organization_comes_from_context(): void {
        $first = Customer::query()->create(['name' => 'Müller GmbH']);
        $second = Customer::query()->create(['name' => 'Müller GmbH']);

        $this->assertSame($this->organization->id, $second->organization_id);
        $this->assertSame($first->slug . '-2', $second->slug);
    }

    public function test_supplier_slug_is_unique_when_organization_comes_from_context(): void {
        $first = Supplier::query()->create(['name' => 'Müller GmbH']);
        $second = Supplier::query()->create(['name' => 'Müller GmbH']);

        $this->assertSame($this->organization->id, $second->organization_id);
        $this->assertSame($first->slug . '-2', $second->slug);
    }

    public function test_customer_store_accepts_duplicate_name(): void {
        $admin = $this->orgAdmin();

        foreach ([1, 2] as $_) {
            $this->actingAs($admin)
                ->post(route('customers.store'), ['name' => 'Doppelt GmbH', 'currency' => 'EUR'])
                ->assertRedirect();
        }

        $this->assertSame(2, Customer::query()->where('name', 'Doppelt GmbH')->count());
    }
}
