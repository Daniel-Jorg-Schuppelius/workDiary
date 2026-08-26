<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolSuggestionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Enums\Classification\ClassificationDomain;
use App\Enums\OpenIssue\OpenIssueSeverity;
use App\Enums\Protocol\{ProtocolItemResult, ProtocolItemType, ProtocolStatus};
use App\Enums\User\Permission;
use App\Models\Ai\{AiCapabilitySetting, AiProviderConnection, AiTextSuggestion};
use App\Models\{AuditLog, Classification, Customer, DiaryEntry, Organization, Protocol, ProtocolItem, User};
use App\Services\Ai\Suggestions\ProtocolTextSuggestionService;
use App\Services\Ai\Support\CustomerNameMasker;
use App\Services\Protocol\ProtocolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\{FakeAiProvider, FakeAiProviderFactory};
use Tests\TestCase;

/**
 * KI-Welle 1 für Protokolle (Feature 143, MVP-711): Freitext veredeln,
 * Chips klassifizieren, Übernahme über die reguläre Punkt-Erfassung,
 * Sperre für signierte Protokolle, Tenancy, Gating und Budget.
 */
class ProtocolSuggestionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private FakeAiProvider $fake;

    private AiProviderConnection $connection;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->fake = FakeAiProviderFactory::install();

        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->user->givePermissionTo([Permission::AiUse->value]);

        $this->connection = AiProviderConnection::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        foreach ([ProtocolTextSuggestionService::CAPABILITY_TEXT, ProtocolTextSuggestionService::CAPABILITY_CLASSIFY] as $capability) {
            AiCapabilitySetting::factory()->create([
                'organization_id' => $this->organization->id,
                'capability' => $capability,
                'enabled' => true,
                'allowed_connection_ids' => [$this->connection->id],
            ]);
        }
    }

    private function draftProtocol(?User $creator = null): Protocol {
        $creator ??= $this->user;
        $entry = DiaryEntry::factory()->for($creator)->create(['organization_id' => $creator->organization_id]);

        return Protocol::factory()->create([
            'organization_id' => $creator->organization_id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'created_by_user_id' => $creator->id,
            'title' => 'Abnahme Serverraum',
            'status' => ProtocolStatus::Draft->value,
        ]);
    }

    private function textItem(Protocol $protocol, string $description = 'kabel lose, steckdose wackelt'): ProtocolItem {
        return app(ProtocolService::class)->addItem($protocol, $this->user, [
            'label' => 'Sichtprüfung Elektrik',
            'item_type' => ProtocolItemType::Text->value,
            'description' => $description,
        ]);
    }

    private function defectItem(Protocol $protocol): ProtocolItem {
        return app(ProtocolService::class)->addItem($protocol, $this->user, [
            'label' => 'Mangel Verteilung',
            'item_type' => ProtocolItemType::Defect->value,
            'value_json' => ['description' => 'kabel lose am verteiler', 'severity' => 'low'],
        ]);
    }

    public function test_text_suggestion_is_created_with_masked_names_and_no_facts_rule(): void {
        Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Musterfirma GmbH',
            'currency' => 'EUR',
            'hourly_rate' => '90.00',
            'created_by' => $this->user->id,
        ]);
        $protocol = $this->draftProtocol();
        $item = $this->textItem($protocol, 'Kabel bei Musterfirma GmbH lose');
        $this->fake->textResponse = 'Das Kabel ist lose.';

        $this->actingAs($this->user)
            ->from(route('protocols.show', $protocol))
            ->post(route('ai.suggestions.protocol-item', $item))
            ->assertRedirect(route('protocols.show', $protocol))
            ->assertSessionHas('success');

        $this->assertSame(1, $this->fake->callCount('formulate'));
        $sent = $this->fake->calls[0]['request'];
        $this->assertInstanceOf(\App\Services\Ai\Dto\FormulateRequest::class, $sent);
        $this->assertStringContainsString(CustomerNameMasker::PLACEHOLDER, $sent->text);
        $this->assertStringNotContainsString('Musterfirma', $sent->text);
        $this->assertStringContainsString('keine neuen Fakten', implode(' ', $sent->styleRules));
        $this->assertContains('Protokoll: Abnahme Serverraum', $sent->contextHints);

        $suggestion = AiTextSuggestion::query()->where('subject_id', $item->id)->firstOrFail();
        $this->assertSame(ProtocolTextSuggestionService::CAPABILITY_TEXT, $suggestion->capability);
        $this->assertSame('Das Kabel ist lose.', $suggestion->suggestion);
        $this->assertSame((new ProtocolItem)->getMorphClass(), $suggestion->subject_type);

        // Vorschlag ist auf der Detailseite sichtbar, der Punkt unverändert.
        $this->actingAs($this->user)->get(route('protocols.show', $protocol))
            ->assertOk()
            ->assertSee('Das Kabel ist lose.');
        $this->assertSame('Kabel bei Musterfirma GmbH lose', $item->fresh()->description);
    }

    public function test_accept_writes_text_item_description_and_audits(): void {
        $protocol = $this->draftProtocol();
        $item = $this->textItem($protocol);
        $this->fake->textResponse = 'Kabel lose; Steckdose sitzt nicht fest.';
        $this->actingAs($this->user)->post(route('ai.suggestions.protocol-item', $item));
        $suggestion = AiTextSuggestion::query()->where('subject_id', $item->id)->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('ai.suggestions.accept', $suggestion), ['text' => 'Kabel lose; Steckdose sitzt nicht fest.'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('Kabel lose; Steckdose sitzt nicht fest.', $item->fresh()->description);
        $this->assertSame(AiTextSuggestion::STATUS_ACCEPTED, $suggestion->fresh()->status);
        $this->assertDatabaseHas('protocol_events', ['protocol_id' => $protocol->id, 'event' => 'protocol.itemFilled']);

        $audit = AuditLog::query()->where('event', 'ai.suggestion_decided')->latest('id')->firstOrFail();
        $this->assertSame('accepted', $audit->changes['decision']);
        $this->assertSame(ProtocolTextSuggestionService::CAPABILITY_TEXT, $audit->changes['capability']);
        $this->assertArrayNotHasKey('text', $audit->changes);
    }

    public function test_accept_on_defect_item_runs_through_fill_logic(): void {
        $protocol = $this->draftProtocol();
        $item = $this->defectItem($protocol);
        $this->fake->textResponse = 'Kabel am Verteiler lose.';
        $this->actingAs($this->user)->post(route('ai.suggestions.protocol-item', $item));
        $suggestion = AiTextSuggestion::query()->where('subject_id', $item->id)->firstOrFail();
        $this->assertSame('kabel lose am verteiler', $suggestion->original);

        // Editiert vor der Übernahme → Status edited.
        $this->actingAs($this->user)
            ->post(route('ai.suggestions.accept', $suggestion), ['text' => 'Kabel am Verteiler lose (Klemme 3).'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $item->fresh();
        $this->assertSame('Kabel am Verteiler lose (Klemme 3).', $fresh->value_json['description']);
        $this->assertSame('low', $fresh->value_json['severity']);
        $this->assertSame(ProtocolItemResult::NotOk, $fresh->result);
        $this->assertSame($this->user->id, $fresh->measured_by_user_id);
        $this->assertSame(AiTextSuggestion::STATUS_EDITED, $suggestion->fresh()->status);
    }

    public function test_reject_marks_suggestion_and_leaves_item_untouched(): void {
        $protocol = $this->draftProtocol();
        $item = $this->textItem($protocol);
        $this->actingAs($this->user)->post(route('ai.suggestions.protocol-item', $item));
        $suggestion = AiTextSuggestion::query()->where('subject_id', $item->id)->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('ai.suggestions.reject', $suggestion))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(AiTextSuggestion::STATUS_REJECTED, $suggestion->fresh()->status);
        $this->assertSame('kabel lose, steckdose wackelt', $item->fresh()->description);
        $this->assertDatabaseHas('audit_logs', ['event' => 'ai.suggestion_decided', 'auditable_id' => $suggestion->id]);
    }

    public function test_signed_protocol_blocks_new_suggestions_and_acceptance(): void {
        $protocol = $this->draftProtocol();
        $item = $this->textItem($protocol);
        $this->actingAs($this->user)->post(route('ai.suggestions.protocol-item', $item));
        $suggestion = AiTextSuggestion::query()->where('subject_id', $item->id)->firstOrFail();

        $protocol->forceFill(['status' => ProtocolStatus::Signed->value, 'signed_at' => now()])->save();

        $blocked = $this->actingAs($this->user)->post(route('ai.suggestions.protocol-item', $item));
        $this->assertContains($blocked->getStatusCode(), [302, 403]);
        $this->assertSame(1, $this->fake->callCount('formulate'));

        $accept = $this->actingAs($this->user)
            ->post(route('ai.suggestions.accept', $suggestion), ['text' => 'x']);
        $this->assertContains($accept->getStatusCode(), [302, 403]);
        $this->assertSame('kabel lose, steckdose wackelt', $item->fresh()->description);
        $this->assertSame(AiTextSuggestion::STATUS_PROPOSED, $suggestion->fresh()->status);

        // Service-Ebene: signiert → AiException, kein Provider-Aufruf.
        $this->expectException(\App\Services\Ai\Exceptions\AiException::class);
        app(ProtocolTextSuggestionService::class)->suggestForItem($item->fresh(), $this->user);
    }

    public function test_classify_maps_only_known_values_and_apply_runs_fill_logic(): void {
        Classification::factory()->create([
            'organization_id' => $this->organization->id,
            'domain' => ClassificationDomain::DefectType->value,
            'code' => 'elektrik',
            'label' => 'Elektrik',
        ]);
        $protocol = $this->draftProtocol();
        $item = $this->defectItem($protocol);
        $this->fake->classificationResponse = [OpenIssueSeverity::High->label(), 'Elektrik', 'Frei erfunden'];

        $this->actingAs($this->user)
            ->post(route('ai.suggestions.protocol-item-classify', $item))
            ->assertRedirect()
            ->assertSessionHas('success', __('ai.flash.classification_created'));

        $this->assertSame(1, $this->fake->callCount('classify'));
        $sent = $this->fake->calls[0]['request'];
        $this->assertInstanceOf(\App\Services\Ai\Dto\ClassifyRequest::class, $sent);
        $this->assertTrue($sent->multiple);
        $this->assertContains('Elektrik', $sent->catalog);
        $this->assertContains(OpenIssueSeverity::Critical->label(), $sent->catalog);

        $suggestion = AiTextSuggestion::query()
            ->where('subject_id', $item->id)
            ->where('capability', ProtocolTextSuggestionService::CAPABILITY_CLASSIFY)
            ->firstOrFail();
        $chips = ProtocolTextSuggestionService::classificationValues($suggestion);
        $this->assertSame(
            [['severity', 'high'], ['category', 'elektrik']],
            array_map(static fn (array $c): array => [$c['kind'], $c['value']], $chips),
        );

        // Chips auf der Detailseite, Wert noch NICHT übernommen (nie Auto-Apply).
        $this->actingAs($this->user)->get(route('protocols.show', $protocol))
            ->assertOk()
            ->assertSee(__('ai.suggestion.classification_title'))
            ->assertSee('Elektrik');
        $this->assertSame('low', $item->fresh()->value_json['severity']);

        // Erster Chip: Schweregrad → value_json, Vorschlag bleibt mit Rest offen.
        $this->actingAs($this->user)
            ->post(route('ai.suggestions.apply', $suggestion), ['kind' => 'severity', 'value' => 'high'])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame('high', $item->fresh()->value_json['severity']);
        $this->assertSame(AiTextSuggestion::STATUS_PROPOSED, $suggestion->fresh()->status);
        $this->assertCount(1, ProtocolTextSuggestionService::classificationValues($suggestion->fresh()));

        // Fremder Wert wird abgelehnt.
        $this->actingAs($this->user)
            ->post(route('ai.suggestions.apply', $suggestion), ['kind' => 'severity', 'value' => 'critical'])
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertSame('high', $item->fresh()->value_json['severity']);

        // Letzter Chip schließt den Vorschlag.
        $this->actingAs($this->user)
            ->post(route('ai.suggestions.apply', $suggestion), ['kind' => 'category', 'value' => 'elektrik'])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame('elektrik', $item->fresh()->value_json['category']);
        $this->assertSame(AiTextSuggestion::STATUS_ACCEPTED, $suggestion->fresh()->status);
        $this->assertSame(2, AuditLog::query()->where('event', 'ai.suggestion_decided')->where('auditable_id', $suggestion->id)->count());
    }

    public function test_classify_with_only_unknown_values_creates_nothing(): void {
        $protocol = $this->draftProtocol();
        $item = $this->textItem($protocol);
        $this->fake->classificationResponse = ['Frei erfunden'];

        $this->actingAs($this->user)
            ->post(route('ai.suggestions.protocol-item-classify', $item))
            ->assertRedirect()
            ->assertSessionHas('success', __('ai.flash.classification_none'));

        $this->assertSame(0, AiTextSuggestion::query()->count());
    }

    public function test_text_and_classification_suggestions_coexist_per_item(): void {
        $protocol = $this->draftProtocol();
        $item = $this->textItem($protocol);
        $this->fake->classificationResponse = [ProtocolItemResult::NotOk->label()];

        $this->actingAs($this->user)->post(route('ai.suggestions.protocol-item', $item));
        $this->actingAs($this->user)->post(route('ai.suggestions.protocol-item-classify', $item));

        $open = AiTextSuggestion::query()->where('subject_id', $item->id)->where('status', AiTextSuggestion::STATUS_PROPOSED)->pluck('capability')->sort()->values()->all();
        $this->assertSame([ProtocolTextSuggestionService::CAPABILITY_CLASSIFY, ProtocolTextSuggestionService::CAPABILITY_TEXT], $open);

        // Erneuter Textvorschlag ersetzt nur den Textvorschlag.
        $this->actingAs($this->user)->post(route('ai.suggestions.protocol-item', $item));
        $this->assertSame(2, AiTextSuggestion::query()->where('subject_id', $item->id)->where('status', AiTextSuggestion::STATUS_PROPOSED)->count());
    }

    public function test_show_page_has_no_ai_buttons_without_enabled_capability(): void {
        $protocol = $this->draftProtocol();
        $item = $this->textItem($protocol);

        $this->actingAs($this->user)->get(route('protocols.show', $protocol))
            ->assertOk()
            ->assertSee(route('ai.suggestions.protocol-item', $item), false)
            ->assertSee(route('ai.suggestions.protocol-item-classify', $item), false);

        AiCapabilitySetting::query()->update(['enabled' => false]);

        $this->actingAs($this->user)->get(route('protocols.show', $protocol))
            ->assertOk()
            ->assertDontSee(route('ai.suggestions.protocol-item', $item), false)
            ->assertDontSee(route('ai.suggestions.protocol-item-classify', $item), false);

        $this->actingAs($this->user)
            ->post(route('ai.suggestions.protocol-item', $item))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertSame(0, $this->fake->callCount('formulate'));
    }

    public function test_foreign_organization_item_is_not_found(): void {
        $other = Organization::factory()->create();
        $stranger = User::factory()->user()->create(['organization_id' => $other->id]);
        $entry = DiaryEntry::factory()->for($stranger)->create(['organization_id' => $other->id]);
        $protocol = Protocol::factory()->create([
            'organization_id' => $other->id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'created_by_user_id' => $stranger->id,
        ]);
        $item = $protocol->items()->create([
            'sort_order' => 1,
            'item_type' => ProtocolItemType::Text->value,
            'label' => 'Fremd',
            'description' => 'fremder text',
            'required' => false,
        ]);

        $this->actingAs($this->user)
            ->post(route('ai.suggestions.protocol-item', $item))
            ->assertNotFound();
        $this->actingAs($this->user)
            ->post(route('ai.suggestions.protocol-item-classify', $item))
            ->assertNotFound();
        $this->assertSame(0, $this->fake->callCount());
    }

    public function test_user_without_ai_permission_is_forbidden(): void {
        $protocol = $this->draftProtocol();
        $item = $this->textItem($protocol);
        $this->user->revokePermissionTo(Permission::AiUse->value);

        $this->actingAs($this->user)
            ->post(route('ai.suggestions.protocol-item', $item))
            ->assertForbidden();
    }

    public function test_exhausted_budget_yields_error_flash_without_provider_call(): void {
        $this->organization->forceFill([
            'settings' => ['ai' => ['budget' => ['monthly_units' => ['llm' => 1]]]],
        ])->save();
        app()->instance('currentOrganization', $this->organization->fresh());
        $protocol = $this->draftProtocol();
        $item = $this->textItem($protocol);

        $this->actingAs($this->user)
            ->post(route('ai.suggestions.protocol-item', $item))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertStringContainsString('Budget', (string) session('error'));
        $this->assertSame(0, $this->fake->callCount('formulate'));
        $this->assertSame(0, AiTextSuggestion::query()->count());
    }

    public function test_item_without_text_yields_error_flash(): void {
        $protocol = $this->draftProtocol();
        $item = app(ProtocolService::class)->addItem($protocol, $this->user, [
            'label' => 'Leer',
            'item_type' => ProtocolItemType::Boolean->value,
        ]);

        $this->actingAs($this->user)
            ->post(route('ai.suggestions.protocol-item', $item))
            ->assertRedirect()
            ->assertSessionHas('error', __('ai.error.protocol_item_no_text'));
        $this->assertSame(0, $this->fake->callCount());
    }
}
