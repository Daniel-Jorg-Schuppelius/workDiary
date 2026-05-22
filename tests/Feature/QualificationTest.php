<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QualificationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Qualification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class QualificationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function admin(): User {
        return User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function regularUser(): User {
        return User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    // ── Zugriffskontrolle ────────────────────────────────────────────────────

    public function test_guest_cannot_access_qualifications(): void {
        $this->get(route('qualifications.index'))->assertRedirect(route('login'));
    }

    public function test_org_member_can_view_index(): void {
        $this->actingAs($this->regularUser())
            ->get(route('qualifications.index'))
            ->assertOk()
            ->assertViewIs('qualifications.index');
    }

    public function test_non_admin_cannot_open_create_form(): void {
        $this->actingAs($this->regularUser())
            ->get(route('qualifications.create'))
            ->assertForbidden();
    }

    // ── CRUD ─────────────────────────────────────────────────────────────────

    public function test_admin_can_create_qualification(): void {
        $this->actingAs($this->admin())
            ->post(route('qualifications.store'), [
                'name' => 'Erste Hilfe',
                'abbreviation' => 'EH',
                'description' => 'Grundkurs Erste Hilfe',
                'is_active' => '1',
            ])
            ->assertRedirect(route('qualifications.index'));

        $this->assertDatabaseHas('qualifications', [
            'name' => 'Erste Hilfe',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_name_must_be_unique_within_org(): void {
        Qualification::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Doppelt',
        ]);

        $this->actingAs($this->admin())
            ->post(route('qualifications.store'), [
                'name' => 'Doppelt',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_admin_can_update_qualification(): void {
        $qual = Qualification::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin())
            ->put(route('qualifications.update', $qual), [
                'name' => 'Aktualisiert',
                'is_active' => '1',
            ])
            ->assertRedirect(route('qualifications.index'));

        $this->assertDatabaseHas('qualifications', ['id' => $qual->id, 'name' => 'Aktualisiert']);
    }

    public function test_admin_can_delete_qualification(): void {
        $qual = Qualification::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin())
            ->delete(route('qualifications.destroy', $qual))
            ->assertRedirect(route('qualifications.index'));

        $this->assertDatabaseMissing('qualifications', ['id' => $qual->id]);
    }

    public function test_non_admin_cannot_delete(): void {
        $qual = Qualification::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->regularUser())
            ->delete(route('qualifications.destroy', $qual))
            ->assertForbidden();
    }

    // ── Mandanten-Isolierung ─────────────────────────────────────────────────

    public function test_qualifications_from_other_org_not_visible(): void {
        $otherOrg = Organization::factory()->create();
        Qualification::factory()->create([
            'organization_id' => $otherOrg->id,
            'name' => 'Fremde Qualifikation',
        ]);
        Qualification::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Eigene Qualifikation',
        ]);

        $response = $this->actingAs($this->regularUser())
            ->get(route('qualifications.index'));

        $response->assertOk();
        $qualifications = $response->viewData('qualifications');
        $this->assertSame(1, $qualifications->total());
        $this->assertSame('Eigene Qualifikation', $qualifications->items()[0]->name);
    }
}
