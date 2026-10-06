<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteStatusEnumCastTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Enums\Ai\AiTextSuggestionStatus;
use App\Enums\Sales\QuoteStatus;
use App\Models\Ai\AiTextSuggestion;
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Sales\{Quote, QuoteItem};
use App\Services\Ai\Contracts\ItemTextSuggester;
use App\Services\Invoicing\{OrderConfirmationPdfRenderer, QuoteService};
use App\Services\Privacy\SubjectData\CustomerDocumentsSection;
use App\Support\MorphMap;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Konsolidierungs-Audit 2026-10, k3-10 (Welle 8): das Angebot führt seinen
 * Status als Enum. Ein Vergleich gegen die frühere Zeichenkette ist danach
 * still falsch — Aktionen fehlten oder blieben stehen, Entwürfe ließen sich
 * mailen, die Bindefrist liefe nie ab.
 */
final class QuoteStatusEnumCastTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private Customer $customer;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo('2026-10-05 12:00:00');
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->user = User::factory()->buchhaltung()->create(['organization_id' => $this->org->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->org->id, 'email' => 'einkauf@kunde.example']);
    }

    /** @param array<string, mixed> $attributes */
    private function quote(QuoteStatus $status, array $attributes = []): Quote {
        $quote = Quote::query()->create(array_replace([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'AN-' . $status->value,
            'status' => $status,
            'valid_until' => '2026-11-05',
            'acceptance_token_hash' => CryptoHelper::hash('geheim'),
            'created_by' => $this->user->id,
        ], $attributes));
        $quote->items()->create([
            'organization_id' => $this->org->id,
            'position' => 1,
            'description' => 'Wartung',
            'quantity' => 1,
            'unit_price' => 100,
            'tax_rate' => '19.00',
            'accepted' => $status->isWon() ? true : null,
        ]);

        return $quote->refresh();
    }

    private function page(string $url): string {
        return (string) $this->actingAs($this->user)->get($url)->assertOk()->getContent();
    }

    /** Eigene Meldung statt `assertSee`: Dessen Fehlertext trägt die ganze Seite. */
    private function assertPageHas(string $needle, string $html): void {
        $this->assertTrue(str_contains($html, $needle), "Auf der Seite fehlt: {$needle}");
    }

    private function assertPageLacks(string $needle, string $html): void {
        $this->assertFalse(str_contains($html, $needle), "Auf der Seite steht unerwartet: {$needle}");
    }

    public function test_quote_page_offers_the_actions_that_fit_the_status(): void {
        $draft = $this->quote(QuoteStatus::Draft);
        $html = $this->page(route('quotes.show', $draft));
        $this->assertPageHas('href="' . route('quotes.items.create', $draft) . '"', $html);
        $this->assertPageHas('href="' . route('quotes.markup.form', $draft) . '"', $html);
        $this->assertPageHas('action="' . route('quotes.approve', $draft) . '"', $html);
        $this->assertPageHas('action="' . route('quotes.destroy', $draft) . '"', $html);
        $this->assertPageLacks('href="' . route('quotes.mail.form', $draft) . '"', $html);
        $this->assertPageLacks('action="' . route('quotes.send', $draft) . '"', $html);
        $this->assertPageLacks('action="' . route('quotes.decide', $draft) . '"', $html);
        $this->assertPageLacks('action="' . route('quotes.new-version', $draft) . '"', $html);

        $approved = $this->quote(QuoteStatus::Approved);
        $html = $this->page(route('quotes.show', $approved));
        $this->assertPageHas('action="' . route('quotes.send', $approved) . '"', $html);
        $this->assertPageHas('href="' . route('quotes.mail.form', $approved) . '"', $html);
        $this->assertPageLacks('action="' . route('quotes.approve', $approved) . '"', $html);
        $this->assertPageLacks('href="' . route('quotes.items.create', $approved) . '"', $html);

        $sent = $this->quote(QuoteStatus::Sent);
        $html = $this->page(route('quotes.show', $sent));
        $this->assertPageHas('action="' . route('quotes.decide', $sent) . '"', $html);
        $this->assertPageHas('action="' . route('quotes.new-version', $sent) . '"', $html);
        $this->assertPageLacks('href="' . route('quotes.order-confirmation', $sent) . '"', $html);
        $this->assertPageLacks('action="' . route('quotes.convert', $sent) . '"', $html);
        $this->assertPageHas(e(QuoteStatus::Sent->label()), $html);

        foreach ([QuoteStatus::Accepted, QuoteStatus::PartiallyAccepted] as $won) {
            $quote = $this->quote($won);
            $html = $this->page(route('quotes.show', $quote));
            $this->assertPageHas('href="' . route('quotes.order-confirmation', $quote) . '"', $html);
            $this->assertPageHas('action="' . route('quotes.convert', $quote) . '"', $html);
            $this->assertPageLacks('action="' . route('quotes.decide', $quote) . '"', $html);
            $this->assertPageLacks('action="' . route('quotes.new-version', $quote) . '"', $html);
        }

        foreach ([QuoteStatus::Rejected, QuoteStatus::Expired] as $closed) {
            $quote = $this->quote($closed);
            $html = $this->page(route('quotes.show', $quote));
            $this->assertPageHas('action="' . route('quotes.new-version', $quote) . '"', $html);
            $this->assertPageLacks('action="' . route('quotes.convert', $quote) . '"', $html);
        }
    }

    public function test_newer_version_is_named_with_its_status_label(): void {
        $sent = $this->quote(QuoteStatus::Sent);
        $next = app(QuoteService::class)->newVersion($sent, $this->user);

        $this->assertSame(QuoteStatus::Draft, $next->status);
        $this->assertPageHas('V2 (' . e(QuoteStatus::Draft->label()) . ')', $this->page(route('quotes.show', $sent)));
    }

    public function test_portal_shows_the_decision_instead_of_the_form_once_decided(): void {
        $sent = $this->quote(QuoteStatus::Sent);
        $html = (string) $this->get(route('quotes.portal.show', ['quote' => $sent->getRouteKey(), 'token' => 'geheim']))->assertOk()->getContent();
        $this->assertPageHas('action="' . route('quotes.portal.decide', $sent) . '"', $html);

        foreach ([QuoteStatus::Accepted, QuoteStatus::PartiallyAccepted, QuoteStatus::Rejected] as $decided) {
            $quote = $this->quote($decided, ['decided_at' => '2026-10-04 09:00:00']);
            $html = (string) $this->get(route('quotes.portal.show', ['quote' => $quote->getRouteKey(), 'token' => 'geheim']))->assertOk()->getContent();
            $this->assertPageLacks('action="' . route('quotes.portal.decide', $quote) . '"', $html);
            $this->assertPageHas('(' . e($decided->label()) . ',', $html);
        }

        // Abgelaufen ohne Entscheidung: weder Formular noch Entscheidungshinweis.
        $expired = $this->quote(QuoteStatus::Sent, ['number' => 'AN-abgelaufen', 'valid_until' => '2026-10-01']);
        $html = (string) $this->get(route('quotes.portal.show', ['quote' => $expired->getRouteKey(), 'token' => 'geheim']))->assertOk()->getContent();
        $this->assertPageLacks('action="' . route('quotes.portal.decide', $expired) . '"', $html);
    }

    public function test_validity_and_follow_up_run_only_while_the_quote_is_pending(): void {
        foreach (QuoteStatus::cases() as $status) {
            $quote = new Quote(['status' => $status, 'valid_until' => '2026-10-01', 'follow_up_at' => '2026-10-02']);
            $pending = in_array($status, [QuoteStatus::Approved, QuoteStatus::Sent], true);

            $this->assertSame($pending, $quote->isExpired(), "Bindefrist bei {$status->value}");
            $this->assertSame($pending, $quote->isFollowUpDue(), "Nachfassen bei {$status->value}");
        }
    }

    public function test_draft_quotes_cannot_be_mailed_and_only_won_quotes_are_confirmed(): void {
        $draft = $this->quote(QuoteStatus::Draft);
        $this->actingAs($this->user)->get(route('quotes.mail.form', $draft))->assertStatus(422);
        $this->actingAs($this->user)->get(route('quotes.order-confirmation', $draft))->assertStatus(422);

        $sent = $this->quote(QuoteStatus::Sent);
        $this->actingAs($this->user)->get(route('quotes.mail.form', $sent))->assertOk();
        $this->actingAs($this->user)->get(route('quotes.order-confirmation.mail.form', $sent))->assertStatus(422);

        $won = $this->quote(QuoteStatus::PartiallyAccepted);
        $this->actingAs($this->user)->get(route('quotes.order-confirmation.mail.form', $won))->assertOk();
    }

    public function test_order_confirmation_names_a_partial_acceptance_only(): void {
        $renderer = app(OrderConfirmationPdfRenderer::class);

        $partial = view('quotes.order-confirmation-pdf', $renderer->viewData($this->quote(QuoteStatus::PartiallyAccepted)))->render();
        $this->assertPageHas('Teilannahme', $partial);

        $full = view('quotes.order-confirmation-pdf', $renderer->viewData($this->quote(QuoteStatus::Accepted)))->render();
        $this->assertPageLacks('Teilannahme', $full);
    }

    public function test_lifecycle_guards_follow_the_transition_table(): void {
        $service = app(QuoteService::class);

        foreach (QuoteStatus::cases() as $status) {
            $quote = $this->quote($status, ['number' => 'AN-L-' . $status->value]);

            foreach ([
                'approve' => [fn () => $service->approve($quote->fresh(), $this->user), $status === QuoteStatus::Draft],
                'send' => [fn () => $service->send($quote->fresh(), $this->user), $status === QuoteStatus::Approved],
                'reject' => [fn () => $service->reject($quote->fresh()), $status === QuoteStatus::Sent],
            ] as $action => [$call, $allowed]) {
                Quote::query()->whereKey($quote->id)->update(['status' => $status]);
                try {
                    $call();
                    $passed = true;
                } catch (\RuntimeException) {
                    $passed = false;
                }
                $this->assertSame($allowed, $passed, "{$action} aus {$status->value}");
            }
        }
    }

    public function test_status_change_is_audited_with_the_stored_values(): void {
        $quote = $this->quote(QuoteStatus::Draft);
        app(QuoteService::class)->approve($quote, $this->user);

        $changes = json_decode((string) DB::table('audit_logs')
            ->where('auditable_type', MorphMap::stableKey(Quote::class))
            ->where('auditable_id', $quote->id)
            ->where('event', 'updated')
            ->orderByDesc('id')
            ->value('changes'), true);

        $this->assertSame('draft', $changes['before']['status'] ?? null);
        $this->assertSame('approved', $changes['after']['status'] ?? null);
    }

    public function test_billing_report_counts_quotes_by_stored_status(): void {
        $this->quote(QuoteStatus::Draft);
        $this->quote(QuoteStatus::Accepted, ['decided_at' => now()]);
        $this->quote(QuoteStatus::Rejected, ['decided_at' => now()]);
        $admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);

        $response = $this->actingAs($admin)->get(route('reports.billing', ['from' => '2026-10-01', 'to' => '2026-10-31']))->assertOk();
        $chain = $response->viewData('documentChain');

        $this->assertSame(['accepted' => 1, 'draft' => 1, 'rejected' => 1], collect($chain['quotes'])->sortKeys()->all());
        $this->assertSame(50.0, $chain['acceptance_rate']);
        $this->assertPageHas('Angebote: ' . e(QuoteStatus::Accepted->label()), (string) $response->getContent());
    }

    public function test_maintenance_keeps_suggestions_of_draft_quotes_only(): void {
        $suggestion = function (Quote $quote): AiTextSuggestion {
            /** @var QuoteItem $item */
            $item = $quote->items()->firstOrFail();

            return AiTextSuggestion::query()->create([
                'organization_id' => $this->org->id,
                'subject_type' => $item->getMorphClass(),
                'subject_id' => $item->id,
                'capability' => ItemTextSuggester::CAPABILITY_QUOTE_ITEM,
                'original' => 'Wartung',
                'suggestion' => 'Wartung der Anlage',
                'status' => AiTextSuggestionStatus::Proposed,
            ]);
        };
        $draft = $suggestion($this->quote(QuoteStatus::Draft));
        $sent = $suggestion($this->quote(QuoteStatus::Sent));

        $this->artisan('ai:maintenance')->assertSuccessful();

        $this->assertSame(AiTextSuggestionStatus::Proposed, $draft->fresh()->status);
        $this->assertSame(AiTextSuggestionStatus::Expired, $sent->fresh()->status);
    }

    /** Die Auskunft nennt den Status jetzt wie bei Rechnungen als Text statt als Speicherwert. */
    public function test_subject_data_export_names_the_status(): void {
        $this->quote(QuoteStatus::PartiallyAccepted);

        $families = collect((new CustomerDocumentsSection)->build($this->customer)['families'])->keyBy('table');
        $row = array_combine(array_keys($families['quotes']['columns']), $families['quotes']['rows'][0]);

        $this->assertSame(QuoteStatus::PartiallyAccepted->label(), $row['status']);
    }
}
