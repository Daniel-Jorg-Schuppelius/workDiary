<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EmptyStateRenderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\UI;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Leere-Zustände-Stichprobe (Feature 039, B3/MVP-344): Kern-Index-Views
 * rendern bei leerer Liste den Standard-Baustein <x-empty-state>
 * (Marker-Klasse `wd-empty-state`) statt einer kommentarlos leeren
 * Tabelle. Vollständiges Audit: bauturbo-plan-2026-07.md, B3.
 */
class EmptyStateRenderTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_customers_index_renders_empty_state_without_data(): void {
        $this->actingAs($this->admin)->get(route('customers.index'))
            ->assertOk()
            ->assertSee('wd-empty-state');
    }

    public function test_projects_index_renders_empty_state_without_data(): void {
        $this->actingAs($this->admin)->get(route('projects.index'))
            ->assertOk()
            ->assertSee('wd-empty-state');
    }

    public function test_invoices_index_renders_empty_state_without_data(): void {
        $this->actingAs($this->admin)->get(route('invoices.index'))
            ->assertOk()
            ->assertSee('wd-empty-state');
    }
}
