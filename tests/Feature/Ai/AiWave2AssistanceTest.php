<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiWave2AssistanceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Enums\Communication\{CommunicationDirection, CommunicationNoteType, CommunicationVisibility};
use App\Enums\Customer\CustomerQueryStatus;
use App\Enums\User\Permission;
use App\Models\Ai\{AiCapabilitySetting, AiProviderConnection, AiTextSuggestion};
use App\Models\{AuditLog, Comment, CommunicationNote, Customer, CustomerQuery, DiaryEntry, Project, Quote, QuoteItem, User};
use App\Services\Ai\Dto\{ExplainRequest, ExtractRequest, SummarizeRequest, TranslateRequest};
use App\Services\Ai\Suggestions\{CaseNarrativeSuggestionService, CommunicationNoteSuggestionService, DocumentTranslationSuggestionService, PlanActualExplainService, PortalQuerySuggestionService, SupportDiagnosisSuggestionService};
use App\Services\Ai\Support\CustomerNameMasker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\{FakeAiProvider, FakeAiProviderFactory};
use Tests\TestCase;

/**
 * KI-Welle 2 (Feature 148, MVP-732): Belegsprache-Übersetzung,
 * Portal-Rückfragen verstehen, Kommunikationsnotiz strukturieren,
 * Fallakten-Kurznarrativ, Plan-Ist erklären, Support-Diagnose erklären.
 *
 * Geprüft werden Datenfluss (Maskierung, Whitelist), Gating, Tenancy und die
 * Grundregel „nie Auto-Apply": Fachdaten ändern sich ausschließlich über die
 * ausdrückliche Übernahme.
 */
class AiWave2AssistanceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private FakeAiProvider $fake;

    private AiProviderConnection $cloud;

    private AiProviderConnection $local;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['locale' => 'de']);
        $this->fake = FakeAiProviderFactory::install();

        $this->admin = $this->orgAdmin();
        $this->admin->givePermissionTo([
            Permission::AiUse->value,
            Permission::ProtocolCustomerQueryManage->value,
            Permission::ReportView->value,
        ]);

        $this->cloud = AiProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $this->local = AiProviderConnection::factory()->local()->create(['organization_id' => $this->organization->id]);
    }

    private function enable(string $capability, bool $local = false): void {
        AiCapabilitySetting::factory()->create([
            'organization_id' => $this->organization->id,
            'capability' => $capability,
            'enabled' => true,
            'allowed_connection_ids' => [$local ? $this->local->id : $this->cloud->id],
        ]);
    }

    private function customer(?string $documentLocale = null): Customer {
        return Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Musterfirma GmbH',
            'currency' => 'EUR',
            'hourly_rate' => '90.00',
            'document_locale' => $documentLocale,
            'created_by' => $this->admin->id,
        ]);
    }

    private function draftQuote(Customer $customer): Quote {
        return Quote::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'status' => 'draft',
            'terms' => 'Es gelten unsere allgemeinen Geschäftsbedingungen.',
            'created_by' => $this->admin->id,
        ]);
    }

    // ── (a) documents.item_translate ─────────────────────────────────────────

    public function test_quote_item_is_translated_into_the_document_locale_and_applied_only_on_accept(): void {
        $this->enable(DocumentTranslationSuggestionService::CAPABILITY);
        $customer = $this->customer('en');
        $quote = $this->draftQuote($customer);
        $item = QuoteItem::factory()->create([
            'organization_id' => $this->organization->id,
            'quote_id' => $quote->id,
            'description' => 'Wartung der Anlage bei Musterfirma GmbH',
        ]);
        $this->fake->textResponse = 'Maintenance of the system.';

        $this->actingAs($this->admin)
            ->from(route('quotes.show', $quote))
            ->post(route('ai.assist.quote-item-translate', [$quote, $item]))
            ->assertRedirect(route('quotes.show', $quote))
            ->assertSessionHas('success');

        $sent = $this->fake->calls[0]['request'];
        $this->assertInstanceOf(TranslateRequest::class, $sent);
        $this->assertSame('en', $sent->targetLanguage);
        $this->assertSame('de', $sent->sourceLanguage);
        $this->assertStringContainsString(CustomerNameMasker::PLACEHOLDER, $sent->text);
        $this->assertStringNotContainsString('Musterfirma', $sent->text);

        // Nie Auto-Apply: die Position ist bis zur Übernahme unverändert.
        $this->assertSame('Wartung der Anlage bei Musterfirma GmbH', $item->fresh()?->description);

        $suggestion = AiTextSuggestion::query()->withoutGlobalScopes()->firstOrFail();
        $this->actingAs($this->admin)
            ->from(route('quotes.show', $quote))
            ->post(route('ai.assist.accept', $suggestion), ['text' => 'Maintenance of the system.'])
            ->assertSessionHas('success');

        $this->assertSame('Maintenance of the system.', $item->fresh()?->description);
        $this->assertSame(AiTextSuggestion::STATUS_ACCEPTED, $suggestion->fresh()?->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'ai.suggestion_decided']);
    }

    public function test_translation_is_refused_when_the_document_locale_equals_the_organisation_language(): void {
        $this->enable(DocumentTranslationSuggestionService::CAPABILITY);
        $quote = $this->draftQuote($this->customer()); // keine Belegsprache → Org-Sprache
        $item = QuoteItem::factory()->create([
            'organization_id' => $this->organization->id,
            'quote_id' => $quote->id,
            'description' => 'Wartung',
        ]);

        $this->actingAs($this->admin)
            ->from(route('quotes.show', $quote))
            ->post(route('ai.assist.quote-item-translate', [$quote, $item]))
            ->assertSessionHas('error');

        $this->assertSame(0, $this->fake->callCount());
        $this->assertSame(0, AiTextSuggestion::query()->withoutGlobalScopes()->count());
    }

    public function test_quote_terms_translation_hangs_on_the_quote_and_writes_back_on_accept(): void {
        $this->enable(DocumentTranslationSuggestionService::CAPABILITY);
        $quote = $this->draftQuote($this->customer('fr'));
        $this->fake->textResponse = 'Nos conditions générales de vente s’appliquent.';

        $this->actingAs($this->admin)
            ->from(route('quotes.show', $quote))
            ->post(route('ai.assist.quote-terms-translate', $quote))
            ->assertSessionHas('success');

        $suggestion = AiTextSuggestion::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame((new Quote)->getMorphClass(), $suggestion->subject_type);

        $this->actingAs($this->admin)
            ->from(route('quotes.show', $quote))
            ->post(route('ai.assist.accept', $suggestion), ['text' => 'Nos conditions générales de vente s’appliquent.'])
            ->assertSessionHas('success');

        $this->assertSame('Nos conditions générales de vente s’appliquent.', $quote->fresh()?->terms);
    }

    public function test_translation_button_is_hidden_when_the_capability_is_off(): void {
        $quote = $this->draftQuote($this->customer('en'));
        QuoteItem::factory()->create([
            'organization_id' => $this->organization->id,
            'quote_id' => $quote->id,
            'description' => 'Wartung',
        ]);

        $this->actingAs($this->admin)
            ->get(route('quotes.show', $quote))
            ->assertOk()
            ->assertDontSee(route('ai.assist.quote-terms-translate', $quote), false);
    }

    // ── (b) portal.query_understand ──────────────────────────────────────────

    public function test_portal_query_is_summarised_for_the_agent_and_rejecting_is_audited(): void {
        $this->enable(PortalQuerySuggestionService::CAPABILITY);
        $customer = $this->customer('en');
        $entry = DiaryEntry::factory()->for($this->admin)->create(['organization_id' => $this->organization->id]);
        $query = CustomerQuery::create([
            'organization_id' => $this->organization->id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'customer_id' => $customer->id,
            'asker_name' => 'John Doe',
            'question' => 'When will Musterfirma GmbH receive the replacement part?',
            'status' => CustomerQueryStatus::Open->value,
        ]);
        $this->fake->textResponse = 'Der Kunde fragt nach dem Liefertermin des Ersatzteils.';

        $this->actingAs($this->admin)
            ->from(route('customer-queries.index'))
            ->post(route('ai.assist.portal-query', $query))
            ->assertSessionHas('success');

        $sent = $this->fake->calls[0]['request'];
        $this->assertInstanceOf(SummarizeRequest::class, $sent);
        $this->assertStringContainsString(CustomerNameMasker::PLACEHOLDER, $sent->items[0]);
        $this->assertStringNotContainsString('Musterfirma', implode(' ', $sent->items));
        $this->assertStringContainsString('keine Antwort formulieren', implode(' ', $sent->styleRules));

        $suggestion = AiTextSuggestion::query()->withoutGlobalScopes()->firstOrFail();

        // Lesehilfe: erscheint in der Liste, aber ohne Übernehmen-Ziel.
        $this->actingAs($this->admin)
            ->get(route('customer-queries.index'))
            ->assertOk()
            ->assertSee('Der Kunde fragt nach dem Liefertermin des Ersatzteils.')
            ->assertSee(route('ai.assist.reject', $suggestion), false)
            ->assertDontSee(route('ai.assist.accept', $suggestion), false);

        $this->actingAs($this->admin)
            ->from(route('customer-queries.index'))
            ->post(route('ai.assist.reject', $suggestion))
            ->assertSessionHas('success');

        $this->assertSame(AiTextSuggestion::STATUS_REJECTED, $suggestion->fresh()?->status);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ai.suggestion_decided',
            'auditable_id' => $suggestion->id,
        ]);
    }

    public function test_portal_query_understanding_requires_the_query_permission(): void {
        $this->enable(PortalQuerySuggestionService::CAPABILITY);
        $user = $this->orgUser();
        $user->givePermissionTo([Permission::AiUse->value]);

        $entry = DiaryEntry::factory()->for($this->admin)->create(['organization_id' => $this->organization->id]);
        $query = CustomerQuery::create([
            'organization_id' => $this->organization->id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'question' => 'Frage',
            'status' => CustomerQueryStatus::Open->value,
        ]);

        $this->actingAs($user)
            ->post(route('ai.assist.portal-query', $query))
            ->assertForbidden();
        $this->assertSame(0, $this->fake->callCount());
    }

    // ── (c) communication.note_structure ─────────────────────────────────────

    public function test_communication_note_chips_are_applied_one_by_one_through_the_regular_service(): void {
        $this->enable(CommunicationNoteSuggestionService::CAPABILITY, local: true);
        $note = $this->note();
        $this->fake->extractionResponse = [
            'subject' => 'Rückruf zur Störung',
            'result' => 'Ersatzteil wird bestellt.',
            'next_action' => 'Kunden nach Lieferung informieren.',
            'next_action_due_at' => '2026-09-15',
        ];

        $this->actingAs($this->admin)
            ->from(route('diary.show', $note->notable))
            ->post(route('ai.assist.communication-note', $note))
            ->assertSessionHas('success');

        $sent = $this->fake->calls[0]['request'];
        $this->assertInstanceOf(ExtractRequest::class, $sent);
        $this->assertSame(
            ['subject', 'result', 'next_action', 'next_action_due_at'],
            array_keys($sent->schema)
        );

        $suggestion = AiTextSuggestion::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertCount(4, CommunicationNoteSuggestionService::structuredValues($suggestion));
        $this->assertSame('Telefonat', $note->fresh()?->subject, 'Nie Auto-Apply.');

        $this->actingAs($this->admin)
            ->from(route('diary.show', $note->notable))
            ->post(route('ai.assist.apply', $suggestion), ['field' => 'result'])
            ->assertSessionHas('success');

        $this->assertSame('Ersatzteil wird bestellt.', $note->fresh()?->result);
        $this->assertSame('Telefonat', $note->fresh()?->subject, 'Nur der geklickte Chip wird übernommen.');
        $this->assertCount(3, CommunicationNoteSuggestionService::structuredValues($suggestion->fresh()));
        $this->assertTrue($suggestion->fresh()?->isOpen());
    }

    public function test_confidential_communication_notes_never_reach_the_provider(): void {
        $this->enable(CommunicationNoteSuggestionService::CAPABILITY, local: true);
        $note = $this->note(['confidential' => true]);

        $this->actingAs($this->admin)
            ->from(route('diary.show', $note->notable))
            ->post(route('ai.assist.communication-note', $note))
            ->assertSessionHas('error');

        $this->assertSame(0, $this->fake->callCount());
    }

    public function test_note_structure_is_local_only_and_reports_when_no_local_connection_is_allowed(): void {
        // Cloud-Verbindung für eine `high`-Capability → Kandidatenliste leer.
        $this->enable(CommunicationNoteSuggestionService::CAPABILITY);
        $note = $this->note();

        $this->actingAs($this->admin)
            ->from(route('diary.show', $note->notable))
            ->post(route('ai.assist.communication-note', $note))
            ->assertSessionHas('error');

        $this->assertSame(0, $this->fake->callCount());
    }

    private function note(array $attributes = []): CommunicationNote {
        $entry = DiaryEntry::factory()->for($this->admin)->create(['organization_id' => $this->organization->id]);

        return CommunicationNote::create(array_merge([
            'organization_id' => $this->organization->id,
            'notable_type' => DiaryEntry::class,
            'notable_id' => $entry->id,
            'type' => CommunicationNoteType::Call->value,
            'direction' => CommunicationDirection::Inbound->value,
            'occurred_at' => now()->subHour(),
            'subject' => 'Telefonat',
            'body' => 'Kunde meldet Störung an der Anlage, Ersatzteil fehlt, Rückruf zugesagt.',
            'visibility' => CommunicationVisibility::Internal->value,
            'confidential' => false,
            'created_by_user_id' => $this->admin->id,
        ], $attributes));
    }

    // ── (d) case.timeline_narrative ──────────────────────────────────────────

    public function test_case_narrative_summarises_the_timeline_and_accept_writes_an_internal_comment(): void {
        $this->enable(CaseNarrativeSuggestionService::CAPABILITY, local: true);
        $customer = $this->customer();
        $entry = DiaryEntry::factory()->for($this->admin)->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'title' => 'Störung bei Musterfirma GmbH',
        ]);
        $this->fake->textResponse = 'Der Auftrag wurde angelegt und bearbeitet.';

        $this->actingAs($this->admin)
            ->from(route('diary.show', $entry))
            ->post(route('ai.assist.case-narrative', $entry))
            ->assertSessionHas('success');

        $sent = $this->fake->calls[0]['request'];
        $this->assertInstanceOf(SummarizeRequest::class, $sent);
        $this->assertNotSame([], $sent->items);
        $this->assertStringNotContainsString('Musterfirma', implode(' ', $sent->items));

        $suggestion = AiTextSuggestion::query()->withoutGlobalScopes()->firstOrFail();
        $this->actingAs($this->admin)
            ->from(route('diary.show', $entry))
            ->post(route('ai.assist.accept', $suggestion), ['text' => 'Der Auftrag wurde angelegt und bearbeitet.'])
            ->assertSessionHas('success');

        $this->assertSame(1, Comment::query()->withoutGlobalScopes()->count());
        $this->assertDatabaseHas('comments', ['body' => 'Der Auftrag wurde angelegt und bearbeitet.']);
    }

    // ── (e) plan_actual.explain ──────────────────────────────────────────────

    public function test_plan_actual_explanation_sends_metrics_only(): void {
        $this->enable(PlanActualExplainService::CAPABILITY);
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Neubau Musterfirma GmbH',
            'time_budget' => 600,
        ]);
        $this->fake->textResponse = 'Der Ist-Aufwand liegt deutlich unter dem Plan.';

        $this->actingAs($this->admin)
            ->from(route('reports.economics'))
            ->post(route('ai.assist.plan-actual', $project), [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->endOfMonth()->toDateString(),
            ])
            ->assertSessionHas('success');

        $sent = $this->fake->calls[0]['request'];
        $this->assertInstanceOf(ExplainRequest::class, $sent);
        $this->assertArrayHasKey('plan_minuten', $sent->facts);
        $this->assertSame(600, $sent->facts['plan_minuten']);
        $this->assertStringNotContainsString('Musterfirma', (string) json_encode($sent->facts));
        $this->assertStringNotContainsString('Neubau', (string) json_encode($sent->facts));
    }

    public function test_plan_actual_explanation_needs_a_plan(): void {
        $this->enable(PlanActualExplainService::CAPABILITY);
        // Ohne gepflegtes Zeitbudget/Budget (DB-Default 0) gibt es keinen Plan.
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)
            ->from(route('reports.economics'))
            ->post(route('ai.assist.plan-actual', $project), [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->endOfMonth()->toDateString(),
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, $this->fake->callCount());
    }

    // ── (f) support.diagnose_explain ─────────────────────────────────────────

    public function test_support_facts_are_whitelisted_and_redacted(): void {
        $facts = SupportDiagnosisSuggestionService::factsFor([
            'available' => true,
            'healthy' => false,
            'version' => '1.2.3',
            'environment' => 'production',
            'checks' => [
                ['name' => 'Datenbank', 'ok' => true, 'details' => 'Verbindung "mysql" erreichbar'],
                ['name' => 'Mail', 'ok' => false, 'details' => 'SMTP an 192.168.10.5 fehlgeschlagen, Absender admin@kunde.example'],
                ['name' => 'Storage', 'ok' => false, 'details' => 'Kein Schreibrecht auf /var/www/workdiary/storage/app'],
                ['name' => 'Geheimer Zusatzcheck', 'ok' => false, 'details' => 'Kunde Meier GmbH offline'],
            ],
            'failed_count' => 3,
        ]);

        $this->assertSame('ok', $facts['check_datenbank']);
        $this->assertStringNotContainsString('192.168.10.5', (string) $facts['check_mail']);
        $this->assertStringNotContainsString('admin@kunde.example', (string) $facts['check_mail']);
        $this->assertStringContainsString('[ip]', (string) $facts['check_mail']);
        $this->assertStringContainsString('[mail]', (string) $facts['check_mail']);
        $this->assertStringNotContainsString('/var/www', (string) $facts['check_storage']);
        // Nicht gelistete Checks verlassen die Installation gar nicht.
        $this->assertArrayNotHasKey('check_geheimer_zusatzcheck', $facts);
        $this->assertStringNotContainsString('Meier GmbH', (string) json_encode($facts));
    }

    public function test_support_diagnosis_explains_the_health_block(): void {
        $this->enable(SupportDiagnosisSuggestionService::CAPABILITY);
        $this->admin->givePermissionTo([Permission::PlatformSupportExport->value]);
        $this->app->instance(\App\Services\Support\SupportHealthSummary::class, new class extends \App\Services\Support\SupportHealthSummary {
            public function collect(): array {
                return [
                    'available' => true,
                    'healthy' => false,
                    'version' => '1.0.0',
                    'environment' => 'testing',
                    'checks' => [['name' => 'Queue', 'ok' => false, 'details' => 'Worker antwortet nicht']],
                    'failed_count' => 1,
                ];
            }
        });
        $this->fake->textResponse = 'Der Queue-Worker läuft nicht — zuerst den Dienst prüfen.';

        $this->actingAs($this->admin)
            ->from(route('admin.support.report.index'))
            ->post(route('ai.assist.support-diagnose'))
            ->assertSessionHas('success');

        $sent = $this->fake->calls[0]['request'];
        $this->assertInstanceOf(ExplainRequest::class, $sent);
        $this->assertSame('fehler: Worker antwortet nicht', $sent->facts['check_queue']);

        $suggestion = AiTextSuggestion::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame($this->organization->getMorphClass(), $suggestion->subject_type);
        $this->assertSame(1, AuditLog::query()->withoutGlobalScopes()->where('event', 'ai.invoked')->count());
    }
}
