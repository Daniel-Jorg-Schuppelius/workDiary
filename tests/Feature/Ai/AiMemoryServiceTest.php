<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiMemoryServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Enums\Ai\AiMemoryEntryType;
use App\Models\Ai\AiMemoryEntry;
use App\Models\{Customer, Organization};
use App\Services\Ai\AiMemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * KI-Gedächtnis (Feature 025, MVP-401): Ebenen-Vorrang, Budget-Trimmung,
 * bestätigtes Lernen, DSGVO-Export und Kunden-Löschkaskade.
 */
class AiMemoryServiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const CAPABILITY = 'test.formulate';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function service(): AiMemoryService {
        return app(AiMemoryService::class);
    }

    private function customer(): Customer {
        return Customer::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_customer_entries_rank_before_org_and_capability_defaults(): void {
        $customer = $this->customer();

        AiMemoryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'capability' => self::CAPABILITY,
            'term' => 'capability-default',
        ]);
        AiMemoryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'term' => 'org-begriff',
        ]);
        AiMemoryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'term' => 'kunden-begriff',
        ]);

        $glossary = $this->service()->glossaryFor($this->organization, self::CAPABILITY, (int) $customer->id);

        $this->assertSame(
            ['kunden-begriff', 'org-begriff', 'capability-default'],
            array_map(static fn ($e): string => $e->term, $glossary)
        );
    }

    public function test_foreign_customer_and_foreign_capability_entries_are_excluded(): void {
        $customer = $this->customer();
        $otherCustomer = $this->customer();

        AiMemoryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $otherCustomer->id,
            'term' => 'fremder-kunde',
        ]);
        AiMemoryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'capability' => 'andere.capability',
            'term' => 'fremde-capability',
        ]);
        AiMemoryEntry::factory()->inactive()->create([
            'organization_id' => $this->organization->id,
            'term' => 'inaktiv',
        ]);

        $glossary = $this->service()->glossaryFor($this->organization, self::CAPABILITY, (int) $customer->id);

        $this->assertSame([], $glossary);
    }

    public function test_budget_trims_lowest_priority_entries_first(): void {
        config()->set('ai.memory_budget_characters', 500);
        $customer = $this->customer();

        AiMemoryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'term' => 'kunde',
            'content' => str_repeat('K', 300),
        ]);
        AiMemoryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'term' => 'org',
            'content' => str_repeat('O', 150),
        ]);
        AiMemoryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'capability' => self::CAPABILITY,
            'term' => 'default',
            'content' => str_repeat('D', 300), // sprengt das Budget → fliegt
        ]);

        $entries = $this->service()->entriesFor($this->organization, self::CAPABILITY, (int) $customer->id);

        $this->assertSame(
            ['kunde', 'org'],
            $entries->map(static fn (AiMemoryEntry $e): string => (string) $e->term)->all()
        );
    }

    public function test_glossary_translation_is_resolved_per_target_language(): void {
        AiMemoryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'term' => 'Wartungsvertrag',
            'content' => 'jährlicher Servicevertrag',
            'translations' => ['en' => 'maintenance agreement', 'fr' => 'contrat de maintenance'],
        ]);

        $glossary = $this->service()->glossaryFor($this->organization, self::CAPABILITY, null, 'en');

        $this->assertSame('maintenance agreement', $glossary[0]->translation);
    }

    public function test_remember_learned_marks_origin_and_is_auditable(): void {
        $entry = $this->service()->rememberLearned($this->organization, null, [
            'entry_type' => AiMemoryEntryType::Glossary,
            'term' => 'TK',
            'content' => 'Telefonanlage Auerswald',
        ]);

        $this->assertSame(AiMemoryEntry::ORIGIN_LEARNED, $entry->origin);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $entry->getMorphClass(),
            'auditable_id' => $entry->id,
            'event' => 'created',
        ]);
    }

    public function test_style_rules_and_examples_are_returned_typed(): void {
        AiMemoryEntry::factory()->styleRule('Keine Anglizismen')->create([
            'organization_id' => $this->organization->id,
        ]);
        AiMemoryEntry::factory()->example('wartung server', 'Wartung der Serversysteme')->create([
            'organization_id' => $this->organization->id,
        ]);

        $rules = $this->service()->styleRulesFor($this->organization, self::CAPABILITY);
        $examples = $this->service()->examplesFor($this->organization, self::CAPABILITY);

        $this->assertSame(['Keine Anglizismen'], $rules);
        $this->assertSame('wartung server', $examples[0]->source);
        $this->assertSame('Wartung der Serversysteme', $examples[0]->target);
    }

    public function test_export_and_customer_delete_cascade(): void {
        $customer = $this->customer();
        AiMemoryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'term' => 'kunden-begriff',
        ]);
        AiMemoryEntry::factory()->inactive()->create([
            'organization_id' => $this->organization->id,
            'term' => 'org-begriff',
        ]);

        $export = $this->service()->exportFor($this->organization, (int) $customer->id);
        $this->assertCount(1, $export);
        $this->assertSame('kunde', $export[0]['ebene']);

        $deleted = $this->service()->deleteForCustomer($this->organization, (int) $customer->id);
        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('ai_memory_entries', ['customer_id' => $customer->id]);
        $this->assertDatabaseHas('ai_memory_entries', ['term' => 'org-begriff']);
    }

    public function test_mark_used_feeds_priority_within_same_level(): void {
        $seldom = AiMemoryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'term' => 'selten',
        ]);
        $frequent = AiMemoryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'term' => 'haeufig',
        ]);

        $this->service()->markUsed(collect([$frequent]));
        $this->service()->markUsed(collect([$frequent]));

        $entries = $this->service()->entriesFor($this->organization, self::CAPABILITY);
        $this->assertSame('haeufig', (string) $entries->first()?->term);
        $this->assertSame(2, $frequent->fresh()->usage_count);
        $this->assertSame(0, $seldom->fresh()->usage_count);
    }

    public function test_entries_are_isolated_per_organization(): void {
        $otherOrg = Organization::factory()->create();
        AiMemoryEntry::factory()->create([
            'organization_id' => $otherOrg->id,
            'term' => 'fremde-org',
        ]);

        $this->assertSame([], $this->service()->glossaryFor($this->organization, self::CAPABILITY));
    }
}
