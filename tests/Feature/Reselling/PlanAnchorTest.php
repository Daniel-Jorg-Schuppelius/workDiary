<?php
/*
 * Created on   : Wed Nov 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanAnchorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\{LinkOrigin, PeriodStatus};
use App\Models\Audit\AuditLog;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink, ResalePlanLink, ResaleSubscription};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\{LexofficeArticle, LexofficeRecurringTemplate, LexofficeRecurringTemplateLine, LexofficeVoucher, LexofficeVoucherLine};
use App\Services\Reselling\Plan\PlanSection;
use App\Services\Reselling\Register\{LineFit, LinkProposer, PeriodPlanner};
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\InteractsWithPlugins;
use Tests\TestCase;

/**
 * Phase 183: Vorlagenanker im Vorschlagslauf (MVP-1741), Abrechnungslauf im
 * Vorlagenblock (MVP-1742), Perioden an eigenem Bestand (MVP-1743), stille
 * Audit-Felder (MVP-1744).
 */
class PlanAnchorTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;
    use WithOrganization;

    private const string CONTACT = 'c-hafen';

    private LexofficeArticle $exchange;

    private LexofficeArticle $standard;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-10-26');
        $this->enablePluginFor($this->organization, LexofficePlugin::ID, ['api_key' => 'lex-key', 'request_interval' => '0']);
        $this->exchange = $this->article('art-exo', 'Exchange Online (Plan 1)');
        $this->standard = $this->article('art-std', 'Microsoft 365 Business Standard');
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Hafen- und Schleppbetrieb']);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => self::CONTACT, 'referenceable_type' => $this->customer->getMorphClass(), 'referenceable_id' => $this->customer->getKey(),
        ]);
    }

    private function article(string $externalId, string $name): LexofficeArticle {
        return LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => $externalId, 'name' => $name, 'article_number' => strtoupper($externalId),
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '3.95', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function subscription(string $externalId, string $startsOn, array $attributes = []): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'external_id' => $externalId,
            'label' => $this->exchange->name, 'customer_id' => $this->customer->id, 'article_ref' => 'lex:' . $this->exchange->id, 'quantity' => 1,
            'starts_on' => $startsOn, 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR', 'sale_unit_price' => '47.40',
        ], $attributes));
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    private function template(string $id, ?LexofficeArticle $article = null, ?string $next = '2027-10-16'): void {
        $article ??= $this->exchange;
        $template = LexofficeRecurringTemplate::create([
            'organization_id' => $this->organization->id, 'external_id' => $id, 'contact_external_id' => self::CONTACT, 'contact_name' => 'Hafen- und Schleppbetrieb',
            'customer_id' => $this->customer->id, 'title' => 'Rechnung Exchange ' . $id, 'execution_status' => 'ACTIVE', 'execution_interval' => 'ANNUALLY',
            'starts_on' => '2024-10-16', 'next_execution_on' => $next, 'is_finalized' => true, 'is_last_execution_failed' => false, 'currency' => 'EUR',
            'lines_synced_at' => now(), 'synced_at' => now(),
        ]);
        LexofficeRecurringTemplateLine::create([
            'organization_id' => $this->organization->id, 'template_id' => $template->id, 'position' => 1, 'kind' => 'service',
            'external_article_id' => $article->external_id, 'lexoffice_article_id' => $article->id, 'name' => $article->name,
            'quantity' => 12, 'unit_name' => 'Monat', 'unit_net' => '3.95', 'total_net' => '47.40', 'tax_rate' => 19, 'currency' => 'EUR',
        ]);
    }

    private function planLink(ResaleSubscription $subscription, string $template, LinkOrigin $origin = LinkOrigin::Confirmed): void {
        ResalePlanLink::query()->create([
            'organization_id' => $this->organization->id, 'subscription_id' => $subscription->id, 'plan_key' => 'lexoffice:' . $template,
            'line_position' => 1, 'quantity' => 1, 'origin' => $origin, 'confirmed_at' => $origin === LinkOrigin::Proposed ? null : now(),
        ]);
    }

    private function invoice(string $number, string $date, ?string $template): void {
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-' . $number, 'contact_external_id' => self::CONTACT, 'voucher_type' => 'invoice',
            'recurring_template_id' => $template, 'voucher_status' => 'open', 'voucher_number' => $number, 'voucher_date' => $date,
            'total_amount' => 56.41, 'currency' => 'EUR', 'archived' => false, 'lines_synced_at' => now(),
        ]);
        LexofficeVoucherLine::create([
            'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => 1, 'type' => 'service',
            'external_article_id' => $this->exchange->external_id, 'lexoffice_article_id' => $this->exchange->id, 'name' => $this->exchange->name,
            'quantity' => 12, 'unit_name' => 'Monat', 'unit_net' => '3.95', 'total_net' => '47.40', 'tax_rate' => 19, 'currency' => 'EUR',
        ]);
    }

    private function periodStarting(ResaleSubscription $subscription, string $startsOn): ResalePeriod {
        return $subscription->periods()->whereDate('starts_on', $startsOn)->with('links')->firstOrFail();
    }

    public function test_line_from_a_template_goes_to_the_subscription_of_its_template_line_not_to_the_nearer_sibling(): void {
        $sibling = $this->subscription('CNL-A', '2026-10-17');
        $anchored = $this->subscription('CNL-B', '2026-10-20');
        $this->template('tpl-b');
        $this->planLink($anchored, 'tpl-b');
        // Die Vorlage lief am 16.10.: einen Tag vor dem Geschwister, vier vor dem eigenen Abo.
        $this->invoice('RE/2026/0900', '2026-10-16', 'tpl-b');

        $result = (new LinkProposer)->propose($this->organization);

        $period = $this->periodStarting($anchored, '2026-10-20');
        $this->assertSame(PeriodStatus::Billed, $period->status);
        $link = $period->links->firstOrFail();
        $this->assertSame('RE/2026/0900', $link->voucher_number);
        $this->assertSame(LinkOrigin::Auto, $link->origin, 'der Anker gilt als sicherer Treffer');
        $this->assertNotNull($period->decided_at);
        $this->assertStringContainsString('Rechnung Exchange tpl-b', (string) $link->note);
        $this->assertSame(1, $result['auto_confirmed']);
        $this->assertSame(PeriodStatus::Open, $this->periodStarting($sibling, '2026-10-17')->status);
    }

    public function test_anchored_line_is_never_proposed_outside_its_chain(): void {
        $sibling = $this->subscription('CNL-A', '2026-10-17');
        // Das Abo der Vorlage beginnt erst in vier Tagen: seine Periode wird noch nicht bewertet.
        $anchored = $this->subscription('CNL-B', '2026-10-30');
        $this->template('tpl-b');
        $this->planLink($anchored, 'tpl-b');
        $this->invoice('RE/2026/0901', '2026-10-25', 'tpl-b');

        (new LinkProposer)->propose($this->organization);

        $this->assertSame(0, ResalePeriodLink::query()->count(), 'das Geschwister-Abo nimmt die Position nicht');
        $this->assertSame(PeriodStatus::Open, $this->periodStarting($sibling, '2026-10-17')->status);
    }

    public function test_anchor_follows_the_predecessor_chain(): void {
        $sibling = $this->subscription('CNL-A', '2024-10-17');
        $successor = $this->subscription('CNL-B2', '2026-10-20');
        $predecessor = $this->subscription('CNL-B1', '2024-10-20', ['status' => 'superseded', 'ends_on' => '2026-10-19', 'successor_id' => $successor->id]);
        $this->template('tpl-b');
        $this->planLink($successor, 'tpl-b');
        $this->invoice('RE/2025/0700', '2025-10-18', 'tpl-b');

        (new LinkProposer)->propose($this->organization);

        $this->assertSame('RE/2025/0700', $this->periodStarting($predecessor, '2025-10-20')->links->firstOrFail()->voucher_number, 'Lizenzjahr des Vorgängers');
        $this->assertSame(PeriodStatus::Open, $this->periodStarting($sibling, '2025-10-17')->status);
    }

    public function test_without_proof_the_date_heuristic_stays(): void {
        $sibling = $this->subscription('CNL-A', '2024-10-17');
        $other = $this->subscription('CNL-B', '2026-10-20');
        $third = $this->subscription('CNL-C', '2026-10-21');
        $fourth = $this->subscription('CNL-D', '2026-10-22');
        // Nur vorgeschlagen (Namensvergleich): kein Anker.
        $this->template('tpl-proposed');
        $this->planLink($other, 'tpl-proposed', LinkOrigin::Proposed);
        $this->invoice('RE/2026/0910', '2026-10-16', 'tpl-proposed');
        // Vorlage inzwischen auf einen anderen Artikel umgebaut: kein Anker.
        $this->template('tpl-rebuilt', $this->standard);
        $this->planLink($third, 'tpl-rebuilt');
        $this->invoice('RE/2025/0911', '2025-10-16', 'tpl-rebuilt');
        // Rechnung aus der Zeit vor dem Abo der Vorlage: sie gehörte einem anderen.
        $this->template('tpl-early');
        $this->planLink($fourth, 'tpl-early');
        $this->invoice('RE/2024/0912', '2024-10-16', 'tpl-early');

        (new LinkProposer)->propose($this->organization);

        $numbers = ResalePeriodLink::query()->where('subscription_id', $sibling->id)->orderBy('voucher_date')->pluck('voucher_number')->all();
        $this->assertSame(['RE/2024/0912', 'RE/2025/0911', 'RE/2026/0910'], $numbers, 'ohne Anker entscheidet der nächste Periodenbeginn');
    }

    public function test_voucher_dialog_and_fit_know_the_anchor(): void {
        $sibling = $this->subscription('CNL-A', '2026-08-20');
        $anchored = $this->subscription('CNL-B', '2026-07-01');
        $this->template('tpl-b');
        $this->planLink($anchored, 'tpl-b');
        // 71 Tage nach Periodenbeginn: ohne Anker „unsicher“ und „weit vom Periodenbeginn“.
        $this->invoice('RE/2026/0880', '2026-09-10', 'tpl-b');

        (new LinkProposer)->propose($this->organization);

        $period = $this->periodStarting($anchored, '2026-07-01');
        $link = $period->links->firstOrFail();
        $this->assertSame(LinkOrigin::Auto, $link->origin);
        $line = $link->mirrorLine();
        $this->assertNotNull($line);
        $this->assertTrue(LinkProposer::isSure($period, $anchored, $line));
        $fit = LineFit::forPeriod($line, $period);
        $this->assertSame(LineFit::PLAN, $fit->kind);
        $this->assertFalse($fit->needsConfirmation());
        $this->assertSame(__('resale.fit.plan'), $fit->label());

        $other = LineFit::forPeriod($line, $this->periodStarting($sibling, '2026-08-20'));
        $this->assertSame(LineFit::OTHER_PLAN, $other->kind);
        $this->assertTrue($other->needsConfirmation(), 'von Hand an ein anderes Abo nur mit Bestätigung');

        // Die Zuordnung der Vorlagenposition zu lösen nimmt den Anker im selben Prozess zurück.
        ResalePlanLink::query()->firstOrFail()->delete();
        $this->assertTrue(LineFit::forPeriod($line, $period)->needsConfirmation(), 'ohne Anker gilt wieder die Datumsregel');
        $this->assertFalse(LinkProposer::isSure($period, $anchored, $line));
    }

    public function test_plan_section_shows_the_billing_run_per_assignment(): void {
        $anchored = $this->subscription('CNL-B', '2026-10-20');
        $this->template('tpl-b');
        $this->planLink($anchored, 'tpl-b');
        $this->invoice('RE/2026/0900', '2026-10-16', 'tpl-b');
        $link = ResalePlanLink::query()->firstOrFail();

        $run = static fn(): array => app(PlanSection::class)->build(app('currentOrganization'), collect([$anchored]), [(int) $anchored->customer_id])['templates'][0]['runs'][$link->id];
        app()->instance('currentOrganization', $this->organization);
        $before = $run();
        $this->assertSame(PlanSection::RUN_UNLINKED, $before['state'], 'Beleg da, Periode offen: das fehlende Glied');
        $this->assertSame('RE/2026/0900', $before['voucher']);
        $this->assertSame('2026-10-20', $before['period']?->starts_on->toDateString());

        (new LinkProposer)->propose($this->organization);
        $this->assertSame(PlanSection::RUN_BILLED, $run()['state']);

        $this->actingAs($this->orgAdmin())->get(route('finance.resale.reconcile.show', $this->customer))->assertOk()
            ->assertSee(__('resale.plan.run.voucher', ['number' => 'RE/2026/0900', 'date' => '16.10.2026']))
            ->assertSee(__('resale.plan.run.billed'));
    }

    public function test_open_periods_of_own_holdings_are_labelled_as_own_holding(): void {
        $own = $this->subscription('own.example', '2026-01-10', ['kind' => 'domain', 'provider' => 'domainreselling', 'label' => 'own.example', 'customer_id' => null, 'is_own_holding' => true, 'article_ref' => null]);
        $foreign = $this->subscription('CNL-A', '2026-01-10');

        $ownPeriod = $own->periods()->firstOrFail();
        $this->assertSame(PeriodStatus::Open, $ownPeriod->status);
        $this->assertSame(__('resale.holder.own'), $ownPeriod->statusLabel());
        $this->assertSame('neutral', $ownPeriod->statusTone());
        $foreignPeriod = $foreign->periods()->firstOrFail();
        $this->assertSame(PeriodStatus::Open->label(), $foreignPeriod->statusLabel());
        $this->assertSame('error', $foreignPeriod->statusTone());
    }

    public function test_heartbeat_fields_stay_out_of_the_audit_log(): void {
        $subscription = $this->subscription('CNL-A', '2026-01-10');
        $audits = static fn(): int => AuditLog::query()->where('auditable_type', MorphMap::stableKey(ResaleSubscription::class))->where('auditable_id', $subscription->id)->where('event', 'updated')->count();
        $before = $audits();

        $subscription->forceFill(['last_seen_at' => now()])->save();
        $this->assertSame($before, $audits(), 'nur das Lebenszeichen: keine Zeile');

        $this->travel(1)->days();
        $subscription->forceFill(['last_seen_at' => now(), 'quantity' => 2])->save();
        $this->assertSame($before + 1, $audits());
        $changes = AuditLog::query()->where('auditable_type', MorphMap::stableKey(ResaleSubscription::class))->where('auditable_id', $subscription->id)->where('event', 'updated')->latest('id')->firstOrFail()->changes;
        $this->assertArrayNotHasKey('last_seen_at', $changes['after']);
        $this->assertSame(2, (int) $changes['after']['quantity']);
    }
}
