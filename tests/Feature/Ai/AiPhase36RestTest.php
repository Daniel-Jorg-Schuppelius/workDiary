<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiPhase36RestTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Ai;

use App\Enums\User\Permission;
use App\Models\Ai\{AiCapabilitySetting, AiProviderConnection};
use App\Models\{Customer, CustomerQuery, Invoice, User};
use App\Services\Ai\Suggestions\CoveringTextSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{RegistersAiCapabilities, WithOrganization};
use Tests\Support\{FakeAiProvider, FakeAiProviderFactory};
use Tests\TestCase;

/**
 * Phase-36-Restpunkte (Feature 084/025): Verbrauchsbericht als eigene
 * Seite, Begleittext-Entwürfe in Versand-/Mahn-Dialog (MVP-405-Rest) und
 * die Portal-Antwort-Übersetzung als Vorschau-Entwurf.
 */
class AiPhase36RestTest extends TestCase {
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
            CoveringTextSuggestionService::CAPABILITY_MAIL_TEXT => 'formulate',
            CoveringTextSuggestionService::CAPABILITY_DUNNING_TEXT => 'formulate',
            CoveringTextSuggestionService::CAPABILITY_ANSWER_TRANSLATE => 'translate',
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

    private function customer(): Customer {
        return Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME Handel',
            'currency' => 'EUR',
            'created_by' => $this->user->id,
        ]);
    }

    private static int $invoiceSeq = 0;

    private function invoice(string $status, ?string $dueOn = null): Invoice {
        return Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer()->id,
            'number' => 'R2031-' . str_pad((string) ++self::$invoiceSeq, 4, '0', STR_PAD_LEFT),
            'status' => $status,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'due_on' => $dueOn,
            'created_by' => $this->user->id,
        ]);
    }

    // ── Verbrauchsbericht ────────────────────────────────────────────────

    public function test_usage_report_page_renders_for_admin(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->get(route('admin.ai.usage'))
            ->assertOk()
            ->assertSee(__('ai.usage.title'))
            ->assertSee(__('ai.usage.months'));
    }

    public function test_usage_report_exports_csv(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($admin)->get(route('admin.ai.usage', ['export' => 'csv']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
    }

    public function test_usage_report_denied_without_permission(): void {
        $plain = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($plain)->get(route('admin.ai.usage'))->assertForbidden();
    }

    // ── Begleittexte (MVP-405-Rest) ──────────────────────────────────────

    public function test_send_dialog_prefills_ai_covering_text_on_request(): void {
        $invoice = $this->invoice(Invoice::STATUS_ISSUED);

        $this->actingAs($this->user)
            ->get(route('invoices.send.form', [$invoice, 'ki' => 1]))
            ->assertOk()
            ->assertSee('Fake-Antwort');

        $this->assertSame(1, $this->fake->callCount('formulate'));
    }

    public function test_send_dialog_without_ki_flag_does_not_invoke_provider(): void {
        $invoice = $this->invoice(Invoice::STATUS_ISSUED);

        $this->actingAs($this->user)
            ->get(route('invoices.send.form', $invoice))
            ->assertOk()
            ->assertSee(__('ai.covering.suggest_mail'));

        $this->assertSame(0, $this->fake->callCount());
    }

    public function test_dun_dialog_prefills_ai_dunning_text_on_request(): void {
        $invoice = $this->invoice(Invoice::STATUS_ISSUED, now()->subDays(14)->toDateString());

        $this->actingAs($this->user)
            ->get(route('invoices.dun.form', [$invoice, 'ki' => 1]))
            ->assertOk()
            ->assertSee('Fake-Antwort');

        $this->assertSame(1, $this->fake->callCount('formulate'));
    }

    public function test_covering_button_hidden_when_capability_disabled(): void {
        AiCapabilitySetting::query()
            ->where('capability', CoveringTextSuggestionService::CAPABILITY_MAIL_TEXT)
            ->update(['enabled' => false]);
        $invoice = $this->invoice(Invoice::STATUS_ISSUED);

        $this->actingAs($this->user)
            ->get(route('invoices.send.form', $invoice))
            ->assertOk()
            ->assertDontSee(__('ai.covering.suggest_mail'));
    }

    // ── Portal-Antwort-Übersetzung ───────────────────────────────────────

    public function test_answer_translate_returns_preview_without_storing(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $query = CustomerQuery::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer()->id,
            'subject_type' => \App\Models\DiaryEntry::class,
            'subject_id' => 1,
            'question' => 'Wann kommt der Techniker?',
            'status' => 'open',
        ]);

        $response = $this->actingAs($admin)->post(route('customer-queries.answer', $query), [
            'answer' => 'Der Termin ist am Montag.',
            'translate_to' => 'en',
            'query_sqid' => $query->sqid,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', __('ai.covering.translated_hint'));
        // Vorschau: Original + Übersetzung als Formular-Entwurf, NICHT gespeichert.
        $response->assertSessionHasInput('answer');
        $this->assertStringContainsString('Fake-Antwort', (string) session()->getOldInput('answer'));
        $this->assertNull($query->fresh()->answer);
        $this->assertSame(1, $this->fake->callCount('translate'));
    }

    public function test_answer_without_translate_stores_directly(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $query = CustomerQuery::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer()->id,
            'subject_type' => \App\Models\DiaryEntry::class,
            'subject_id' => 1,
            'question' => 'Ist das Ersatzteil da?',
            'status' => 'open',
        ]);

        $this->actingAs($admin)->post(route('customer-queries.answer', $query), [
            'answer' => 'Ja, es ist eingetroffen.',
        ])->assertRedirect(route('customer-queries.index'));

        $this->assertSame('Ja, es ist eingetroffen.', $query->fresh()->answer);
        $this->assertSame(0, $this->fake->callCount());
    }
}
