<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecurringPlanTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\LinkOrigin;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Reselling\{ResalePlanLink, ResaleSubscription};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\{LexofficeArticle, LexofficeRecurringTemplate, LexofficeVoucher};
use App\Plugins\Lexoffice\Services\{LexofficeRecurringTemplateSync, LexofficeVoucherLineSync};
use App\Services\Reselling\Plan\{PlanAudit, PlanLinkProposer, PlanWorkItemSource, RecurringPlans};
use App\Services\Reselling\Register\{PeriodPlanner, ResaleInvoiceDraftService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\WithOrganization;
use Tests\Support\{FakePluginHttp, InteractsWithPlugins};
use Tests\TestCase;

/**
 * Serienvorlagen als Plan-Quelle (Feature 185, MVP-1409–1413): Lexoffice-
 * Vorlagen spiegeln, Vorlagenverweis je Rechnung, Zuordnung Abo ↔
 * Vorlagenposition, Prüfregeln Bestand ↔ Plan, Abo aus Vorlage anlegen.
 */
class RecurringPlanTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;
    use WithOrganization;

    private const CONTACT = '7c6b5a49-3827-4e16-9f05-e4d3c2b1a098';

    private const ARTICLE = '9d6e1f32-5b8a-4c7d-9e0f-1a2b3c4d5e6f';

    private LexofficeArticle $exchange;

    private Customer $schub;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-10-26');
        $this->enablePluginFor($this->organization, LexofficePlugin::ID, ['api_key' => 'lex-key', 'request_interval' => '0']);
        $this->exchange = LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => self::ARTICLE, 'name' => 'Exchange Online (Plan 1)', 'article_number' => 'EXO',
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '3.95', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
        $this->schub = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Schub- und Schleppreederei']);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => self::CONTACT, 'referenceable_type' => $this->schub->getMorphClass(), 'referenceable_id' => $this->schub->getKey(),
        ]);
    }

    private function subscription(string $externalId, string $label, int $quantity, string $startsOn, ?string $articleRef): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'external_id' => $externalId, 'provider_account' => 'CNL00010',
            'company_name' => 'Schub- und Schleppreederei U. Golka GmbH & Co.KG', 'label' => $label, 'customer_id' => $this->schub->id, 'article_ref' => $articleRef,
            'quantity' => $quantity, 'starts_on' => $startsOn, 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR', 'sale_unit_price' => '47.40',
        ]);
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    /** @return array<string, mixed> */
    private function templateEntry(string $id, string $status = 'ACTIVE', string $next = '2027-10-18'): array {
        return [
            'id' => $id, 'organizationId' => 'org', 'title' => 'Rechnung Exchange', 'createdDate' => '2025-10-01T10:00:00.000+02:00', 'updatedDate' => '2026-10-01T10:00:00.000+02:00',
            'address' => ['contactId' => self::CONTACT, 'name' => 'Schub- und Schleppreederei U. Golka GmbH & CO. KG'],
            'totalPrice' => ['currency' => 'EUR', 'totalNetAmount' => 237.0, 'totalGrossAmount' => 282.03],
            'paymentConditions' => ['paymentTermLabel' => '14 Tage', 'paymentTermDuration' => 14],
            'recurringTemplateSettings' => [
                'id' => 'settings-' . $id, 'startDate' => '2025-10-18T00:00:00.000+02:00', 'endDate' => null, 'finalize' => false, 'shippingType' => 'service',
                'executionInterval' => 'ANNUALLY', 'nextExecutionDate' => $next . 'T00:00:00.000+02:00', 'lastExecutionDate' => '2026-10-18T00:00:00.000+02:00',
                'lastExecutionFailed' => false, 'lastExecutionErrorMessage' => null, 'executionStatus' => $status,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @param  list<array<string, mixed>>  $lineItems
     * @param  array<string, mixed>  $extra
     */
    private function fakeApi(array $entries, array $lineItems, array $extra = []): void {
        $stubs = [];
        foreach ($entries as $entry) {
            $stubs['https://api.lexware.io/v1/recurring-templates/' . $entry['id']] = FakePluginHttp::response($entry + ['lineItems' => $lineItems, 'taxConditions' => ['taxType' => 'net'], 'introduction' => 'Microsoft Dienste']);
        }
        $stubs['https://api.lexware.io/v1/recurring-templates?*'] = FakePluginHttp::response([
            'content' => $entries, 'first' => true, 'last' => true, 'totalPages' => 1, 'totalElements' => count($entries), 'numberOfElements' => count($entries), 'size' => 250, 'number' => 0, 'sort' => [],
        ]);
        FakePluginHttp::fake($stubs + $extra);
    }

    /** @return list<array<string, mixed>> */
    private function exchangeLines(int $count): array {
        $lines = [['type' => 'text', 'name' => 'Lizenzen für das laufende Jahr']];
        for ($i = 0; $i < $count; $i++) {
            $lines[] = ['type' => 'service', 'id' => self::ARTICLE, 'name' => 'Exchange Online (Plan 1)', 'quantity' => 12, 'unitName' => 'Monat', 'unitPrice' => ['currency' => 'EUR', 'netAmount' => 3.95, 'taxRatePercentage' => 19.0]];
        }

        return $lines;
    }

    public function test_templates_are_mirrored_linked_to_subscriptions_and_audited(): void {
        $admin = $this->orgAdmin();
        $exchange = $this->subscription('CNLCON00111', 'Exchange Online Plan 1', 5, '2025-10-18', 'lex:' . $this->exchange->id);
        $teams = $this->subscription('CNLCON00108', 'Microsoft Teams Essentials', 1, '2025-11-09', null);
        $this->fakeApi([$this->templateEntry('tpl-1')], $this->exchangeLines(5));

        $result = (new LexofficeRecurringTemplateSync($this->organization->id))->sync($this->organization);

        $this->assertSame(1, $result['read']);
        $this->assertSame(1, $result['created']);
        $this->assertSame(5, $result['lines'], 'die Textposition zählt nicht');
        $template = LexofficeRecurringTemplate::query()->firstOrFail();
        $this->assertSame($this->schub->id, $template->customer_id, 'Kunde über die Kontaktverknüpfung');
        $this->assertSame('ACTIVE', $template->execution_status->value);
        $this->assertSame('2027-10-18', $template->next_execution_on?->toDateString());
        $this->assertSame(5, $template->lines()->count());
        $this->assertSame($this->exchange->id, (int) $template->lines()->first()?->lexoffice_article_id);

        // Plan-Quelle: eine Vorlage mit fünf Lizenzpositionen à einer Lizenz.
        $plans = app(RecurringPlans::class);
        $planTemplate = $plans->templateByKey($this->organization, 'lexoffice:tpl-1');
        $this->assertNotNull($planTemplate);
        $this->assertCount(5, $planTemplate->licenceLines());
        $this->assertSame(1.0, $planTemplate->licenceLines()[0]->licences(12));
        $this->assertSame(12, $planTemplate->intervalMonths());

        // Zuordnung: alle fünf Positionen an das Exchange-Abo (Artikelschlüssel), Teams bleibt ohne Vorlage.
        $proposal = app(PlanLinkProposer::class)->propose($this->organization);
        $this->assertSame(5, $proposal['links']);
        $this->assertSame(0, $proposal['unmatched']);
        $links = ResalePlanLink::query()->where('subscription_id', $exchange->id)->get();
        $this->assertCount(5, $links);
        $this->assertSame(5, (int) $links->sum('quantity'));
        $this->assertSame(LinkOrigin::Proposed, $links->first()?->origin);
        $this->assertSame([$exchange->id], $plans->plannedSubscriptionIds($this->organization));

        // Prüfregeln: Teams ohne Vorlage; Exchange vollständig (keine Mengenabweichung).
        $findings = app(PlanAudit::class)->findings($this->organization);
        $kinds = array_count_values(array_column($findings, 'kind'));
        $this->assertSame(1, $kinds[PlanAudit::SUBSCRIPTION_WITHOUT_PLAN] ?? 0);
        $this->assertArrayNotHasKey(PlanAudit::QUANTITY_MISMATCH, $kinds);
        $this->assertArrayNotHasKey(PlanAudit::PLAN_WITHOUT_SUBSCRIPTION, $kinds);
        $items = iterator_to_array(app(PlanWorkItemSource::class)->items($admin, $this->organization), false);
        $this->assertCount(1, $items);
        $this->assertSame(1, $items[0]->count);

        // Rechnungsvorschlag lässt die geplanten Perioden aus — Teams bleibt drin.
        $open = app(ResaleInvoiceDraftService::class)->openPeriodsFor($this->schub);
        $this->assertSame([$teams->id], $open->pluck('subscription_id')->unique()->values()->all());

        // Lizenzkonto zeigt die Vorlage; bestätigen und lösen.
        $page = $this->actingAs($admin)->get(route('finance.resale.accounts.show', 'qualityhosting:CNL00010'))->assertOk();
        $page->assertSee(__('resale.plan.title'))->assertSee('Rechnung Exchange')->assertSee(__('resale.plan.finding.subscription_without_plan_short'));
        $first = $links->first();
        $this->actingAs($admin)->post(route('finance.resale.accounts.plan.store', 'qualityhosting:CNL00010'), [
            'subscription_id' => $exchange->sqid, 'plan_key' => 'lexoffice:tpl-1', 'line_position' => $first->line_position, 'quantity' => 1,
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame(LinkOrigin::Confirmed, $first->fresh()?->origin);
        // Ein zweiter Vorschlagslauf lässt die bestätigte Zuordnung stehen und ersetzt nur Vorschläge.
        app(PlanLinkProposer::class)->propose($this->organization);
        $this->assertSame(1, ResalePlanLink::query()->where('origin', LinkOrigin::Confirmed->value)->count());
        $this->assertSame(5, ResalePlanLink::query()->where('subscription_id', $exchange->id)->count());
        $this->actingAs($admin)->delete(route('finance.resale.accounts.plan.destroy', $first->sqid))->assertRedirect();
        $this->assertNull($first->fresh());

        // Zweiter Sync ohne Änderung: kein Detailabruf nötig, nichts doppelt; verschwundene Vorlage → beendet.
        $this->assertSame(1, (new LexofficeRecurringTemplateSync($this->organization->id))->sync($this->organization)['updated']);
        $this->fakeApi([], []);
        $gone = (new LexofficeRecurringTemplateSync($this->organization->id))->sync($this->organization);
        $this->assertSame(1, $gone['ended']);
        $this->assertSame('ENDED', $template->fresh()?->execution_status->value);
        $kinds = array_count_values(array_column(app(PlanAudit::class)->findings($this->organization), 'kind'));
        $this->assertSame(1, $kinds[PlanAudit::PLAN_PAUSED] ?? 0, 'beendete Vorlage bei laufendem Abo');
    }

    public function test_mismatched_quantity_and_template_without_subscription_are_reported(): void {
        $this->subscription('CNLCON00111', 'Exchange Online Plan 1', 9, '2025-10-18', 'lex:' . $this->exchange->id);
        $this->fakeApi([$this->templateEntry('tpl-1')], $this->exchangeLines(5));
        (new LexofficeRecurringTemplateSync($this->organization->id))->sync($this->organization);
        app(PlanLinkProposer::class)->propose($this->organization);

        $kinds = array_count_values(array_column(app(PlanAudit::class)->findings($this->organization), 'kind'));
        $this->assertSame(1, $kinds[PlanAudit::QUANTITY_MISMATCH] ?? 0, 'Vorlage berechnet 5, Abo hat 9');

        // Vorlage für einen Kontakt ohne Kunde und ohne Abo → Position ohne Abo.
        $this->fakeApi([$this->templateEntry('tpl-1'), ['title' => 'Rechnung Andere', 'address' => ['contactId' => 'c-other', 'name' => 'Andere GmbH']] + $this->templateEntry('tpl-2')], $this->exchangeLines(1));
        (new LexofficeRecurringTemplateSync($this->organization->id))->sync($this->organization);
        app(PlanLinkProposer::class)->propose($this->organization);
        $kinds = array_count_values(array_column(app(PlanAudit::class)->findings($this->organization), 'kind'));
        $this->assertGreaterThanOrEqual(1, $kinds[PlanAudit::PLAN_WITHOUT_SUBSCRIPTION] ?? 0);
    }

    public function test_subscription_can_be_created_from_a_template_line_and_keeps_the_link(): void {
        $admin = $this->orgAdmin();
        $this->fakeApi([$this->templateEntry('tpl-1')], $this->exchangeLines(2));
        (new LexofficeRecurringTemplateSync($this->organization->id))->sync($this->organization);

        $planTemplate = app(RecurringPlans::class)->templateByKey($this->organization, 'lexoffice:tpl-1');
        $this->assertNotNull($planTemplate);
        $position = $planTemplate->licenceLines()[0]->position;
        // Beginn = nächste Ausführung minus Intervall (18.10.2027 → 18.10.2026), Preis je Lizenz und Jahr = 12 × 3,95.
        $dialog = $this->actingAs($admin)->get(route('finance.resale.create', ['plan' => 'lexoffice:tpl-1', 'position' => $position]))->assertOk();
        $dialog->assertSee('Exchange Online (Plan 1)')->assertSee('name="plan_key"', false)->assertSee('value="2026-10-18"', false)->assertSee('value="47.40"', false);

        $this->actingAs($admin)->post(route('finance.resale.store'), [
            'kind' => 'license', 'provider' => 'manual', 'label' => 'Exchange Online (Plan 1)', 'holder' => 'customer', 'customer_id' => $this->schub->sqid,
            'quantity' => 1, 'starts_on' => '2025-10-18', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'sale_unit_price' => '47.40',
            'plan_key' => 'lexoffice:tpl-1', 'plan_position' => $position,
        ])->assertRedirect();
        $subscription = ResaleSubscription::query()->where('provider', 'manual')->firstOrFail();
        $link = ResalePlanLink::query()->where('subscription_id', $subscription->id)->firstOrFail();
        $this->assertSame('lexoffice:tpl-1', $link->plan_key);
        $this->assertSame($position, $link->line_position);
        $this->assertSame(LinkOrigin::Manual, $link->origin);
        $this->actingAs($admin)->get(route('finance.resale.show', $subscription->sqid))->assertOk()->assertSee(__('resale.plan.planned_by', ['title' => 'Rechnung Exchange', 'date' => '18.10.2027']));
    }

    public function test_voucher_line_sync_stores_the_recurring_template_reference(): void {
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-1134', 'contact_external_id' => self::CONTACT, 'voucher_type' => 'invoice',
            'voucher_status' => 'open', 'voucher_number' => 'RE/2026/1134', 'voucher_date' => '2026-10-18', 'total_amount' => 282.03, 'currency' => 'EUR', 'archived' => false,
        ]);
        FakePluginHttp::fake([
            'https://api.lexware.io/v1/invoices/v-1134' => FakePluginHttp::response([
                'id' => 'v-1134', 'voucherNumber' => 'RE/2026/1134', 'recurringTemplateId' => 'tpl-1', 'totalPrice' => ['currency' => 'EUR', 'totalNetAmount' => 237.0],
                'taxConditions' => ['taxType' => 'net'], 'lineItems' => $this->exchangeLines(5), 'address' => ['contactId' => self::CONTACT, 'name' => 'Schub'],
            ]),
        ]);

        (new LexofficeVoucherLineSync($this->organization->id))->syncVoucher($voucher);

        $this->assertSame('tpl-1', $voucher->fresh()?->recurring_template_id);
        $line = app(\App\Services\Reselling\Mirror\InvoiceMirror::class)->linesFor($this->organization, [$this->schub->id])->first();
        $this->assertSame('lexoffice:tpl-1', $line?->planKey);
    }

    public function test_scheduled_command_mirrors_templates_and_proposes_links(): void {
        $this->subscription('CNLCON00111', 'Exchange Online Plan 1', 5, '2025-10-18', 'lex:' . $this->exchange->id);
        $this->fakeApi([$this->templateEntry('tpl-1')], $this->exchangeLines(5));

        $this->assertSame(0, Artisan::call('lexoffice:sync-recurring-templates'));

        $this->assertStringContainsString('1 Vorlagen gelesen', Artisan::output());
        $this->assertSame(5, ResalePlanLink::query()->count());
        foreach (['de', 'en', 'es', 'fr', 'it'] as $locale) {
            $this->assertTrue(app('translator')->has('scheduler.job.lexoffice.sync_recurring_templates', $locale, false), "Label fehlt: $locale");
        }
    }
}
