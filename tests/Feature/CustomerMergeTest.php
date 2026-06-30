<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerMergeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{Customer, CustomerMergeDismissal, ExternalReference, Project, User};
use App\Plugins\Toggl\{TogglImportService, TogglPlugin};
use App\Services\{CustomerDuplicateFinder, CustomerMergeService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class CustomerMergeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function customer(array $attributes = []): Customer {
        return Customer::factory()->create(array_merge(['organization_id' => $this->organization->id], $attributes));
    }

    private function project(Customer $customer, string $name, string $slug): Project {
        return Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    private function ref(Customer $customer, string $plugin, string $type, string $externalId): ExternalReference {
        return ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => $plugin,
            'external_type' => $type,
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->id,
            'external_id' => $externalId,
        ]);
    }

    public function test_merge_repoints_relations_and_deletes_source(): void {
        $target = $this->customer(['name' => 'LDS Systems GmbH', 'email' => 'lds@example.test']);
        $source = $this->customer(['name' => 'LDS Systems', 'vat_id' => 'DE811111111']);

        $project = $this->project($source, 'Wartung Süd', 'wartung-sued');
        $ref = $this->ref($source, 'toggl', 'client', 'LDS Systems');

        app(CustomerMergeService::class)->merge($source, $target);

        $this->assertNull(Customer::query()->find($source->id), 'Quelle wurde gelöscht');
        $this->assertSame($target->id, $project->fresh()->customer_id);
        $this->assertSame($target->id, $ref->fresh()->referenceable_id);
        // Leeres Ziel-Feld wird aus der Quelle aufgefüllt.
        $this->assertSame('DE811111111', $target->fresh()->vat_id);
    }

    public function test_merge_resolves_project_slug_collision(): void {
        $target = $this->customer();
        $source = $this->customer();

        // Der CustomerObserver legt für jeden Kunden ein Standardprojekt mit dem
        // Slug "wartung" an — beim Merge kollidieren diese zwangsläufig und müssen
        // eindeutig gemacht werden (zusammengesetzter Unique-Index customer_id+slug).
        $this->assertSame('wartung', $source->defaultProject()?->slug);
        $this->assertSame('wartung', $target->defaultProject()?->slug);

        app(CustomerMergeService::class)->merge($source, $target);

        $projects = Project::query()->where('customer_id', $target->id)->get();
        $this->assertCount(2, $projects, 'Beide Standardprojekte hängen jetzt am Ziel');
        $this->assertCount(2, $projects->pluck('slug')->unique(), 'Slugs bleiben eindeutig');
    }

    public function test_merge_handles_external_reference_collision(): void {
        $target = $this->customer();
        $source = $this->customer();

        // Beide tragen eine Toggl-Client-Referenz (gleiches Plugin+Typ) → Kollision.
        $this->ref($target, 'toggl', 'client', 'Kunde X');
        $sourceClient = $this->ref($source, 'toggl', 'client', 'Kunde Y');
        // Eine nicht kollidierende Lexoffice-Referenz darf umgehängt werden.
        $sourceContact = $this->ref($source, 'lexoffice', 'contact', 'uuid-123');

        app(CustomerMergeService::class)->merge($source, $target);

        $this->assertNull(ExternalReference::query()->find($sourceClient->id), 'Kollidierende Quell-Ref verworfen');
        $this->assertSame($target->id, $sourceContact->fresh()->referenceable_id, 'Nicht kollidierende Ref umgehängt');
        $this->assertSame(1, ExternalReference::query()
            ->where('referenceable_type', $target->getMorphClass())
            ->where('referenceable_id', $target->id)
            ->where('external_type', 'client')->count());
    }

    public function test_merge_keeps_alternate_toggl_client_as_alias(): void {
        $target = $this->customer();
        $source = $this->customer();

        // Beide tragen eine eigene Toggl-Client-Referenz → Kollision beim Merge.
        $this->ref($target, TogglPlugin::ID, 'client', 'Kunde X');
        $this->ref($source, TogglPlugin::ID, 'client', 'Kunde Y');

        app(CustomerMergeService::class)->merge($source, $target);

        // Der abweichende Quell-Client lebt als Alias weiter und zeigt aufs Ziel.
        $this->assertDatabaseHas('external_reference_aliases', [
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => 'client',
            'external_id' => 'Kunde Y',
            'referenceable_type' => (new Customer)->getMorphClass(),
            'referenceable_id' => $target->id,
        ]);

        // Ein Re-Import mit dem alten Client-Namen ordnet ohne Inbox-Umweg dem
        // Ziel-Kunden zu (Name-Fallback griffe hier nicht).
        $matched = app(TogglImportService::class)->matchCustomer($this->organization, 'Kunde Y');
        $this->assertNotNull($matched, 'Alter Toggl-Client muss über den Alias auflösen');
        $this->assertSame($target->id, $matched->id);
    }

    public function test_finder_detects_exact_match_by_vat_id(): void {
        // Ziel hat Lexoffice-Anbindung → bleibt bestehen.
        $withLex = $this->customer(['name' => 'Thieme Transporte', 'vat_id' => 'DE822222222', 'lexoffice_contact_number' => 'C-1']);
        $togglOnly = $this->customer(['name' => 'Thieme Transporte GmbH', 'vat_id' => 'DE822222222']);

        $candidates = app(CustomerDuplicateFinder::class)->candidates($this->organization, CustomerDuplicateFinder::CONF_EXACT);

        $this->assertCount(1, $candidates);
        $pair = $candidates->first();
        $this->assertSame(CustomerDuplicateFinder::CONF_EXACT, $pair['confidence']);
        $this->assertSame($withLex->id, $pair['target']->id);
        $this->assertSame($togglOnly->id, $pair['source']->id);
        $this->assertContains('vat_id', $pair['reasons']);
    }

    public function test_command_auto_merges_exact_pairs(): void {
        $this->customer(['name' => 'A GmbH', 'vat_id' => 'DE833333333', 'lexoffice_contact_number' => 'C-9']);
        $this->customer(['name' => 'A GmbH alt', 'vat_id' => 'DE833333333']);

        $this->artisan('customer:merge-duplicates', ['--organization' => $this->organization->id, '--apply' => true])
            ->assertExitCode(0);

        $this->assertSame(1, Customer::query()->where('vat_id', 'DE833333333')->count());
    }

    public function test_dismiss_excludes_pair_from_finder(): void {
        $a = $this->customer(['name' => 'Doppel GmbH', 'company' => null, 'vat_id' => null, 'email' => null]);
        $b = $this->customer(['name' => 'Doppel GmbH', 'company' => null, 'vat_id' => null, 'email' => null]);

        $finder = app(CustomerDuplicateFinder::class);
        $this->assertCount(1, $finder->candidates($this->organization));

        CustomerMergeDismissal::query()->create(array_merge(
            CustomerMergeDismissal::pairKey($a->id, $b->id),
            ['organization_id' => $this->organization->id],
        ));

        $this->assertCount(0, $finder->candidates($this->organization));
    }

    public function test_merge_endpoint_requires_billing_permission(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $user->assignRole(\App\Enums\User\UserRole::Callcenter->value);
        $target = $this->customer();
        $source = $this->customer();

        $this->actingAs($user)
            ->post(route('customers.duplicates.merge'), ['source' => $source->sqid, 'target' => $target->sqid])
            ->assertForbidden();

        $this->assertNotNull(Customer::query()->find($source->id));
    }

    public function test_merge_endpoint_merges_for_admin(): void {
        $target = $this->customer(['name' => 'Ziel GmbH']);
        $source = $this->customer(['name' => 'Quelle GmbH']);

        $this->actingAs($this->admin)
            ->post(route('customers.duplicates.merge'), ['source' => $source->sqid, 'target' => $target->sqid])
            ->assertRedirect(route('customers.duplicates.index'));

        $this->assertNull(Customer::query()->find($source->id));
    }
}
