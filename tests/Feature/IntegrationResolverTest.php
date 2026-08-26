<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrationResolverTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Integration\{ConflictFieldPolicy, ImportMatchPolicy};
use App\Models\{Customer, ExternalReference, IntegrationInboxItem};
use App\Services\Integration\{IntegrationResolver, ResolveOutcome};
use App\Services\Integration\Match\EntityMatcher;
use App\Services\Integration\Profiles\CustomerMatchProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class IntegrationResolverTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private IntegrationResolver $resolver;
    private CustomerMatchProfile $profile;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->resolver = app(IntegrationResolver::class);
        $this->profile = app(CustomerMatchProfile::class);
    }

    private function customer(array $attributes = []): Customer {
        return Customer::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'vat_id' => null,
            'email' => null,
        ], $attributes));
    }

    private function resolve(string $externalType, ?string $externalId, array $attributes, ImportMatchPolicy $policy, ConflictFieldPolicy $onConflict = ConflictFieldPolicy::ManualReview): ResolveOutcome {
        return $this->resolver->resolve(
            $this->organization, 'toggl', $this->profile,
            $externalType, $externalId, $attributes, $attributes, $policy, $onConflict, 'api',
        );
    }

    // ── EntityMatcher ─────────────────────────────────────────────────────────

    public function test_matcher_finds_exact_by_vat_id(): void {
        $c = $this->customer(['name' => 'Alpha GmbH', 'vat_id' => 'DE111']);
        $result = app(EntityMatcher::class)->match($this->organization, $this->profile, ['name' => 'Irgendwas', 'vat_id' => 'DE111']);

        $this->assertSame($c->id, $result->uniqueExact()?->id);
    }

    public function test_matcher_fuzzy_single_needs_human(): void {
        $this->customer(['name' => 'Betagamma Logistik GmbH']);
        $result = app(EntityMatcher::class)->match($this->organization, $this->profile, ['name' => 'Betagamma Logistik GmbH']);

        $this->assertNull($result->uniqueExact());
        $this->assertTrue($result->needsHuman());
    }

    // ── Resolver ──────────────────────────────────────────────────────────────

    public function test_existing_reference_links(): void {
        $c = $this->customer(['name' => 'Gamma GmbH']);
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'toggl', 'external_type' => 'client',
            'referenceable_type' => $c->getMorphClass(), 'referenceable_id' => $c->id,
            'external_id' => 'tg-1',
        ]);

        $outcome = $this->resolve('client', 'tg-1', ['name' => 'Gamma GmbH'], ImportMatchPolicy::AutoLinkExactOnly);

        $this->assertSame(ResolveOutcome::LINKED, $outcome->type);
        $this->assertSame($c->id, $outcome->model?->id);
    }

    public function test_exact_match_links_and_writes_reference(): void {
        $c = $this->customer(['name' => 'Delta GmbH', 'vat_id' => 'DE222']);

        $outcome = $this->resolve('client', 'tg-2', ['name' => 'Delta', 'vat_id' => 'DE222'], ImportMatchPolicy::AutoLinkExactOnly);

        $this->assertSame(ResolveOutcome::LINKED, $outcome->type);
        $this->assertSame($c->id, $outcome->model?->id);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => 'toggl', 'external_type' => 'client', 'external_id' => 'tg-2',
            'referenceable_id' => $c->id,
        ]);
    }

    public function test_multiple_exact_candidates_are_ambiguous(): void {
        $this->customer(['name' => 'Epsilon A', 'vat_id' => 'DE333']);
        $this->customer(['name' => 'Epsilon B', 'vat_id' => 'DE333']);

        $outcome = $this->resolve('client', 'tg-3', ['name' => 'Epsilon', 'vat_id' => 'DE333'], ImportMatchPolicy::AutoLinkExactOnly);

        $this->assertSame(ResolveOutcome::AMBIGUOUS, $outcome->type);
        $this->assertSame(IntegrationInboxItem::CASE_AMBIGUOUS, $outcome->inboxItem?->case_type);
        $this->assertCount(2, $outcome->inboxItem?->candidate_ids ?? []);
    }

    public function test_no_match_is_staged_unmatched(): void {
        $outcome = $this->resolve('client', 'tg-4', ['name' => 'Zeta Brandneu KG', 'vat_id' => 'DE999'], ImportMatchPolicy::AutoLinkExactOnly);

        $this->assertSame(ResolveOutcome::STAGED, $outcome->type);
        $item = $outcome->inboxItem;
        $this->assertNotNull($item);
        $this->assertSame(IntegrationInboxItem::CASE_UNMATCHED, $item->case_type);
        $this->assertSame(IntegrationInboxItem::STATUS_OPEN, $item->status);
        $this->assertSame((new Customer)->getMorphClass(), $item->target_type);
        // Inbox-First: NICHT blind angelegt.
        $this->assertSame(0, Customer::query()->where('name', 'Zeta Brandneu KG')->count());
    }

    public function test_opt_in_creates_when_no_match(): void {
        $outcome = $this->resolve('client', 'tg-5', ['name' => 'Eta Neu GmbH', 'vat_id' => 'DE888'], ImportMatchPolicy::AutoLinkAndCreate);

        $this->assertSame(ResolveOutcome::CREATED, $outcome->type);
        $this->assertSame('Eta Neu GmbH', $outcome->model?->name);
        $this->assertDatabaseHas('external_references', ['external_id' => 'tg-5', 'referenceable_id' => $outcome->model?->id]);
    }

    public function test_manual_review_policy_does_not_autolink(): void {
        $this->customer(['name' => 'Theta GmbH', 'vat_id' => 'DE777']);

        $outcome = $this->resolve('client', 'tg-6', ['name' => 'Theta', 'vat_id' => 'DE777'], ImportMatchPolicy::ManualReview);

        $this->assertSame(ResolveOutcome::AMBIGUOUS, $outcome->type);
    }

    public function test_linked_field_conflict_creates_conflict_item(): void {
        $c = $this->customer(['name' => 'Iota GmbH', 'email' => 'old@iota.test']);
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'lexoffice', 'external_type' => 'contact',
            'referenceable_type' => $c->getMorphClass(), 'referenceable_id' => $c->id,
            'external_id' => 'lx-1',
        ]);

        $outcome = $this->resolver->resolve(
            $this->organization, 'lexoffice', $this->profile, 'contact', 'lx-1',
            ['name' => 'Iota GmbH', 'email' => 'new@iota.test'],
            ['name' => 'Iota GmbH', 'email' => 'new@iota.test'],
            ImportMatchPolicy::AutoLinkExactOnly, ConflictFieldPolicy::ManualReview, 'api',
        );

        $this->assertSame(ResolveOutcome::CONFLICT, $outcome->type);
        $this->assertContains('email', $outcome->inboxItem?->diff_fields ?? []);
        $this->assertSame('old@iota.test', $c->fresh()->email, 'Lokaler Wert unangetastet (ManualReview)');
    }

    public function test_linked_conflict_remote_wins_updates(): void {
        $c = $this->customer(['name' => 'Kappa GmbH', 'email' => 'old@kappa.test']);
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'lexoffice', 'external_type' => 'contact',
            'referenceable_type' => $c->getMorphClass(), 'referenceable_id' => $c->id,
            'external_id' => 'lx-2',
        ]);

        $outcome = $this->resolver->resolve(
            $this->organization, 'lexoffice', $this->profile, 'contact', 'lx-2',
            ['name' => 'Kappa GmbH', 'email' => 'new@kappa.test'],
            ['name' => 'Kappa GmbH', 'email' => 'new@kappa.test'],
            ImportMatchPolicy::AutoLinkExactOnly, ConflictFieldPolicy::RemoteWins, 'api',
        );

        $this->assertSame(ResolveOutcome::LINKED, $outcome->type);
        $this->assertSame('new@kappa.test', $c->fresh()->email);
    }

    /**
     * F8/E6 (Vollscan 2026-08-23, MVP-723): Der Resolver legt Adresse und
     * Bankverbindung in contact_addresses/contact_bank_accounts an — die
     * Inline-Spalten entstehen nur noch über die Projektion. Sonst hätte der
     * Import eine zweite Wahrheit neben der führenden Tabelle geschrieben.
     */
    public function test_create_routes_contact_details_into_contact_tables(): void {
        $outcome = $this->resolve('client', 'tg-contact-1', [
            'name' => 'My GmbH',
            'address_street' => 'Hauptstr. 1',
            'address_zip' => '10115',
            'address_city' => 'Berlin',
            'country' => 'DE',
            'bank_iban' => 'DE02120300000000202051',
        ], ImportMatchPolicy::AutoLinkAndCreate);

        $customer = $outcome->model;
        $this->assertInstanceOf(Customer::class, $customer);

        // Adress-/Bankfelder liegen verschlüsselt in der Tabelle — über das
        // Modell prüfen statt über assertDatabaseHas.
        $address = $customer->addresses()->firstOrFail();
        $this->assertSame('Hauptstr. 1', $address->street);
        $this->assertSame('10115', $address->zip);
        $this->assertSame('Berlin', $address->city);
        $this->assertSame('DE', $address->country_code);
        $this->assertTrue((bool) $address->is_primary);

        $this->assertSame('DE02120300000000202051', $customer->bankAccounts()->firstOrFail()->iban);

        // Projektion: die Inline-Spalten bleiben lesbar wie bisher.
        $this->assertSame('Berlin', $customer->fresh()->address_city);
        $this->assertSame('DE02120300000000202051', $customer->fresh()->bank_iban);
    }

    /**
     * Teil-Update aus einem Konflikt: Nur das abweichende Adressfeld ändert
     * sich, der Rest der primären Adresse bleibt stehen (kein Leerräumen).
     */
    public function test_remote_wins_updates_the_primary_address_in_place(): void {
        $c = $this->customer(['name' => 'Ny GmbH']);
        app(\App\Services\Stammdaten\ContactDetailsWriter::class)->writeAddress($c, [
            'street' => 'Altweg 2', 'zip' => '20095', 'city' => 'Hamburg', 'country' => 'DE',
        ]);
        $c->refresh();

        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'lexoffice', 'external_type' => 'contact',
            'referenceable_type' => $c->getMorphClass(), 'referenceable_id' => $c->id,
            'external_id' => 'lx-addr-1',
        ]);

        $attrs = ['name' => 'Ny GmbH', 'address_city' => 'Bremen'];
        $this->resolver->resolve(
            $this->organization, 'lexoffice', $this->profile, 'contact', 'lx-addr-1',
            $attrs, $attrs, ImportMatchPolicy::AutoLinkExactOnly, ConflictFieldPolicy::RemoteWins, 'api',
        );

        $this->assertSame(1, $c->addresses()->count(), 'Kein zweiter Adressdatensatz.');
        $address = $c->addresses()->firstOrFail();
        $this->assertSame('Bremen', $address->city);
        $this->assertSame('Altweg 2', $address->street, 'Nicht gelieferte Felder bleiben stehen.');
        $this->assertSame('Bremen', $c->fresh()->address_city);
    }

    public function test_staging_is_idempotent(): void {
        $attrs = ['name' => 'Lambda Brandneu KG', 'vat_id' => 'DE555'];
        $this->resolve('client', 'tg-9', $attrs, ImportMatchPolicy::AutoLinkExactOnly);
        $this->resolve('client', 'tg-9', $attrs, ImportMatchPolicy::AutoLinkExactOnly);

        $this->assertSame(1, IntegrationInboxItem::query()->where('plugin_id', 'toggl')->where('dedupe_key', 'client:tg-9')->count());
    }
}
