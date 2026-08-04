<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TransferPositionReviewTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Finance\{TransferChannel, TransferTarget};
use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Enums\User\Permission;
use App\Models\Ai\{AiCapabilitySetting, AiProviderConnection, AiTextSuggestion};
use App\Models\{Customer, Project, TimeEntry, User};
use App\Models\Finance\BillingTransfer;
use App\Services\Ai\Suggestions\ItemTextSuggestionService;
use App\Services\Billing\OrganizationDefaultRateResolver;
use App\Services\Finance\BillingTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, RegistersAiCapabilities, WithOrganization};
use Tests\Support\{FakeAiProvider, FakeAiProviderFactory};
use Tests\TestCase;

/**
 * Prüfen der eingefrorenen Übergabe-Positionen (MVP-487/488): Bearbeiten,
 * KI-Textvorschlag und die Grenzen (nur bestätigt, Preis nur mit
 * finance.config).
 */
class TransferPositionReviewTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use RegistersAiCapabilities;
    use WithOrganization;

    private User $accountant;

    private Customer $customer;

    private Project $project;

    private FakeAiProvider $fake;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->fake = FakeAiProviderFactory::install();

        $this->accountant = User::factory()->buchhaltung()->create([
            'organization_id' => $this->organization->id,
            'hourly_rate' => null,
        ]);
        $this->actingAs($this->accountant);

        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'hourly_rate' => '90.00',
            'currency' => 'EUR',
        ]);
        $this->project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Wartung IT',
            'status' => ProjectStatus::Active->value,
            'is_default' => false,
        ]);

        app(OrganizationDefaultRateResolver::class)->flush();
    }

    private function confirmedTransfer(): BillingTransfer {
        TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->accountant->id,
            'date' => '2030-04-01',
            'started_at' => '2030-04-01 09:00:00',
            'ended_at' => '2030-04-01 11:00:00',
            'minutes' => 120,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'exported' => false,
            'description' => 'Server geprüft',
        ]);

        $service = app(BillingTransferService::class);
        $transfer = $service->createDraft(
            $this->customer,
            TransferChannel::Time,
            TransferTarget::File,
            ['from' => '2030-04-01', 'to' => '2030-04-30'],
            null,
            $this->accountant,
        );
        $service->confirm($transfer, $this->accountant);

        return $transfer->fresh();
    }

    private function enableAi(): void {
        // Permissions müssen im Team-Kontext der Organisation vergeben werden.
        $this->grantPermissions($this->accountant, [Permission::AiUse]);
        $this->actingAs($this->accountant->fresh());
        $connection = AiProviderConnection::factory()->create(['organization_id' => $this->organization->id]);

        foreach ([
            ItemTextSuggestionService::CAPABILITY_ITEM => 'formulate',
            ItemTextSuggestionService::CAPABILITY_BLOCK => 'summarize',
        ] as $capability => $verb) {
            $this->registerAiCapability($capability, ['verb' => $verb]);
            AiCapabilitySetting::factory()->create([
                'organization_id' => $this->organization->id,
                'capability' => $capability,
                'enabled' => true,
                'allowed_connection_ids' => [$connection->id],
            ]);
        }
    }

    public function test_text_bearbeiten_ohne_finance_config_laesst_preis_unveraendert(): void {
        $transfer = $this->confirmedTransfer();
        $position = $transfer->positions->first();

        $this->patch(route('finance.transfers.positions.update', [$transfer, $position]), [
            'name' => 'Neue Bezeichnung',
            'description' => 'Geprüfter Text',
            'quantity' => 99,
            'unit_price' => 1,
        ])->assertSessionHasNoErrors();

        $position->refresh();
        $this->assertSame('Neue Bezeichnung', $position->name);
        $this->assertSame('Geprüfter Text', $position->description);
        $this->assertSame(2.0, $position->quantityFloat(), 'Menge darf ohne finance.config nicht wandern.');
        $this->assertSame(90.0, $position->unitPriceFloat());
    }

    public function test_mit_finance_config_sind_menge_und_preis_aenderbar(): void {
        // finance.config trägt in dieser Installation die Admin-Rolle.
        $this->actingAs($this->orgAdmin());
        $transfer = $this->confirmedTransfer();
        $position = $transfer->positions->first();

        $this->patch(route('finance.transfers.positions.update', [$transfer, $position]), [
            'name' => $position->name,
            'description' => $position->description,
            'quantity' => 3,
            'unit_price' => 100,
        ])->assertSessionHasNoErrors();

        $position->refresh();
        $this->assertSame(3.0, $position->quantityFloat());
        $this->assertSame(100.0, $position->unitPriceFloat());
        $this->assertSame(300.0, $position->amountFloat());
        $this->assertDatabaseHas('billing_transfer_events', [
            'billing_transfer_id' => $transfer->id,
            'event' => 'position_edited',
        ]);
    }

    public function test_nach_der_uebergabe_ist_die_position_gesperrt(): void {
        $transfer = $this->confirmedTransfer();
        $position = $transfer->positions->first();
        app(BillingTransferService::class)->markTransferred($transfer, null, 'exports/finance/test.csv', $this->accountant);

        $this->patch(route('finance.transfers.positions.update', [$transfer->fresh(), $position]), [
            'name' => 'Zu spät',
        ])->assertForbidden();
    }

    public function test_ki_vorschlag_und_uebernahme_schreiben_den_positionstext(): void {
        $this->enableAi();
        $this->fake->textResponse = 'Wartung der Serverinfrastruktur';
        $transfer = $this->confirmedTransfer();
        $position = $transfer->positions->first();

        $this->post(route('finance.transfers.positions.suggest', [$transfer, $position]))
            ->assertSessionHas('success');

        $suggestion = AiTextSuggestion::query()
            ->where('subject_type', $position->getMorphClass())
            ->where('subject_id', $position->id)
            ->firstOrFail();
        $this->assertSame('Wartung der Serverinfrastruktur', $suggestion->suggestion);

        $this->post(route('ai.suggestions.accept', $suggestion), ['text' => $suggestion->suggestion])
            ->assertSessionHas('success');

        $position->refresh();
        $this->assertSame('Wartung der Serverinfrastruktur', $position->description);
        $this->assertNotNull($position->ai_assisted_at);
    }

    public function test_korrektur_erzeugt_eigenen_nachweis_mit_verweis(): void {
        $this->actingAs($this->orgAdmin());
        $transfer = $this->confirmedTransfer();
        app(BillingTransferService::class)->markTransferred($transfer, null, 'exports/finance/test.csv', $this->accountant);
        $transfer = $transfer->fresh();

        $this->post(route('finance.transfers.correct', $transfer), ['reason' => 'Nullpositionen'])
            ->assertSessionHasNoErrors();

        $correction = BillingTransfer::query()->where('corrects_transfer_id', $transfer->id)->firstOrFail();
        $this->assertSame(\App\Enums\Finance\TransferStatus::Draft, $correction->status);
        $this->assertSame('Nullpositionen', $correction->correction_reason);
        $this->assertSame($transfer->items->count(), $correction->items->count());

        // Das Original bleibt unangetastet — nur ein Ereignis kommt hinzu.
        $transfer->refresh();
        $this->assertSame(\App\Enums\Finance\TransferStatus::Transferred, $transfer->status);
        $this->assertNotNull($transfer->transferred_at);
        $this->assertDatabaseHas('billing_transfer_events', [
            'billing_transfer_id' => $transfer->id,
            'event' => 'correction_created',
        ]);
        $this->assertDatabaseHas('billing_transfer_events', [
            'billing_transfer_id' => $correction->id,
            'event' => 'created_as_correction',
        ]);
    }

    public function test_korrektur_ist_bestaetigbar_und_erneut_uebertragbar(): void {
        $this->actingAs($this->orgAdmin());
        $transfer = $this->confirmedTransfer();
        app(BillingTransferService::class)->markTransferred($transfer, null, 'exports/finance/test.csv', $this->accountant);

        $correction = app(BillingTransferService::class)->createCorrection($transfer->fresh(), 'Textkorrektur', $this->accountant);
        app(BillingTransferService::class)->confirm($correction, $this->accountant);

        // Positionen sind neu eingefroren und wieder bearbeitbar.
        $position = $correction->fresh()->positions->first();
        $this->assertNotNull($position);
        $this->patch(route('finance.transfers.positions.update', [$correction, $position]), [
            'name' => 'Korrigierte Bezeichnung',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Korrigierte Bezeichnung', $position->fresh()->name);
    }

    public function test_korrektur_nur_nach_uebergabe(): void {
        $this->actingAs($this->orgAdmin());
        $transfer = $this->confirmedTransfer();

        $this->post(route('finance.transfers.correct', $transfer))->assertSessionHasErrors('status');
        $this->assertSame(0, BillingTransfer::query()->where('corrects_transfer_id', $transfer->id)->count());
    }

    public function test_ohne_finance_config_keine_korrektur(): void {
        $transfer = $this->confirmedTransfer();
        app(BillingTransferService::class)->markTransferred($transfer, null, 'exports/finance/test.csv', $this->accountant);

        $this->post(route('finance.transfers.correct', $transfer->fresh()))->assertForbidden();
    }

    public function test_rechnungstexte_werden_vorbelegt_und_sind_aenderbar(): void {
        $this->organization->update(['settings' => array_replace_recursive((array) $this->organization->settings, [
            'invoicing' => [
                'transfer_intro_text' => 'Leistungen für :customer (:from – :to)',
                'transfer_closing_text' => 'Zahlbar ohne Abzug.',
            ],
        ])]);

        $transfer = $this->confirmedTransfer();

        $this->assertStringContainsString($this->customer->name, (string) $transfer->intro_text);
        $this->assertStringContainsString('01.04.2030', (string) $transfer->intro_text);
        $this->assertSame('Zahlbar ohne Abzug.', $transfer->closing_text);

        $this->patch(route('finance.transfers.texts.update', $transfer), [
            'intro_text' => 'Von Hand angepasst',
            'closing_text' => '',
        ])->assertSessionHasNoErrors();

        $transfer->refresh();
        $this->assertSame('Von Hand angepasst', $transfer->intro_text);
        $this->assertNull($transfer->closing_text);
        $this->assertDatabaseHas('billing_transfer_events', [
            'billing_transfer_id' => $transfer->id,
            'event' => 'texts_edited',
        ]);
    }

    public function test_rechnungstexte_nach_uebergabe_gesperrt(): void {
        $transfer = $this->confirmedTransfer();
        app(BillingTransferService::class)->markTransferred($transfer, null, 'exports/finance/test.csv', $this->accountant);

        $this->patch(route('finance.transfers.texts.update', $transfer->fresh()), ['intro_text' => 'Zu spät'])
            ->assertForbidden();
    }

    public function test_positionen_verschieben_entfernen_zusammenfassen(): void {
        $this->actingAs($this->orgAdmin());

        // Zweiter Eintrag an einem anderen Tag ⇒ zwei Positionen.
        TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->accountant->id,
            'date' => '2030-04-02',
            'started_at' => '2030-04-02 09:00:00',
            'ended_at' => '2030-04-02 10:00:00',
            'minutes' => 60,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'exported' => false,
            'description' => 'Zweiter Tag',
        ]);
        $transfer = $this->confirmedTransfer();
        $this->assertGreaterThanOrEqual(2, $transfer->positions->count());

        $first = $transfer->positions->first();
        $second = $transfer->positions->skip(1)->first();

        // Verschieben tauscht die Reihenfolge.
        $this->post(route('finance.transfers.positions.move', [$transfer, $first]), ['direction' => 'down'])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, (int) $first->fresh()->position);
        $this->assertSame(1, (int) $second->fresh()->position);

        // Zusammenfassen addiert Menge und Betrag.
        $expectedQuantity = round($first->quantityFloat() + $second->quantityFloat(), 3);
        $this->post(route('finance.transfers.positions.merge', $transfer), [
            'positions' => [$first->id, $second->id],
        ])->assertSessionHasNoErrors();

        $merged = $transfer->fresh()->positions;
        $this->assertCount(1, $merged);
        $this->assertSame($expectedQuantity, $merged->first()->quantityFloat());

        // Entfernen löscht die letzte Position.
        $this->delete(route('finance.transfers.positions.destroy', [$transfer, $merged->first()]))
            ->assertSessionHasNoErrors();
        $this->assertCount(0, $transfer->fresh()->positions);
        $this->assertDatabaseHas('billing_transfer_events', [
            'billing_transfer_id' => $transfer->id,
            'event' => 'position_removed',
        ]);
    }

    public function test_ohne_ki_recht_kein_vorschlag(): void {
        $transfer = $this->confirmedTransfer();
        $position = $transfer->positions->first();

        $this->post(route('finance.transfers.positions.suggest', [$transfer, $position]))->assertForbidden();
    }

    public function test_manuelle_wortkorrektur_bietet_merken_dialog_an(): void {
        $transfer = $this->confirmedTransfer();
        $position = $transfer->positions->first();
        $position->update(['description' => 'Server geprüfft']);

        $this->patch(route('finance.transfers.positions.update', [$transfer, $position]), [
            'name' => (string) $position->name,
            'description' => 'Server geprüft',
        ])->assertSessionHas('text_correction_learn', [
            'pairs' => [['wrong' => 'geprüfft', 'correct' => 'geprüft']],
        ]);
    }

    public function test_umbau_ohne_wortersetzung_bietet_kein_merken_an(): void {
        $transfer = $this->confirmedTransfer();
        $position = $transfer->positions->first();
        $position->update(['description' => 'Server geprüft']);

        $this->patch(route('finance.transfers.positions.update', [$transfer, $position]), [
            'name' => (string) $position->name,
            'description' => 'Server geprüft und Update eingespielt',
        ])->assertSessionMissing('text_correction_learn');
    }

    public function test_merken_endpoint_legt_gelernten_eintrag_an(): void {
        $this->post(route('text-corrections.learn'), [
            'wrong' => 'geprüfft',
            'correct' => 'geprüft',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('text_corrections', [
            'organization_id' => $this->organization->id,
            'wrong_normalized' => 'geprüfft',
            'correct' => 'geprüft',
            'origin' => \App\Models\TextCorrection::ORIGIN_LEARNED,
            'active' => true,
        ]);
    }

    public function test_merken_duplikat_erhoeht_verwendung_statt_doppelt_anzulegen(): void {
        \App\Models\TextCorrection::factory()->create([
            'organization_id' => $this->organization->id,
            'wrong' => 'Geprüfft',
            'correct' => 'geprüft',
        ]);

        $this->post(route('text-corrections.learn'), [
            'wrong' => 'geprüfft',
            'correct' => 'geprüft',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, \App\Models\TextCorrection::query()->count());
        $this->assertSame(1, (int) \App\Models\TextCorrection::query()->first()->usage_count);
    }

    public function test_merken_ohne_belegrecht_verboten(): void {
        $this->actingAs($this->orgUser());

        $this->post(route('text-corrections.learn'), [
            'wrong' => 'geprüfft',
            'correct' => 'geprüft',
        ])->assertForbidden();
    }
}
