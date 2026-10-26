<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProposalAutoConfirmTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\{LinkOrigin, PeriodStatus};
use App\Models\Audit\AuditLog;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Reselling\{ResalePeriodLink, ResaleSubscription};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\{LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine};
use App\Services\Reselling\Register\{LinkProposer, PeriodPlanner};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Vorschlagslauf im Zeitplan mit automatischer Bestätigung (Feature 185,
 * MVP-1406/1408): sichere Treffer (gleicher Artikel, ein Beleg, Lizenzzahl =
 * Menge, Datum dicht am Periodenbeginn) werden entschieden, unsichere bleiben
 * Vorschlag; der Beleg aus dem Rechnungsvorschlag gilt immer als sicher.
 */
class ProposalAutoConfirmTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private LexofficeArticle $exchange;

    private LexofficeArticle $standard;

    private Customer $schub;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-10-26');
        $this->exchange = $this->article('art-exo', 'Exchange Online (Plan 1)', '3.95');
        $this->standard = $this->article('art-std', 'Microsoft 365 Business Standard', '11.70');
        $this->schub = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Schub- und Schleppreederei']);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-schub', 'referenceable_type' => $this->schub->getMorphClass(), 'referenceable_id' => $this->schub->getKey(),
        ]);
    }

    private function article(string $externalId, string $name, string $price): LexofficeArticle {
        return LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => $externalId, 'name' => $name, 'article_number' => strtoupper($externalId),
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => $price, 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
    }

    private function subscription(LexofficeArticle $article, int $quantity, string $startsOn, ?string $articleRef = null): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'external_id' => 'CNL-' . $article->external_id . '-' . $startsOn,
            'label' => $article->name, 'customer_id' => $this->schub->id, 'article_ref' => $articleRef ?? 'lex:' . $article->id, 'quantity' => $quantity,
            'starts_on' => $startsOn, 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR', 'sale_unit_price' => '47.40',
        ]);
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    private function invoice(string $externalId, string $number, string $date, LexofficeArticle $article, int $lines, float $quantity = 12): LexofficeVoucher {
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => $externalId, 'contact_external_id' => 'c-schub', 'voucher_type' => 'invoice',
            'voucher_status' => 'open', 'voucher_number' => $number, 'voucher_date' => $date, 'total_amount' => 100, 'currency' => 'EUR', 'archived' => false, 'lines_synced_at' => now(),
        ]);
        for ($i = 1; $i <= $lines; $i++) {
            LexofficeVoucherLine::create([
                'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => $i, 'type' => 'service',
                'external_article_id' => $article->external_id, 'lexoffice_article_id' => $article->id, 'name' => $article->name,
                'quantity' => $quantity, 'unit_name' => 'Monat', 'unit_net' => $article->net_unit_price?->getAmount(), 'total_net' => (string) round($quantity * ($article->net_unit_price?->toFloat() ?? 0.0), 2), 'tax_rate' => 19, 'currency' => 'EUR',
            ]);
        }

        return $voucher;
    }

    public function test_sure_matches_are_auto_confirmed_and_unsure_ones_stay_proposals(): void {
        // Sicher: fünf Exchange-Positionen eines Belegs am Periodenbeginn für den 5er-Vertrag.
        $sure = $this->subscription($this->exchange, 5, '2026-10-18');
        $this->invoice('v-1134', 'RE/2026/1134', '2026-10-18', $this->exchange, 5);
        // Unsicher: Standard-Abo ohne Artikelschlüssel (Namensheuristik) — gleiche Rechnung, gleicher Tag.
        $byName = $this->subscription($this->standard, 1, '2026-10-09', 'none');
        $byName->forceFill(['article_ref' => null])->save();
        $this->invoice('v-1130', 'RE/2026/1130', '2026-10-09', $this->standard, 1);
        // Unsicher: Rechnung 60 Tage nach Periodenbeginn.
        $late = $this->subscription($this->exchange, 1, '2026-07-01');
        $this->invoice('v-late', 'RE/2026/1099', '2026-08-30', $this->exchange, 1);

        $result = (new LinkProposer)->propose($this->organization);

        $this->assertSame(1, $result['auto_confirmed']);
        $surePeriod = $sure->periods()->firstOrFail();
        $this->assertSame(PeriodStatus::Billed, $surePeriod->status);
        $this->assertNotNull($surePeriod->decided_at, 'automatisch entschieden');
        $this->assertNull($surePeriod->decided_by_user_id);
        $this->assertSame(5, $surePeriod->links()->where('origin', LinkOrigin::Auto->value)->count());
        $this->assertSame(1, AuditLog::query()->where('event', LinkProposer::AUDIT_AUTO_CONFIRMED)->count());

        $namePeriod = $byName->periods()->firstOrFail();
        $this->assertSame(PeriodStatus::Billed, $namePeriod->status, 'per Name zugeordnet …');
        $this->assertNull($namePeriod->decided_at, '… aber nur als Vorschlag');
        $this->assertSame(LinkOrigin::Proposed, $namePeriod->links()->firstOrFail()->origin);

        $latePeriod = $late->periods()->firstOrFail();
        $this->assertSame(PeriodStatus::Billed, $latePeriod->status);
        $this->assertNull($latePeriod->decided_at, '60 Tage nach Beginn ist nicht sicher');

        // Ein zweiter Lauf fasst die automatische Entscheidung nicht an; Zurücknehmen löst sie wie einen Vorschlag.
        (new LinkProposer)->propose($this->organization);
        $this->assertSame(5, ResalePeriodLink::query()->where('period_id', $surePeriod->id)->where('origin', LinkOrigin::Auto->value)->count());
        $this->actingAs($this->orgAdmin())->post(route('finance.resale.periods.reopen', $surePeriod->sqid))->assertRedirect();
        $this->assertSame(0, ResalePeriodLink::query()->where('period_id', $surePeriod->id)->count());
        $this->assertSame(PeriodStatus::Open, $surePeriod->fresh()?->status);

        $this->assertNotNull(LinkProposer::lastRunAt($this->organization), 'Zeitstempel des letzten Laufs');
    }

    public function test_the_voucher_created_from_the_draft_is_preferred_and_sure(): void {
        // Zwei gleiche Abos; der Entwurfsstempel der zweiten Periode zeigt auf den Beleg v-draft.
        $first = $this->subscription($this->exchange, 1, '2026-09-01');
        $second = $this->subscription($this->exchange, 1, '2026-09-03');
        $second->periods()->firstOrFail()->forceFill(['draft_reference' => 'v-draft', 'draft_created_at' => now()])->save();
        $this->invoice('v-other', 'RE/2026/0001', '2026-09-01', $this->exchange, 1);
        $this->invoice('v-draft', 'RE/2026/0002', '2026-09-20', $this->exchange, 1);

        (new LinkProposer)->propose($this->organization);

        $this->assertSame('RE/2026/0002', $second->periods()->firstOrFail()->links()->firstOrFail()->voucher_number, 'der Beleg aus dem Entwurf gehört zur gestempelten Periode');
        $this->assertSame(LinkOrigin::Auto, $second->periods()->firstOrFail()->links()->firstOrFail()->origin);
        $this->assertNull($second->periods()->firstOrFail()->draft_reference, 'Stempel fällt, der Entwurf wurde Rechnung');
        $this->assertSame('RE/2026/0001', $first->periods()->firstOrFail()->links()->firstOrFail()->voucher_number);
    }

    public function test_schedule_command_runs_the_proposal_per_organization(): void {
        $this->subscription($this->exchange, 1, '2026-10-01');
        $this->invoice('v-1', 'RE/2026/0100', '2026-10-01', $this->exchange, 1);

        $this->assertSame(0, Artisan::call('resale:propose'));

        $this->assertStringContainsString('1 automatisch bestätigt', Artisan::output());
        $this->assertSame(1, ResalePeriodLink::query()->where('origin', LinkOrigin::Auto->value)->count());
        foreach (['de', 'en', 'es', 'fr', 'it'] as $locale) {
            $this->assertTrue(app('translator')->has('scheduler.job.resale.propose', $locale, false), "Label fehlt: $locale");
            $this->assertTrue(app('translator')->has('scheduler.job.domain.refresh_details', $locale, false), "Label fehlt: $locale");
        }
    }
}
