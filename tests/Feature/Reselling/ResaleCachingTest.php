<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleCachingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\{LinkOrigin, PeriodStatus};
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Reselling\{ResalePeriodLink, ResalePlanLink, ResaleSubscription};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\{LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine};
use App\Services\Reselling\Mirror\InvoiceMirror;
use App\Services\Reselling\Plan\PlanAudit;
use App\Services\Reselling\Register\{LicenseAccounts, LinkAudit, PeriodLinker, PeriodPlanner, RecipientReconciler, ResaleCacheStamp, ResaleOverview, ResaleRequestMemo};
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\Support\InteractsWithPlugins;
use Tests\TestCase;

/**
 * Leistung der Reselling-Seiten (Leistungsanalyse 2026-11-02, perfc-01 ff.):
 * Spiegel-Ladungen je Arbeitsmappe, Request-Memo und 60-s-Caches mit
 * Abfragezähler und Invalidierung über den Org-Stempel.
 */
class ResaleCachingTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;
    use WithOrganization;

    private LexofficeArticle $exchange;

    private LexofficeArticle $tld;

    /** @var list<string> */
    private array $queries = [];

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-07');
        $this->exchange = $this->article('art-exo', 'Exchange Online (Plan 1)', '3.95');
        $this->tld = $this->article('art-tld', 'Top-Level-Domain', '8.55');
        DB::listen(function (QueryExecuted $query): void {
            $this->queries[] = $query->sql;
        });
    }

    private function article(string $externalId, string $name, string $price): LexofficeArticle {
        return LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => $externalId, 'name' => $name, 'article_number' => strtoupper($externalId),
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => $price, 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
    }

    private function customerWithContact(string $name, string $contactId): Customer {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => $name]);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => $contactId, 'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->getKey(),
        ]);

        return $customer;
    }

    /** @param array<string, mixed> $attributes */
    private function subscription(array $attributes): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'quantity' => 1,
            'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR', 'sale_unit_price' => '47.40',
        ], $attributes));
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    /** @param list<array{article: LexofficeArticle, quantity: float, unit?: string, net: string, description?: string}> $lines */
    private function voucher(string $contactId, string $number, string $date, array $lines, string $status = 'paid', ?string $serviceFrom = null, ?string $serviceTo = null): LexofficeVoucher {
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-' . $number, 'contact_external_id' => $contactId,
            'voucher_type' => 'invoice', 'voucher_status' => $status, 'voucher_number' => $number, 'voucher_date' => $date, 'total_amount' => 100, 'currency' => 'EUR',
            'voucher_text' => 'Microsoftdienste Hanna Quist', 'service_starts_on' => $serviceFrom, 'service_ends_on' => $serviceTo,
            'archived' => false, 'recipient_name' => 'Unbekannte GmbH', 'lines_synced_at' => now(),
        ]);
        foreach ($lines as $position => $line) {
            LexofficeVoucherLine::create([
                'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => $position + 1, 'type' => 'service',
                'external_article_id' => $line['article']->external_id, 'lexoffice_article_id' => $line['article']->id,
                'name' => $line['article']->name, 'description' => $line['description'] ?? null, 'quantity' => $line['quantity'], 'unit_name' => $line['unit'] ?? 'Monat',
                'unit_net' => $line['net'], 'total_net' => (string) round((float) $line['net'] * $line['quantity'], 2), 'tax_rate' => 19, 'currency' => 'EUR',
            ]);
        }

        return $voucher;
    }

    /** Positionsabfragen des Lexoffice-Spiegels seit dem letzten Zurücksetzen (alle). */
    private function mirrorLoads(): int {
        return $this->queriesMatching('/^select \* from [`"]lexoffice_voucher_lines[`"]/i');
    }

    /** Davon die Volllasten über alle Empfänger der Organisation (ohne Kontaktfilter). */
    private function organizationWideMirrorLoads(): int {
        return count(array_filter($this->queries, static fn(string $sql): bool => preg_match('/^select \* from [`"]lexoffice_voucher_lines[`"]/i', $sql) === 1 && ! str_contains($sql, 'contact_external_id')));
    }

    private function queriesMatching(string $pattern): int {
        return count(array_filter($this->queries, static fn(string $sql): bool => preg_match($pattern, $sql) === 1));
    }

    private function resetQueries(): void {
        $this->queries = [];
    }

    /** Frischer Request: das scoped Memo ist weg, der Cache bleibt. */
    private function newRequest(): void {
        app(ResaleRequestMemo::class)->forget();
    }

    /**
     * Mappe mit Lizenz- und Domain-Abo, Rechnungen an den Empfänger und an eine
     * Schwesterfirma, ein Storno: alles, was die Mappe aus dem Spiegel braucht.
     *
     * @return array{customer: Customer, sister: Customer, licence: ResaleSubscription}
     */
    private function workspace(): array {
        $customer = $this->customerWithContact('Nordlicht Systems GmbH', 'c-nordlicht');
        $sister = $this->customerWithContact('Nordlicht Service GmbH', 'c-nordlicht-service');
        $licence = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'customer_id' => $customer->id, 'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => '2024-04-10', 'quantity' => 3]);
        $this->subscription(['kind' => 'domain', 'provider' => 'domainreselling', 'external_id' => 'nordlicht-beispiel.de', 'label' => 'nordlicht-beispiel.de', 'customer_id' => $customer->id, 'starts_on' => '2023-01-25', 'sale_unit_price' => '8.55']);
        $this->voucher('c-nordlicht', 'RE/2024/9001', '2024-04-14', [['article' => $this->exchange, 'quantity' => 12, 'net' => '3.95']]);
        $this->voucher('c-nordlicht', 'RE/2023/9002', '2023-02-01', [['article' => $this->tld, 'description' => '.de-Domain inkl. Verwaltung (nordlicht-beispiel.de)', 'quantity' => 1, 'unit' => 'Jahr', 'net' => '8.55']], 'paid', '2023-01-25', '2024-01-24');
        $this->voucher('c-nordlicht-service', 'RE/2025/9003', '2025-04-14', [['article' => $this->exchange, 'quantity' => 12, 'net' => '3.95']]);
        $this->voucher('c-nordlicht-service', 'RE/2025/9004', '2025-04-20', [['article' => $this->exchange, 'quantity' => 12, 'net' => '3.95']], 'voided');

        return ['customer' => $customer, 'sister' => $sister, 'licence' => $licence];
    }

    public function test_workspace_loads_the_mirror_at_most_once(): void {
        $admin = $this->orgAdmin();
        ['customer' => $customer] = $this->workspace();
        $this->newRequest();
        $this->resetQueries();

        $this->actingAs($admin)->get(route('finance.resale.reconcile.show', $customer))->assertOk()
            ->assertSee('RE/2024/9001')->assertSee('RE/2025/9003')->assertSee('RE/2023/9002');

        fwrite(STDERR, sprintf("\n[perfc-01] Spiegel-Ladungen je Mappe: %d, davon org-weit: %d\n", $this->mirrorLoads(), $this->organizationWideMirrorLoads()));
        // Vorher (2026-11-02): 6 Ladungen, 4 davon über die ganze Organisation. Jetzt: eine Volllast aller
        // Positionen (Lizenz- und Domainzeilen daraus), einmal die Stornos; dazu nur die Gutschriften des Empfängers.
        $this->assertSame(2, $this->organizationWideMirrorLoads(), 'alle Positionen einmal, Stornos einmal');
        $this->assertLessThanOrEqual(3, $this->mirrorLoads());
        $this->assertSame(1, $this->queriesMatching('/^select [`"]referenceable_id[`"], [`"]external_id[`"] from [`"]external_references[`"]/i'), 'Kontaktkarte der Spiegelquelle einmal je Request');
        $this->assertSame(1, $this->queriesMatching('/^select [`"]id[`"], [`"]name[`"] from [`"]lexoffice_articles[`"]/i'), 'Leseseite (Abo-Artikel klassifizieren) einmal je Request');
    }

    public function test_reconciler_memoizes_mirror_lines_and_caches_the_overview_within_a_request(): void {
        ['customer' => $customer] = $this->workspace();
        $this->newRequest();
        $this->resetQueries();

        $first = (new RecipientReconciler)->forCustomer($this->organization, $customer);
        $this->assertSame(2, $this->organizationWideMirrorLoads());
        $this->resetQueries();

        // Zweiter Aufruf im selben Request (andere Instanz, gleiches Memo): keine Volllast mehr — nur die
        // Gutschriften des Empfängers (klein, bewusst nicht memoisiert).
        $second = (new RecipientReconciler)->forCustomer($this->organization, $customer);
        $this->assertSame(0, $this->organizationWideMirrorLoads());
        $this->assertLessThanOrEqual(1, $this->mirrorLoads());
        $this->assertSame(array_column($first['lines'], 'product'), array_column($second['lines'], 'product'));
        $this->assertSame($first['open'], $second['open']);

        // Übersicht aus demselben Memo, danach aus dem Cache: nur noch die Kundennamen.
        $this->resetQueries();
        $rows = (new RecipientReconciler)->overview($this->organization);
        $this->assertSame(0, $this->mirrorLoads());
        $this->assertSame('Nordlicht Systems GmbH', $rows[0]['name']);
        $this->newRequest();
        $this->resetQueries();
        $cached = (new RecipientReconciler)->overview($this->organization);
        $this->assertSame(0, $this->mirrorLoads());
        $this->assertSame(0, $this->queriesMatching('/from [`"]resale_subscriptions[`"]/i'), 'Übersicht aus dem Cache');
        $this->assertSame(1, $this->queriesMatching('/from [`"]customers[`"]/i'), 'nur die Kundenmodelle werden nachgeladen');
        $this->assertSame($rows[0]['open'], $cached[0]['open']);
        $this->assertSame($rows[0]['open_amount']?->getAmount(), $cached[0]['open_amount']?->getAmount());
        $this->assertInstanceOf(Customer::class, $cached[0]['customer']);
    }

    public function test_overview_cache_is_invalidated_by_a_link_an_import_and_a_mirror_change(): void {
        ['customer' => $customer, 'licence' => $licence] = $this->workspace();
        $before = (new RecipientReconciler)->overview($this->organization);
        $this->assertSame(7, $before[0]['open'], 'drei fällige Perioden des 3er-Vertrags, vier der Domain');
        $stamp = ResaleCacheStamp::current((int) $this->organization->id);

        // Bezug von Hand (Modell-Event auf dem Bezug und der Periode): neuer Stempel, Übersicht neu gerechnet.
        $line = app(InvoiceMirror::class)->linesFor($this->organization, [$customer->id])->firstOrFail();
        $period = $licence->periods()->orderBy('starts_on')->firstOrFail();
        (new PeriodLinker)->attach($period, $line, 12.0, null, null);
        $this->assertGreaterThan($stamp, ResaleCacheStamp::current((int) $this->organization->id));
        $this->newRequest();
        $after = (new RecipientReconciler)->overview($this->organization);
        $this->assertSame(PeriodStatus::Partial, $period->fresh()?->status);
        $this->assertSame(1, $after[0]['partial']);
        $this->assertSame(6, $after[0]['open']);

        // Neuer Spiegelbeleg (Sync) stempelt über den Plugin-Observer und vergisst das Memo.
        $stamp = ResaleCacheStamp::current((int) $this->organization->id);
        $this->voucher('c-nordlicht', 'RE/2025/9005', '2025-04-14', [['article' => $this->exchange, 'quantity' => 12, 'net' => '3.95']]);
        $this->assertGreaterThan($stamp, ResaleCacheStamp::current((int) $this->organization->id));
        $this->assertFalse(app(ResaleRequestMemo::class)->has('mirror-lines:all:' . $this->organization->id));
        $this->resetQueries();
        $fresh = (new RecipientReconciler)->overview($this->organization);
        $this->assertGreaterThan(0, $this->organizationWideMirrorLoads(), 'nach dem Sync lädt der Spiegel neu');
        $this->assertSame($after[0]['free'] + 12.0, $fresh[0]['free'], 'die neue Position ist frei');

        // Vorschlagslauf ohne Modell-Events stempelt selbst.
        $stamp = ResaleCacheStamp::current((int) $this->organization->id);
        (new \App\Services\Reselling\Register\LinkProposer)->propose($this->organization);
        $this->assertGreaterThan($stamp, ResaleCacheStamp::current((int) $this->organization->id));
    }

    public function test_plan_finding_counts_are_cached_until_a_plan_link_changes(): void {
        $this->enablePluginFor($this->organization, LexofficePlugin::ID, ['api_key' => 'lex-key', 'request_interval' => '0']);
        ['licence' => $licence] = $this->workspace();
        $this->newRequest();
        $this->resetQueries();

        $counts = app(PlanAudit::class)->countByKind($this->organization);
        $this->assertSame([PlanAudit::SUBSCRIPTION_WITHOUT_PLAN => 2], $counts, 'Lizenz- und Domain-Abo ohne Vorlage');
        $this->assertGreaterThan(0, $this->queriesMatching('/from [`"]resale_plan_links[`"]/i'));

        $this->newRequest();
        $this->resetQueries();
        $this->assertSame($counts, app(PlanAudit::class)->countByKind($this->organization));
        $this->assertSame(0, $this->queriesMatching('/from [`"]resale_plan_links[`"]/i'), 'zweiter Aufruf aus dem Cache');
        $this->assertSame(0, $this->queriesMatching('/from [`"]lexoffice_recurring_templates[`"]/i'));

        // Zuordnung angelegt (Modell-Event): die Zahlen werden neu gerechnet.
        ResalePlanLink::query()->create(['organization_id' => $this->organization->id, 'subscription_id' => $licence->id, 'plan_key' => 'lexoffice:tpl-1', 'line_position' => 0, 'quantity' => 3, 'origin' => LinkOrigin::Manual]);
        $this->newRequest();
        $this->resetQueries();
        $this->assertSame([PlanAudit::SUBSCRIPTION_WITHOUT_PLAN => 1, PlanAudit::PLAN_MISSING => 1], app(PlanAudit::class)->countByKind($this->organization));
        $this->assertGreaterThan(0, $this->queriesMatching('/from [`"]resale_plan_links[`"]/i'));
    }

    public function test_overview_counters_are_cached_and_the_inbox_texts_come_from_the_cache(): void {
        $admin = $this->orgAdmin();
        ['customer' => $customer] = $this->workspace();
        $this->subscription(['company_name' => 'Hanna Quist', 'label' => 'Postfach Quist', 'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => '2024-04-10']);
        $this->newRequest();

        $overview = app(ResaleOverview::class)->build($this->organization);
        $this->assertSame(1, $overview['summary']['unassigned']);
        $this->assertSame(10, $overview['summary']['open'], 'sieben Perioden des Empfängers, drei der Firma ohne Halter');
        $this->newRequest();
        $this->resetQueries();
        $again = app(ResaleOverview::class)->build($this->organization);
        $this->assertSame($overview['summary'], $again['summary']);
        $this->assertSame(0, $this->queriesMatching('/from [`"]resale_periods[`"]/i'), 'Zähler aus dem Cache — nur Anbieterstand und letzter Lauf bleiben live');
        $this->assertSame(0, $this->mirrorLoads());

        // Posteingang: Belegtexte je Organisation aus dem Cache, der zweite Aufruf fragt den Spiegel nicht.
        $this->newRequest();
        $mentioned = (new RecipientReconciler)->mentioningRecipients($this->organization, ['Hanna Quist'], \Carbon\CarbonImmutable::parse('2024-01-10'));
        $this->assertSame($customer->id, $mentioned['Hanna Quist'][0]['customer']->id);
        $this->newRequest();
        $this->resetQueries();
        $this->assertSame(2, (new RecipientReconciler)->mentioningRecipients($this->organization, ['Hanna Quist'], \Carbon\CarbonImmutable::parse('2024-01-10'))['Hanna Quist'][0]['vouchers']);
        $this->assertSame(0, $this->mirrorLoads());
        $this->actingAs($admin)->get(route('finance.resale.inbox'))->assertOk()->assertSee('Postfach Quist');
    }

    public function test_license_accounts_are_memoized_behind_a_stock_signature(): void {
        ['licence' => $licence] = $this->workspace();
        $this->newRequest();
        $this->resetQueries();

        $accounts = app(LicenseAccounts::class);
        $first = $accounts->subscriptions($this->organization);
        $this->assertCount(2, $first);
        $accounts->groups($this->organization);
        $accounts->keyFor($this->organization, $licence);
        $this->assertSame(1, $this->queriesMatching('/^select \* from [`"]resale_subscriptions[`"]/i'), 'Abos einmal je Request — Konten und Kontoschlüssel teilen sie');

        // Neuer Bestand im selben Prozess (Import, Halterwechsel): die Signatur kippt, es wird neu geladen.
        $this->subscription(['label' => 'Microsoft 365 Business Standard', 'customer_id' => $licence->customer_id, 'starts_on' => '2025-10-01']);
        $this->resetQueries();
        $this->assertCount(3, $accounts->subscriptions($this->organization));
        $this->assertSame(1, $this->queriesMatching('/^select \* from [`"]resale_subscriptions[`"]/i'));
    }

    /** MVP-1796: Der umgerechnete Spiegel liegt über Requests hinweg im Cache und folgt jedem Bestandswechsel. */
    public function test_mirror_lines_come_from_the_cache_until_the_mirror_changes(): void {
        $admin = $this->orgAdmin();
        $this->actingAs($admin);
        $data = $this->workspace();
        $mirror = app(InvoiceMirror::class);
        $load = function () use ($mirror): array {
            $this->newRequest();
            $this->resetQueries();
            $lines = [];
            foreach ($mirror->linesFor($this->organization, null, licenceOnly: false) as $line) {
                $row = get_object_vars($line);
                foreach ($row as $field => $value) {
                    $row[$field] = match (true) {
                        $value instanceof \DateTimeInterface => $value::class . '|' . $value->format('Y-m-d H:i:s.u e'),
                        $value instanceof \CommonToolkit\ValueObjects\Money => $value->getAmount() . '|' . $value->getCurrency()->value . '|' . $value->getScale(),
                        $value instanceof \BackedEnum => $value->value,
                        default => $value,
                    };
                }
                $lines[$line->identity()] = $row;
            }
            ksort($lines);

            return $lines;
        };

        // Ein eben geänderter Spiegel geht noch nicht in den Cache (die Signatur misst in Sekunden).
        $load();
        $load();
        $this->assertSame(1, $this->organizationWideMirrorLoads());
        $this->travel(5)->seconds();
        $fresh = $load();
        $this->assertSame(1, $this->organizationWideMirrorLoads());
        $this->assertCount(3, $fresh);

        // Zweiter Request: dieselben Zeilen, ohne die Positionen neu zu lesen.
        $cached = $load();
        $this->assertSame(0, $this->mirrorLoads());
        $this->assertSame($fresh, $cached);

        // Der Kundenname steht nicht im Cache.
        $data['customer']->update(['name' => 'Nordlicht Systems AG']);
        $renamed = $load();
        $this->assertSame(0, $this->mirrorLoads());
        $this->assertContains('Nordlicht Systems AG', array_column($renamed, 'recipientName'));
        $this->assertSame(array_column($fresh, 'previewUrl'), array_column($renamed, 'previewUrl'));

        // Änderung über das Modell und Änderung per Abfrage (ohne Ereignis) lesen beide neu.
        LexofficeVoucherLine::query()->where('organization_id', $this->organization->id)->orderBy('id')->firstOrFail()->update(['name' => 'Exchange Online (Plan 2)']);
        $this->assertContains('Exchange Online (Plan 2)', array_column($load(), 'name'));
        $this->assertSame(1, $this->organizationWideMirrorLoads());
        $this->travel(5)->seconds();
        $load();
        $this->assertContains('Exchange Online (Plan 2)', array_column($load(), 'name'));
        $this->assertSame(0, $this->mirrorLoads());
        LexofficeVoucher::query()->withoutGlobalScopes()->where('voucher_number', 'RE/2024/9001')->update(['voucher_number' => 'RE/2024/9001-K']);
        $this->assertContains('RE/2024/9001-K', array_column($load(), 'voucherNumber'));
        $this->assertSame(1, $this->organizationWideMirrorLoads());
        $this->travel(5)->seconds();
        $load();
        DB::table('lexoffice_voucher_lines')->where('name', 'Exchange Online (Plan 2)')->delete();
        $this->assertCount(2, $load());

        // Ein Bezug ändert das Register, nicht den Spiegel: der Cache bleibt.
        $this->travel(5)->seconds();
        $load();
        ResaleCacheStamp::bump((int) $this->organization->id);
        $load();
        $this->assertSame(0, $this->mirrorLoads());

        // Ein Stempel in der Zukunft (per SQL in Ortszeit geschrieben) sperrt den Cache nicht.
        DB::table('lexoffice_voucher_lines')->where('organization_id', $this->organization->id)->limit(1)->update(['updated_at' => now()->addHour()]);
        $load();
        $this->assertSame(1, $this->organizationWideMirrorLoads());
        $load();
        $this->assertSame(0, $this->mirrorLoads());
    }

    /** MVP-1797: Nach einer Änderung rechnet nur der betroffene Empfänger neu — mit demselben Ergebnis wie eine volle Rechnung. */
    public function test_overview_recomputes_only_the_changed_recipient_and_matches_a_full_run(): void {
        $this->actingAs($this->orgAdmin());
        $data = $this->workspace();
        $third = $this->customerWithContact('Hafenkontor Süd GmbH', 'c-hafenkontor');
        $this->subscription(['label' => 'Exchange Online (Plan 1)', 'customer_id' => $third->id, 'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => '2025-02-01', 'quantity' => 2]);
        $this->voucher('c-hafenkontor', 'RE/2025/9010', '2025-02-03', [['article' => $this->exchange, 'quantity' => 24, 'net' => '3.95']]);
        $line = LexofficeVoucherLine::query()->whereHas('voucher', static fn($q) => $q->where('voucher_number', 'RE/2024/9001'))->firstOrFail();
        $period = $data['licence']->periods()->orderBy('starts_on')->firstOrFail();
        app(PeriodLinker::class)->attach($period, $line, 12, null, null);

        $stateKey = 'resale:reconcile-recipients:1:' . $this->organization->id . ':2026-09-07';
        $overview = function (): array {
            $this->newRequest();
            ResaleCacheStamp::bump((int) $this->organization->id);
            $this->resetQueries();

            return array_map(static fn(array $row): array => array_merge($row, ['customer' => $row['customer']?->id, 'open_amount' => $row['open_amount']?->getAmount()]), (new RecipientReconciler)->overview($this->organization));
        };
        $periodLoads = fn(): int => $this->queriesMatching('/^select \* from [`"]resale_periods[`"] where [`"]resale_periods[`"]\.[`"]subscription_id[`"] in/i');
        $recomputed = function (array $before) use ($stateKey): array {
            $changed = [];
            foreach ((array) \Illuminate\Support\Facades\Cache::get($stateKey) as $recipient => $entry) {
                if (($before[$recipient]['fingerprint'] ?? null) !== $entry['fingerprint']) {
                    $changed[] = (int) $recipient;
                }
            }
            sort($changed);

            return $changed;
        };
        $full = function () use ($overview, $stateKey): array {
            \Illuminate\Support\Facades\Cache::forget($stateKey);

            return $overview();
        };

        $first = $overview();
        $this->assertCount(3, $first);

        // Nichts geändert: kein Empfänger lädt seine Perioden neu.
        $this->assertSame($first, $overview());
        $this->assertSame(0, $periodLoads());

        // Bezug per Abfrage geändert (ohne Ereignis): nur der Empfänger der Periode rechnet neu.
        $states = (array) \Illuminate\Support\Facades\Cache::get($stateKey);
        DB::table('resale_period_links')->where('period_id', $period->id)->update(['months' => 6]);
        $partial = $overview();
        $this->assertSame([$data['customer']->id], $recomputed($states));
        $this->assertNotSame($first, $partial);
        $this->assertSame($full(), $partial);

        // Position eines anderen Empfängers geändert: nur dieser rechnet neu.
        $states = (array) \Illuminate\Support\Facades\Cache::get($stateKey);
        LexofficeVoucherLine::query()->whereHas('voucher', static fn($q) => $q->where('voucher_number', 'RE/2025/9010'))->firstOrFail()->update(['quantity' => 12, 'total_net' => '47.40']);
        $partial = $overview();
        $this->assertSame([$third->id], $recomputed($states));
        $this->assertSame($full(), $partial);

        // Abo wechselt den Empfänger: beide Seiten rechnen neu, die dritte nicht.
        $states = (array) \Illuminate\Support\Facades\Cache::get($stateKey);
        $data['licence']->update(['customer_id' => $data['sister']->id]);
        $partial = $overview();
        $this->assertSame([$data['customer']->id, $data['sister']->id], $recomputed($states));
        $this->assertSame($full(), $partial);
    }

    public function test_link_findings_are_rechecked_only_for_changed_subscriptions_and_match_a_full_check(): void {
        $this->actingAs($this->orgAdmin());
        $data = $this->workspace();
        $third = $this->customerWithContact('Hafenkontor Süd GmbH', 'c-hafenkontor');
        $other = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'customer_id' => $third->id, 'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => '2025-02-01', 'quantity' => 2]);
        $this->voucher('c-hafenkontor', 'RE/2025/9010', '2025-02-03', [['article' => $this->exchange, 'quantity' => 24, 'net' => '3.95']]);
        $lineOf = static fn(string $number): LexofficeVoucherLine => LexofficeVoucherLine::query()->whereHas('voucher', static fn($q) => $q->where('voucher_number', $number))->firstOrFail();
        $link = fn(ResaleSubscription $subscription, int $periodIndex, LexofficeVoucherLine $line, string $number, string $date, float $months): ResalePeriodLink => ResalePeriodLink::query()->create([
            'organization_id' => $this->organization->id, 'period_id' => $subscription->periods()->orderBy('starts_on')->get()[$periodIndex]->id, 'subscription_id' => $subscription->id,
            'linkable_type' => $line->getMorphClass(), 'linkable_id' => $line->id, 'voucher_number' => $number, 'voucher_date' => $date,
            'quantity' => 1, 'months' => $months, 'currency' => 'EUR', 'origin' => 'manual', 'confirmed_at' => now(),
        ]);
        // Überbucht und zeitlich unpassend: die Zwölf-Monats-Position von 2024 trägt zwei Lizenzjahre.
        $link($data['licence'], 0, $lineOf('RE/2024/9001'), 'RE/2024/9001', '2024-04-14', 12);
        $second = $link($data['licence'], 2, $lineOf('RE/2024/9001'), 'RE/2024/9001', '2024-04-14', 12);
        $link($other, 0, $lineOf('RE/2025/9010'), 'RE/2025/9010', '2025-02-03', 24);

        $stateKey = 'resale:link-findings:1:' . $this->organization->id;
        $findings = function (): array {
            $this->newRequest();
            ResaleCacheStamp::bump((int) $this->organization->id);
            $this->resetQueries();
            $overview = app(ResaleOverview::class);

            return (new \ReflectionMethod($overview, 'linkFindings'))->invoke($overview, $this->organization);
        };
        $periodLoads = fn(): int => $this->queriesMatching('/^select \* from [`"]resale_periods[`"] where [`"]resale_periods[`"]\.[`"]subscription_id[`"] in/i');
        $rechecked = function (array $before) use ($stateKey): array {
            $changed = [];
            foreach ((array) \Illuminate\Support\Facades\Cache::get($stateKey) as $subscription => $entry) {
                if (($before[$subscription]['fingerprint'] ?? null) !== $entry['fingerprint']) {
                    $changed[] = (int) $subscription;
                }
            }
            sort($changed);

            return $changed;
        };
        $full = function () use ($findings, $stateKey): array {
            \Illuminate\Support\Facades\Cache::forget($stateKey);

            return $findings();
        };

        $first = $findings();
        $this->assertSame(1, $first['counts'][LinkAudit::OVERBOOKED] ?? 0);
        $this->assertGreaterThan(0, $first['counts'][LinkAudit::MISMATCH] ?? 0);
        $this->assertSame($data['customer']->id, $first['customer_id']);

        // Nichts geändert: kein Abo lädt seine Perioden neu.
        $this->assertSame($first, $findings());
        $this->assertSame(0, $periodLoads());

        // Bezug per Abfrage gelöscht (ohne Ereignis): nur sein Abo wird neu geprüft, die Überbuchung entfällt.
        $states = (array) \Illuminate\Support\Facades\Cache::get($stateKey);
        DB::table('resale_period_links')->where('id', $second->id)->delete();
        $partial = $findings();
        $this->assertSame([$data['licence']->id], $rechecked($states));
        $this->assertSame(0, $partial['counts'][LinkAudit::OVERBOOKED] ?? 0);
        $this->assertSame($full(), $partial);

        // Position eines anderen Empfängers schrumpft: nur dessen Abo wird neu geprüft und ist jetzt überbucht.
        $states = (array) \Illuminate\Support\Facades\Cache::get($stateKey);
        $lineOf('RE/2025/9010')->update(['quantity' => 12, 'total_net' => '47.40']);
        $partial = $findings();
        $this->assertSame([$other->id], $rechecked($states));
        $this->assertSame(1, $partial['counts'][LinkAudit::OVERBOOKED] ?? 0);
        $this->assertSame($full(), $partial);

        // Abo wechselt den Empfänger: nur dieses Abo wird neu geprüft.
        $states = (array) \Illuminate\Support\Facades\Cache::get($stateKey);
        $data['licence']->update(['customer_id' => $data['sister']->id]);
        $partial = $findings();
        $this->assertSame([$data['licence']->id], $rechecked($states));
        $this->assertSame($full(), $partial);
    }

    public function test_stock_counters_survive_a_register_change_and_follow_subscriptions_and_allocations(): void {
        $this->actingAs($this->orgAdmin());
        $data = $this->workspace();
        $keys = ['resale:allocation-conflicts:1:' . $this->organization->id . ':2026-09-07', 'resale:renewal-count:1:' . $this->organization->id . ':2026-09-07'];
        $counters = function (): array {
            $this->newRequest();
            ResaleCacheStamp::bump((int) $this->organization->id);
            $this->resetQueries();
            $overview = app(ResaleOverview::class);
            $all = (new \ReflectionMethod($overview, 'countersUncached'))->invoke($overview, $this->organization, \Carbon\CarbonImmutable::parse('2026-09-07'));

            return ['conflicts' => $all['allocation_conflicts'], 'renewals' => $all['renewals']];
        };
        $full = function () use ($counters, $keys): array {
            foreach ($keys as $key) {
                \Illuminate\Support\Facades\Cache::forget($key);
            }

            return $counters();
        };
        $renewalPlans = fn(): int => $this->queriesMatching('/from [`"]resale_subscriptions[`"] where [`"]status[`"] in \(\?, \?\) and [`"]resale_subscriptions[`"]\.[`"]organization_id[`"] = \? order by [`"]label[`"] asc$/i');
        $allocationLoads = fn(): int => $this->queriesMatching('/^select \* from [`"]resale_account_allocations[`"] where [`"]organization_id[`"] = \?$/i');

        $this->assertSame(['conflicts' => 0, 'renewals' => 0], $counters());

        // Ein Bezug ändert das Register, nicht den Bestand: beide Zähler bleiben stehen.
        $line = LexofficeVoucherLine::query()->whereHas('voucher', static fn($q) => $q->where('voucher_number', 'RE/2024/9001'))->firstOrFail();
        app(PeriodLinker::class)->attach($data['licence']->periods()->orderBy('starts_on')->firstOrFail(), $line, 12, null, null);
        $this->assertSame(['conflicts' => 0, 'renewals' => 0], $counters());
        $this->assertSame(0, $renewalPlans());
        $this->assertSame(0, $allocationLoads());

        // Beginn per Abfrage verschoben (ohne Ereignis): die Verlängerung fällt in die nächsten 60 Tage.
        DB::table('resale_subscriptions')->where('id', $data['licence']->id)->update(['starts_on' => '2024-10-01']);
        $partial = $counters();
        $this->assertSame(1, $renewalPlans());
        $this->assertSame(1, $partial['renewals']);
        $this->assertSame($full(), $partial);

        // Aufteilung per Abfrage über den Bestand hinaus: ein Konflikt.
        $group = collect(app(\App\Services\Reselling\Register\LicenseAccounts::class)->groups($this->organization))->firstOrFail(static fn($g): bool => $g->contracts()->contains('id', $data['licence']->id));
        DB::table('resale_account_allocations')->insert([
            'organization_id' => $this->organization->id, 'provider' => $group->provider->value, 'provider_account' => $group->account, 'product_key' => $data['licence']->productKey(),
            'product_label' => 'Exchange Online (Plan 1)', 'customer_id' => $data['customer']->id, 'is_own_holding' => false, 'quantity' => 99, 'valid_from' => '2024-04-10', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $partial = $counters();
        $this->assertSame(1, $partial['conflicts']);
        $this->assertSame($full(), $partial);
    }

    public function test_register_models_share_date_objects_and_remember_their_casts(): void {
        $subscription = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'company_name' => 'Hafenkontor Süd GmbH', 'starts_on' => '2024-04-10']);

        // Derselbe Rohwert ergibt dasselbe (unveränderliche) Objekt — auch über zwei Modelle.
        $subscription = $subscription->fresh();
        $this->assertSame($subscription->starts_on, $subscription->fresh()->starts_on);
        $this->assertSame('2024-04-10', $subscription->starts_on->toDateString());

        // Die gemerkte Cast-Liste folgt einer späteren Änderung der Casts.
        $this->assertSame('int', $subscription->getCasts()['id']);
        $this->assertSame($subscription->getCasts(), $subscription->getCasts());
        $this->assertIsInt($subscription->quantity);
        $subscription->mergeCasts(['quantity' => 'string']);
        $this->assertSame('string', $subscription->getCasts()['quantity']);
        $this->assertSame('1', $subscription->quantity);
    }
}
