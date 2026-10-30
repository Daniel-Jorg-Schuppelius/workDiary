<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResalePeriodBulkTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\{LinkOrigin, PeriodStatus};
use App\Models\Audit\AuditLog;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\User;
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\{LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine};
use App\Services\Reselling\Register\{LinkProposer, PeriodPlanner};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Sammelaktionen und Vertrauensstufe der Periodenseite (Feature 185,
 * MVP-1415/1416): Vorschläge bestätigen, verzichten mit einem Grund,
 * Entwurf je Empfänger; unsichere Vorschläge tragen ihr Abzeichen, der
 * Standardfilter zeigt sie.
 */
class ResalePeriodBulkTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    private LexofficeArticle $premium;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-04');
        $this->admin = $this->orgAdmin();
        $this->premium = LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-bp', 'name' => 'Microsoft 365 Business Premium', 'article_number' => 'ART-BP',
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '20.60', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Badhaus Bäder GmbH']);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-kl', 'referenceable_type' => $this->customer->getMorphClass(), 'referenceable_id' => $this->customer->getKey(),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function subscription(array $attributes = []): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'label' => 'Microsoft 365 Business Premium',
            'customer_id' => $this->customer->id, 'article_ref' => 'lex:' . $this->premium->id, 'quantity' => 1, 'starts_on' => '2025-08-05',
            'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR', 'sale_unit_price' => '247.20',
        ], $attributes));
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    private function voucherLine(string $number, string $date, float $quantity = 12): LexofficeVoucherLine {
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-' . $number, 'contact_external_id' => 'c-kl', 'voucher_type' => 'invoice',
            'voucher_status' => 'paid', 'voucher_number' => $number, 'voucher_date' => $date, 'total_amount' => 100, 'currency' => 'EUR', 'archived' => false,
            'voucher_text' => '', 'lines_synced_at' => now(),
        ]);

        return LexofficeVoucherLine::create([
            'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => 1, 'type' => 'service',
            'external_article_id' => $this->premium->external_id, 'lexoffice_article_id' => $this->premium->id,
            'name' => 'Microsoft 365 Business Premium', 'quantity' => $quantity, 'unit_name' => 'Monat',
            'unit_net' => '20.60', 'total_net' => (string) round(20.60 * $quantity, 2), 'tax_rate' => 19, 'currency' => 'EUR',
        ]);
    }

    /** Unsicherer Vorschlag: Rechnung 62 Tage nach Periodenbeginn — außerhalb des 45-Tage-Fensters, bleibt Vorschlag. */
    private function unsureProposal(): ResalePeriod {
        $subscription = $this->subscription();
        $this->voucherLine('RE/2025/0900', '2025-10-06');
        (new LinkProposer)->propose($this->organization);
        $period = $subscription->periods()->where('starts_on', '2025-08-05')->firstOrFail()->load('links');
        $this->assertTrue($period->isProposedOnly(), 'außerhalb des Fensters: kein sicherer Treffer, keine automatische Bestätigung');

        return $period;
    }

    public function test_periods_page_defaults_to_problems_and_keeps_proposals_as_a_spot_check(): void {
        $period = $this->unsureProposal();
        $open = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'starts_on' => '2025-09-01']);

        // Standard „Nur Probleme" (Phase 163): Vorschläge gelten ohne Bestätigung und sind kein Problem.
        $page = $this->actingAs($this->admin)->get(route('finance.resale.periods.index'))->assertOk();
        $page->assertSee('data-bulk-form', false)
            ->assertSee(route('finance.resale.periods.bulk.waive.create'), false)
            ->assertSee('Exchange Online (Plan 1)')
            ->assertDontSee('name="ids[]" value="' . $period->sqid . '"', false);
        $spot = $this->actingAs($this->admin)->get(route('finance.resale.periods.index', ['status' => 'proposed']))->assertOk();
        $spot->assertSee(route('finance.resale.periods.bulk.confirm'), false)
            ->assertSee(__('resale.link.confidence.unsure'))
            ->assertSee('name="ids[]" value="' . $period->sqid . '"', false)
            ->assertDontSee('Exchange Online (Plan 1)');
        $this->assertSame(2, $open->periods()->due()->count(), 'jährlich ab 09/2025: 2025 und 2026 fällig');
    }

    public function test_bulk_confirm_confirms_proposed_periods_and_skips_the_rest(): void {
        $period = $this->unsureProposal();
        $open = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'starts_on' => '2025-09-01'])->periods()->firstOrFail();
        $before = AuditLog::query()->where('event', 'resale_period.confirmed')->count();

        $this->actingAs($this->admin)->from(route('finance.resale.periods.index'))
            ->post(route('finance.resale.periods.bulk.confirm'), ['ids' => [$period->sqid, $open->sqid]])
            ->assertRedirect(route('finance.resale.periods.index'))
            ->assertSessionHas('warning', __('resale.bulk.flash.confirmed', ['done' => 1, 'skipped' => 1]));

        $period->refresh();
        $this->assertSame(PeriodStatus::Billed, $period->status);
        $this->assertNotNull($period->decided_at);
        $this->assertSame($this->admin->id, $period->decided_by_user_id);
        $this->assertSame(1, $period->links()->where('origin', LinkOrigin::Confirmed->value)->count());
        $this->assertSame(PeriodStatus::Open, $open->fresh()?->status, 'ohne Vorschlag bleibt offen');
        $this->assertSame($before + 1, AuditLog::query()->where('event', 'resale_period.confirmed')->count(), 'ein Audit je bestätigter Periode wie bei der Einzelaktion');

        // Ohne Auswahl: Validierungsfehler, nichts passiert.
        $this->actingAs($this->admin)->post(route('finance.resale.periods.bulk.confirm'), [])->assertSessionHasErrors('ids');
    }

    public function test_bulk_waive_dialog_lists_the_periods_and_decides_them_with_one_reason(): void {
        $period = $this->unsureProposal();
        $open = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'starts_on' => '2025-09-01'])->periods()->firstOrFail();
        $decided = $this->subscription(['label' => 'Domain example.de', 'starts_on' => '2025-07-01'])->periods()->firstOrFail();
        $decided->forceFill(['status' => PeriodStatus::Waived, 'decided_at' => now(), 'waived_reason' => 'Kulanz'])->save();

        $this->actingAs($this->admin)->get(route('finance.resale.periods.bulk.waive.create', ['ids' => [$period->sqid, $open->sqid]]))
            ->assertOk()
            ->assertSee(trans_choice('resale.bulk.waive_title', 2, ['count' => 2]))
            ->assertSee('Exchange Online (Plan 1)')
            ->assertSee('name="ids[]" value="' . $open->sqid . '"', false);

        $this->actingAs($this->admin)->from(route('finance.resale.periods.index'))
            ->post(route('finance.resale.periods.bulk.waive'), ['ids' => [$period->sqid, $open->sqid, $decided->sqid], 'decision' => 'disputed', 'reason' => 'Kunde widerspricht'])
            ->assertRedirect(route('finance.resale.periods.index'))
            ->assertSessionHas('success', __('resale.bulk.flash.waived', ['done' => 2, 'skipped' => 1]));

        $this->assertSame(PeriodStatus::Disputed, $period->fresh()?->status);
        $this->assertSame(0, $period->links()->where('origin', LinkOrigin::Proposed->value)->count(), 'Vorschläge gehen mit der Entscheidung weg');
        $this->assertSame('Kunde widerspricht', $open->fresh()?->waived_reason);
        $this->assertSame('Kulanz', $decided->fresh()?->waived_reason, 'entschiedene Periode bleibt unverändert');
        $this->assertSame(2, AuditLog::query()->where('event', 'resale_period.disputed')->count());

        // Grund ist Pflicht.
        $this->actingAs($this->admin)->post(route('finance.resale.periods.bulk.waive'), ['ids' => [$period->sqid], 'decision' => 'waived'])->assertSessionHasErrors('reason');
    }

    public function test_bulk_draft_without_invoice_recipient_warns(): void {
        $period = $this->subscription(['customer_id' => null, 'company_name' => 'Unbekannt UG'])->periods()->firstOrFail();

        $this->actingAs($this->admin)->from(route('finance.resale.periods.index'))
            ->post(route('finance.resale.periods.bulk.draft'), ['ids' => [$period->sqid]])
            ->assertRedirect(route('finance.resale.periods.index'))
            ->assertSessionHas('warning', __('resale.bulk.flash.nothing'));
    }

    public function test_bulk_actions_need_the_manage_permission(): void {
        $period = $this->subscription()->periods()->firstOrFail();
        $this->actingAs($this->orgUser())->post(route('finance.resale.periods.bulk.confirm'), ['ids' => [$period->sqid]])->assertForbidden();
        $this->actingAs($this->orgUser())->get(route('finance.resale.periods.bulk.waive.create', ['ids' => [$period->sqid]]))->assertForbidden();
    }
}
