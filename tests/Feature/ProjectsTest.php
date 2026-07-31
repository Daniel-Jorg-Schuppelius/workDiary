<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Project\ProjectStatus;
use App\Models\{Customer, ForeignCustomer, Project, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectsTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
    }

    public function test_user_can_create_project_via_web_route(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->post(route('projects.store'), [
                'name' => 'Neues Projekt',
                'description' => 'Beschreibung',
                'status' => ProjectStatus::Active->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'name' => 'Neues Projekt',
            'created_by' => $user->id,
        ]);
    }

    /**
     * Abrechenbar-Schalter (Tri-State): '0'/'1' setzen die Spalte, leere
     * Auswahl („Erben") setzt sie auf null zurück.
     */
    public function test_billable_tristate_is_saved_and_cleared_via_web_route(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->post(route('projects.store'), [
                'name' => 'Interne Doku',
                'status' => ProjectStatus::Active->value,
                'billable' => '0',
            ])
            ->assertRedirect();

        $project = Project::query()->where('name', 'Interne Doku')->firstOrFail();
        $this->assertFalse((bool) $project->billable);

        $this->actingAs($user)
            ->put(route('projects.update', $project), [
                'name' => 'Interne Doku',
                'status' => ProjectStatus::Active->value,
                'billable' => '',
            ])
            ->assertRedirect();

        $this->assertNull($project->fresh()->billable);
    }

    public function test_rename_is_unique_per_customer_and_foreign_customer(): void {
        $user = User::factory()->admin()->create();
        $customer = Customer::factory()->create(['organization_id' => $user->organization_id]);
        $endkundeA = ForeignCustomer::factory()->create([
            'organization_id' => $user->organization_id,
            'customer_id' => $customer->id,
            'name' => 'Endkunde A',
        ]);
        $endkundeB = ForeignCustomer::factory()->create([
            'organization_id' => $user->organization_id,
            'customer_id' => $customer->id,
            'name' => 'Endkunde B',
        ]);

        Project::factory()->create([
            'organization_id' => $user->organization_id,
            'customer_id' => $customer->id,
            'foreign_customer_id' => $endkundeA->id,
            'name' => 'Wartungen',
            'is_default' => false,
        ]);
        $project = Project::factory()->create([
            'organization_id' => $user->organization_id,
            'customer_id' => $customer->id,
            'foreign_customer_id' => $endkundeB->id,
            'name' => 'Sonstiges',
            'is_default' => false,
        ]);

        // Gleicher Name bei ANDEREM Endkunden derselben Firma → erlaubt.
        $this->actingAs($user)
            ->put(route('projects.update', $project), [
                'name' => 'Wartungen',
                'status' => ProjectStatus::Active->value,
                'customer_id' => $customer->sqid,
                'foreign_customer_id' => $endkundeB->sqid,
            ])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Wartungen']);

        // Gleicher Name beim SELBEN Endkunden → abgelehnt.
        $other = Project::factory()->create([
            'organization_id' => $user->organization_id,
            'customer_id' => $customer->id,
            'foreign_customer_id' => $endkundeA->id,
            'name' => 'Zweitprojekt',
            'is_default' => false,
        ]);
        $this->actingAs($user)
            ->put(route('projects.update', $other), [
                'name' => 'Wartungen',
                'status' => ProjectStatus::Active->value,
                'customer_id' => $customer->sqid,
                'foreign_customer_id' => $endkundeA->sqid,
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_non_owner_cannot_edit_foreign_project(): void {
        $owner = User::factory()->user()->create();
        $other = User::factory()->user()->create(['organization_id' => $owner->organization_id]);
        $project = Project::create([
            'organization_id' => $owner->organization_id,
            'name' => 'Fremdes Projekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $owner->id,
        ]);

        $this->actingAs($other)
            ->get(route('projects.edit', $project))
            ->assertForbidden();
    }

    /**
     * Umhängen auf einen Kunden, der bereits ein Projekt mit gleichem Slug hat
     * ((customer_id, slug) unique, z. B. „wartung" je Kunde): statt der
     * DB-Unique-Verletzung (500er ohne Validierungsfehler — der Slug steht
     * nicht im Formular) wird still um-geslugt.
     */
    public function test_moving_project_to_customer_with_same_slug_reslugs_instead_of_crashing(): void {
        $user = User::factory()->admin()->create();
        $kundeA = Customer::factory()->create(['organization_id' => $user->organization_id]);
        $kundeB = Customer::factory()->create(['organization_id' => $user->organization_id]);

        // Anderer Name (kein Validierungs-Konflikt), aber gleicher Slug — wie in
        // der Praxis nach einer Umbenennung (der Slug bleibt beim Rename stehen).
        Project::factory()->create([
            'organization_id' => $user->organization_id,
            'customer_id' => $kundeB->id,
            'name' => 'Server-Betreuung',
            'slug' => 'serverpflege',
            'is_default' => false,
        ]);
        $project = Project::factory()->create([
            'organization_id' => $user->organization_id,
            'customer_id' => $kundeA->id,
            'name' => 'Serverpflege',
            'slug' => 'serverpflege',
            'is_default' => false,
        ]);

        $response = $this->actingAs($user)
            ->put(route('projects.update', $project), [
                'name' => 'Serverpflege',
                'status' => ProjectStatus::Active->value,
                'customer_id' => $kundeB->sqid,
            ]);
        $response->assertSessionHasNoErrors()->assertRedirect();

        // Der Redirect muss auf die NEUE Projekt-URL zeigen und auflösbar sein
        // (Route-Key = kundenslug/projektslug — stale customer-Relation ergäbe
        // die alte URL und damit einen 404 nach erfolgreichem Umhängen).
        $this->actingAs($user)
            ->get((string) $response->headers->get('Location'))
            ->assertOk();

        $project->refresh();
        $this->assertSame($kundeB->id, $project->customer_id);
        $this->assertSame('serverpflege-2', $project->slug, 'Slug muss beim Zielkunden eindeutig gemacht werden.');
    }

    /**
     * Umhängen über den Bearbeiten-Dialog: Der Route-Key (kundenslug/slug)
     * ändert sich, die alte Seite existiert nicht mehr — der Dialog braucht
     * die neue URL als JSON-Redirect, sonst lädt er die tote URL neu (404).
     */
    public function test_dialog_update_returns_new_url_after_customer_move(): void {
        $user = User::factory()->admin()->create();
        $kunde = Customer::factory()->create(['organization_id' => $user->organization_id]);
        $project = Project::factory()->create([
            'organization_id' => $user->organization_id,
            'customer_id' => null, // internes Projekt → URL /projects/intern/allgemein
            'name' => 'Allgemein',
            'slug' => 'allgemein',
            'is_default' => false,
        ]);

        $response = $this->actingAs($user)
            ->withHeader('X-Entry-Dialog', '1')
            ->put(route('projects.update', $project), [
                'name' => 'Allgemein',
                'status' => ProjectStatus::Active->value,
                'customer_id' => $kunde->sqid,
            ]);

        $response->assertOk()->assertJsonStructure(['redirect']);
        $redirect = (string) $response->json('redirect');
        $this->assertStringContainsString($kunde->slug . '/allgemein', $redirect, 'Neue URL muss den Kunden-Slug enthalten.');
        $this->actingAs($user)->get($redirect)->assertOk();
    }
}
