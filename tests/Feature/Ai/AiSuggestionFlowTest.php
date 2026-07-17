<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiSuggestionFlowTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Enums\User\Permission;
use App\Models\Ai\{AiCapabilitySetting, AiMemoryEntry, AiProviderConnection, AiTextSuggestion};
use App\Models\{Customer, Invoice, InvoiceItem, TimeEntry, User};
use App\Services\Ai\Suggestions\ItemTextSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{RegistersAiCapabilities, WithOrganization};
use Tests\Support\{FakeAiProvider, FakeAiProviderFactory};
use Tests\TestCase;

/**
 * KI-Leistungstexte Ende-zu-Ende (Feature 084, MVP-402–406): Vorschlag →
 * Anzeige → Übernahme/Verwerfen, Blocktext-Erkennung, Sammelaktion über
 * den Queue-Job, „Merken?"-Dialog, Gating und Unveränderlichkeit.
 */
class AiSuggestionFlowTest extends TestCase {
    use RefreshDatabase;
    use RegistersAiCapabilities;
    use WithOrganization;

    private User $user;

    private FakeAiProvider $fake;

    private AiProviderConnection $connection;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->fake = FakeAiProviderFactory::install();

        $this->user = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->user->givePermissionTo([Permission::AiUse->value]);

        $this->connection = AiProviderConnection::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        foreach ([
            ItemTextSuggestionService::CAPABILITY_ITEM => 'formulate',
            ItemTextSuggestionService::CAPABILITY_BLOCK => 'summarize',
            ItemTextSuggestionService::CAPABILITY_TRANSLATE => 'translate',
        ] as $capability => $verb) {
            $this->registerAiCapability($capability, ['verb' => $verb]);
            AiCapabilitySetting::factory()->create([
                'organization_id' => $this->organization->id,
                'capability' => $capability,
                'enabled' => true,
                'allowed_connection_ids' => [$this->connection->id],
            ]);
        }
    }

    private static int $invoiceSeq = 0;

    private function draftInvoice(): Invoice {
        $customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME',
            'currency' => 'EUR',
            'hourly_rate' => '90.00',
            'created_by' => $this->user->id,
        ]);

        return Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'number' => 'R2030-' . str_pad((string) ++self::$invoiceSeq, 4, '0', STR_PAD_LEFT),
            'status' => Invoice::STATUS_DRAFT,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->user->id,
        ]);
    }

    private function item(Invoice $invoice, string $description = 'wartung clients server installation snap'): InvoiceItem {
        return $invoice->items()->create([
            'organization_id' => $this->organization->id,
            'description' => $description,
            'quantity' => '1',
            'unit' => 'Std.',
            'unit_price' => '10',
            'position' => $invoice->items()->count() + 1,
        ]);
    }

    public function test_single_item_suggestion_flow_with_accept(): void {
        $invoice = $this->draftInvoice();
        $item = $this->item($invoice);
        $this->fake->textResponse = 'Wartung der Client- und Server-Systeme; Installation von Snap-Paketen';

        $this->actingAs($this->user)
            ->post(route('ai.suggestions.invoice-item', [$invoice, $item]))
            ->assertRedirect();

        $suggestion = AiTextSuggestion::query()->where('subject_id', $item->id)->firstOrFail();
        $this->assertSame(AiTextSuggestion::STATUS_PROPOSED, $suggestion->status);
        $this->assertSame('wartung clients server installation snap', $suggestion->original);

        // Anzeige auf der Rechnungsseite inkl. Vorschlag.
        $this->actingAs($this->user)->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Wartung der Client- und Server-Systeme; Installation von Snap-Paketen');

        // Übernahme OHNE Bearbeitung → kein Merken-Dialog.
        $this->actingAs($this->user)
            ->post(route('ai.suggestions.accept', $suggestion), ['text' => $suggestion->suggestion])
            ->assertRedirect()
            ->assertSessionMissing('ai_learn');

        $item->refresh();
        $this->assertSame($suggestion->suggestion, $item->description);
        $this->assertNotNull($item->ai_assisted_at);
        $this->assertSame(AiTextSuggestion::STATUS_ACCEPTED, $suggestion->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'ai.suggestion_decided']);
    }

    public function test_accept_with_edit_offers_learn_dialog_and_learn_creates_example(): void {
        $invoice = $this->draftInvoice();
        $item = $this->item($invoice);

        $this->actingAs($this->user)->post(route('ai.suggestions.invoice-item', [$invoice, $item]));
        $suggestion = AiTextSuggestion::query()->where('subject_id', $item->id)->firstOrFail();

        $response = $this->actingAs($this->user)
            ->post(route('ai.suggestions.accept', $suggestion), ['text' => 'Meine korrigierte Fassung'])
            ->assertRedirect()
            ->assertSessionHas('ai_learn');

        $payload = session('ai_learn');
        $this->assertSame('Meine korrigierte Fassung', $payload['content']);
        $this->assertSame((int) $invoice->customer_id, (int) $payload['customer_id']);

        // Bestätigtes Lernen → Beispielpaar auf Kundenebene.
        $this->actingAs($this->user)->post(route('ai.suggestions.learn'), [
            'entry_type' => 'example',
            'source_text' => $payload['source_text'],
            'content' => $payload['content'],
            'customer_id' => $payload['customer_id'],
        ])->assertRedirect();

        $this->assertDatabaseHas('ai_memory_entries', [
            'customer_id' => $invoice->customer_id,
            'entry_type' => 'example',
            'origin' => AiMemoryEntry::ORIGIN_LEARNED,
            'content' => 'Meine korrigierte Fassung',
        ]);
    }

    public function test_bundled_time_entries_use_block_capability(): void {
        $invoice = $this->draftInvoice();
        $item = $this->item($invoice, 'Projekt X (01.07.–15.07.)');
        $entries = TimeEntry::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'description' => 'wartung server',
        ]);
        $item->timeEntries()->sync($entries->pluck('id'));

        $this->actingAs($this->user)
            ->post(route('ai.suggestions.invoice-item', [$invoice, $item]))
            ->assertRedirect();

        $this->assertSame(1, $this->fake->callCount('summarize'));
        $this->assertSame(0, $this->fake->callCount('formulate'));
        $this->assertSame(
            ItemTextSuggestionService::CAPABILITY_BLOCK,
            AiTextSuggestion::query()->where('subject_id', $item->id)->value('capability')
        );
    }

    public function test_suggest_all_queues_jobs_and_stores_suggestions(): void {
        $invoice = $this->draftInvoice();
        $first = $this->item($invoice, 'erster text');
        $second = $this->item($invoice, 'zweiter text');

        // QUEUE_CONNECTION=sync in Tests: Jobs laufen sofort durch den Handler.
        $this->actingAs($this->user)
            ->post(route('ai.suggestions.invoice-all', $invoice))
            ->assertRedirect();

        $this->assertSame(2, AiTextSuggestion::query()
            ->whereIn('subject_id', [$first->id, $second->id])
            ->where('status', AiTextSuggestion::STATUS_PROPOSED)
            ->count());
    }

    public function test_translate_flow_creates_suggestion(): void {
        $invoice = $this->draftInvoice();
        $item = $this->item($invoice, 'Wartung der Serversysteme');
        $this->fake->textResponse = 'Maintenance of the server systems';

        $this->actingAs($this->user)
            ->get(route('ai.suggestions.invoice-item-translate-form', [$invoice, $item]))
            ->assertOk();

        $this->actingAs($this->user)
            ->post(route('ai.suggestions.invoice-item-translate', [$invoice, $item]), ['target_language' => 'en'])
            ->assertRedirect();

        $this->assertSame(1, $this->fake->callCount('translate'));
        $this->assertDatabaseHas('ai_text_suggestions', [
            'subject_id' => $item->id,
            'capability' => ItemTextSuggestionService::CAPABILITY_TRANSLATE,
        ]);
    }

    public function test_issued_invoice_rejects_suggestions_and_accept(): void {
        $invoice = $this->draftInvoice();
        $item = $this->item($invoice);

        $this->actingAs($this->user)->post(route('ai.suggestions.invoice-item', [$invoice, $item]));
        $suggestion = AiTextSuggestion::query()->where('subject_id', $item->id)->firstOrFail();

        $invoice->forceFill(['status' => Invoice::STATUS_ISSUED])->save();

        // Neuer Vorschlag nach Ausstellung: gesperrt (Fehler-Flash oder
        // Policy-403 — je nachdem, wie streng die Update-Policy ist).
        $blocked = $this->actingAs($this->user)
            ->post(route('ai.suggestions.invoice-item', [$invoice->fresh(), $item]));
        $this->assertContains($blocked->getStatusCode(), [302, 403]);

        // Übernahme des offenen Vorschlags: ebenfalls gesperrt.
        $accept = $this->actingAs($this->user)
            ->post(route('ai.suggestions.accept', $suggestion), ['text' => 'x']);
        $this->assertContains($accept->getStatusCode(), [302, 403]);
        $this->assertSame('wartung clients server installation snap', $item->fresh()->description);
        $this->assertNotSame(AiTextSuggestion::STATUS_ACCEPTED, $suggestion->fresh()->status);
    }

    public function test_capability_disabled_yields_error_flash_not_exception(): void {
        AiCapabilitySetting::query()->update(['enabled' => false]);
        $invoice = $this->draftInvoice();
        $item = $this->item($invoice);

        $this->actingAs($this->user)
            ->post(route('ai.suggestions.invoice-item', [$invoice, $item]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, AiTextSuggestion::query()->count());
    }

    public function test_user_without_ai_use_permission_is_forbidden(): void {
        $invoice = $this->draftInvoice();
        $item = $this->item($invoice);
        $stranger = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)
            ->post(route('ai.suggestions.invoice-item', [$invoice, $item]))
            ->assertForbidden();
    }

    public function test_maintenance_expires_suggestions_of_issued_invoices(): void {
        $invoice = $this->draftInvoice();
        $item = $this->item($invoice);
        $this->actingAs($this->user)->post(route('ai.suggestions.invoice-item', [$invoice, $item]));
        $invoice->forceFill(['status' => Invoice::STATUS_ISSUED])->save();

        $this->artisan('ai:maintenance')->assertSuccessful();

        $this->assertSame(
            AiTextSuggestion::STATUS_EXPIRED,
            AiTextSuggestion::query()->where('subject_id', $item->id)->value('status')
        );
    }
}
