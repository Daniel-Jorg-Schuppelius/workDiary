<?php
/*
 * Created on   : Fri Sep 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleDraftRunTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Finance\{BillingMode, TransferStatus};
use App\Enums\Invoicing\InvoiceStatus;
use App\Enums\Reselling\PeriodStatus;
use App\Models\Audit\AuditLog;
use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Finance\BillingTransfer;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\Invoice;
use App\Models\Reselling\{ResalePeriodLink, ResaleSubscription};
use App\Notifications\Finance\ResalePeriodsDigestNotification;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\LexofficeArticle;
use App\Services\Reselling\Register\{DomainSubscriptionSync, PeriodPlanner, ResaleDraftRun};
use App\Settings\SettingScope;
use App\Support\{MorphMap, Setting};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\WithOrganization;
use Tests\Support\{FakePluginHttp, InteractsWithPlugins};
use Tests\TestCase;

/**
 * Serienrechnung bei lokaler Rechnungshoheit (Feature 152, Restpunkt zu
 * MVP-764): Org-Schalter, ein lokaler Entwurf je Rechnungsempfänger aus den
 * fälligen Perioden, Idempotenz über den Stempel, externe Hoheit wird
 * übersprungen, Probelauf, Vorlauf, Digest-Zahl, Zeitplan und UI-Schalter.
 */
class ResaleDraftRunTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-04');
    }

    /** @param array<string, mixed> $attributes */
    private function subscription(array $attributes = []): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'label' => 'Microsoft 365 Business Premium',
            'quantity' => 2, 'starts_on' => '2025-08-05', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR',
            'sale_unit_price' => '247.20',
        ], $attributes));
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    private function enable(bool $enabled = true, int $leadDays = 0): void {
        Setting::set(ResaleDraftRun::SETTING_ENABLED, $enabled, SettingScope::Organization, $this->organization);
        Setting::set(ResaleDraftRun::SETTING_LEAD_DAYS, $leadDays, SettingScope::Organization, $this->organization);
    }

    public function test_switch_off_means_the_command_writes_nothing(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'billing_mode' => BillingMode::Workdiary]);
        $this->subscription(['customer_id' => $customer->id]);

        $this->artisan('resale:draft-local')->assertSuccessful()->expectsOutputToContain('nicht aktiv');

        $this->assertSame(0, Invoice::query()->count());
        $this->assertFalse(app(ResaleDraftRun::class)->enabledFor($this->organization->fresh() ?? $this->organization), 'Default aus');

        // --force übergeht den Schalter für einen einmaligen Lauf.
        $this->artisan('resale:draft-local', ['--force' => true])->assertSuccessful()->expectsOutputToContain('1 Entwürfe');
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_run_drafts_one_local_invoice_per_recipient_and_is_idempotent(): void {
        Notification::fake();
        $admin = $this->orgAdmin();
        $this->enable();
        $article = LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-bp', 'name' => 'Microsoft 365 Business Premium', 'article_number' => 'BP',
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '20.60', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
        // Kunde direkt (zwei fällige Perioden) …
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Badhaus Bäder GmbH', 'billing_mode' => BillingMode::Workdiary]);
        $direct = $this->subscription(['customer_id' => $customer->id, 'article_ref' => 'lex:' . $article->id]);
        // … Partner mit Endkunde (Rechnung an den Partner) …
        $partner = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Nordlicht Systems GmbH', 'billing_mode' => BillingMode::Workdiary]);
        $zahlenwerk = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Steuerbüro Zahlenwerk']);
        $viaPartner = $this->subscription(['foreign_customer_id' => $zahlenwerk->id, 'label' => 'Microsoft 365 Business Basic', 'quantity' => 1, 'sale_unit_price' => '67.20']);
        // … eigener Bestand und Abo ohne Verkaufspreis bleiben außen vor.
        $own = $this->subscription(['is_own_holding' => true, 'company_name' => 'Eigene GmbH']);
        $unpriced = $this->subscription(['customer_id' => $customer->id, 'label' => 'Exchange Online (Plan 1)', 'sale_unit_price' => null]);

        $result = app(ResaleDraftRun::class)->run($this->organization);

        $this->assertSame(['recipients' => 2, 'drafts' => 2, 'skipped' => 0, 'errors' => []], $result);
        $invoices = Invoice::query()->orderBy('customer_id')->get();
        $this->assertCount(2, $invoices, 'ein Entwurf je Rechnungsempfänger');
        foreach ($invoices as $invoice) {
            $this->assertSame(InvoiceStatus::Draft, $invoice->status);
            $this->assertSame('resale', $invoice->category);
            $this->assertNull($invoice->created_by, 'Serienlauf ohne Nutzer');
        }
        $forCustomer = $invoices->firstWhere('customer_id', $customer->id);
        $forPartner = $invoices->firstWhere('customer_id', $partner->id);
        $this->assertNotNull($forCustomer);
        $this->assertNotNull($forPartner);
        // MVP-1436: zwei zusammenhängende offene Perioden sind eine Position über beide Jahre (preisloses Abo liefert keine).
        $this->assertCount(1, $forCustomer->items, 'zusammenhängende Perioden des bepreisten Abos = eine Position');
        $this->assertSame('48.000', $forCustomer->items->first()?->quantity, 'Monatsartikel: 2 Lizenzen × 24 Monate');
        $this->assertSame('2025-08-05', $forCustomer->items->first()?->service_from?->toDateString(), 'Leistungszeitraum vom ersten Beginn (Review 2026-09-11)');
        $this->assertSame('2027-08-04', $forCustomer->items->first()?->service_to?->toDateString(), '… bis zum letzten Ende');
        $this->assertCount(1, $forPartner->items);
        $this->assertSame('2.000', $forPartner->items->first()?->quantity, 'eine Lizenz, zwei Jahre');
        $this->assertSame(__('resale.draft.unit_year'), $forPartner->items->first()?->unit, 'Jahresabo mit einer Lizenz: Einheit Jahr');
        // Zeitraum nur in den Spalten (eigene Zeile auf Beleg und Seite), Beschreibung = Name · Endkunde (Review 2026-09-11, Kleinigkeiten UI).
        $this->assertSame('Microsoft 365 Business Premium', (string) $forCustomer->items->first()?->description, 'Artikelname ohne Zeitraum');
        $this->assertSame('Microsoft 365 Business Basic · ' . __('resale.draft.end_customer', ['name' => 'Steuerbüro Zahlenwerk']), (string) $forPartner->items->first()?->description, 'Endkunde in der Beschreibung');
        $this->assertSame('2025-08-05', $forPartner->items->first()?->service_from?->toDateString());

        foreach ([$direct, $viaPartner] as $subscription) {
            foreach ($subscription->periods()->get() as $period) {
                $this->assertSame(PeriodStatus::Billed, $period->status);
                $this->assertNotNull($period->draft_reference, 'Stempel gesetzt');
                $this->assertNotNull($period->draft_created_at);
                $this->assertTrue($period->isProposedOnly(), 'Bezug auf die Rechnungsposition ist ein Vorschlag');
            }
        }
        $this->assertSame(0, $own->periods()->whereNotNull('draft_reference')->count(), 'eigener Bestand wird nie berechnet');
        $this->assertSame(0, $unpriced->periods()->whereNotNull('draft_reference')->count(), 'ohne Verkaufspreis kein Stempel');
        $this->assertSame(4, ResalePeriodLink::query()->count());
        $this->assertSame(2, AuditLog::query()->where('event', ResaleDraftRun::AUDIT_EVENT)->where('auditable_type', MorphMap::stableKey(Invoice::class))->count(), 'Audit-Event je Entwurf');

        // Zweiter Lauf: alles gestempelt → nichts Neues. Das preislose Abo bleibt offen, ergibt aber keinen Empfänger mit Position.
        $again = app(ResaleDraftRun::class)->run($this->organization);
        $this->assertSame(0, $again['drafts']);
        $this->assertSame([], $again['errors']);
        $this->assertSame(2, Invoice::query()->count(), 'kein zweiter Entwurf');
        $this->assertSame(4, ResalePeriodLink::query()->count());

        // Der Wochen-Digest nennt die ausstehenden Entwürfe der letzten 7 Tage (zwei Rechnungsnummern).
        $this->artisan('resale:digest')->assertSuccessful();
        Notification::assertSentTo($admin, ResalePeriodsDigestNotification::class, static fn(ResalePeriodsDigestNotification $n): bool => $n->draftCount === 2 && $n->total() >= 2);
    }

    public function test_recipients_with_external_billing_are_skipped(): void {
        $this->enable();
        $lexoffice = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Lexoffice-Kunde', 'billing_mode' => BillingMode::Lexoffice]);
        $external = $this->subscription(['customer_id' => $lexoffice->id]);
        $local = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Lokal-Kunde', 'billing_mode' => BillingMode::Workdiary]);
        $this->subscription(['customer_id' => $local->id]);

        $result = app(ResaleDraftRun::class)->run($this->organization);

        $this->assertSame(2, $result['recipients']);
        $this->assertSame(1, $result['drafts']);
        $this->assertSame(1, $result['skipped'], 'externe Hoheit: kein lokaler Entwurf, kein Fehler');
        $this->assertSame([], $result['errors']);
        $this->assertSame(0, Invoice::query()->where('customer_id', $lexoffice->id)->count());
        $this->assertSame(1, Invoice::query()->where('customer_id', $local->id)->count());
        $this->assertSame(0, $external->periods()->whereNotNull('draft_reference')->count(), 'Perioden des Lexoffice-Empfängers bleiben ungestempelt');

        // Org-Default extern, kein Kunden-Override → ebenfalls übersprungen.
        $orgExternal = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Ohne Override', 'billing_mode' => null]);
        $this->subscription(['customer_id' => $orgExternal->id]);
        $settings = (array) $this->organization->settings;
        $settings['billing_mode'] = BillingMode::Lexoffice->value;
        $this->organization->forceFill(['settings' => $settings])->save();

        $result = app(ResaleDraftRun::class)->run($this->organization);
        $this->assertSame(0, $result['drafts']);
        $this->assertSame(0, Invoice::query()->where('customer_id', $orgExternal->id)->count());
    }

    public function test_dry_run_counts_without_writing(): void {
        $this->enable();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'billing_mode' => BillingMode::Workdiary]);
        $subscription = $this->subscription(['customer_id' => $customer->id]);

        $result = app(ResaleDraftRun::class)->run($this->organization, null, true);

        $this->assertSame(['recipients' => 1, 'drafts' => 1, 'skipped' => 0, 'errors' => []], $result);
        $this->assertSame(0, Invoice::query()->count(), 'Probelauf schreibt keine Rechnung');
        $this->assertSame(0, ResalePeriodLink::query()->count());
        $this->assertSame(0, $subscription->periods()->whereNotNull('draft_reference')->count(), 'Probelauf stempelt nicht');
        $this->assertSame([PeriodStatus::Open, PeriodStatus::Open], $subscription->periods()->get()->map(static fn($p) => $p->status)->all());

        $this->artisan('resale:draft-local', ['--dry-run' => true])->assertSuccessful()->expectsOutputToContain('Probelauf');
        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_lead_days_draft_periods_that_start_soon(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'billing_mode' => BillingMode::Workdiary]);
        // Perioden 09.09.2025 (fällig) und 09.09.2026 (beginnt in 5 Tagen).
        $subscription = $this->subscription(['customer_id' => $customer->id, 'starts_on' => '2025-09-09']);
        $this->assertSame(2, $subscription->periods()->count());

        $this->enable(true, 0);
        $result = app(ResaleDraftRun::class)->run($this->organization);
        $this->assertSame(1, $result['drafts']);
        $invoice = Invoice::query()->firstOrFail();
        $this->assertCount(1, $invoice->items, 'ohne Vorlauf nur die fällige Periode');
        $this->assertSame(1, $subscription->periods()->whereNotNull('draft_reference')->count());

        // Mit Vorlauf 7 Tage kommt die Periode vom 09.09.2026 in einen zweiten Entwurf.
        $this->enable(true, 7);
        $this->assertSame(7, app(ResaleDraftRun::class)->leadDaysFor($this->organization));
        $result = app(ResaleDraftRun::class)->run($this->organization);
        $this->assertSame(1, $result['drafts']);
        $this->assertSame(2, Invoice::query()->count());
        $this->assertSame(2, $subscription->periods()->whereNotNull('draft_reference')->count());
        $upcoming = $subscription->periods()->reorder('starts_on', 'desc')->firstOrFail();
        $this->assertSame('2026-09-09', $upcoming->starts_on->toDateString());
        $this->assertSame(PeriodStatus::Billed, $upcoming->status);
    }

    public function test_command_is_registered_in_the_scheduler_with_labels(): void {
        $jobs = (array) config('scheduler.jobs');
        $this->assertArrayHasKey('resale.draft_local', $jobs);
        $this->assertSame('resale:draft-local', $jobs['resale.draft_local']['command']);
        $this->assertSame('core', $jobs['resale.draft_local']['criticality']);
        $this->assertSame(['type' => 'dailyAt', 'time' => '06:20'], $jobs['resale.draft_local']['cadence']);
        foreach (['de', 'en', 'fr', 'it', 'es'] as $locale) {
            $this->assertTrue(app('translator')->has('scheduler.job.resale.draft_local', $locale, false), "Label fehlt: $locale");
        }
    }

    public function test_external_run_transfers_to_the_accounting_program_and_skips_local_recipients(): void {
        // MVP-1438: monatlicher Serienlauf nach außen — eine Übergabe je Lexoffice-Empfänger; lokale Empfänger überspringt er.
        $this->enablePluginFor($this->organization, LexofficePlugin::ID, ['api_key' => 'lex-key', 'request_interval' => '0']);
        Setting::set(ResaleDraftRun::SETTING_EXTERNAL_ENABLED, true, SettingScope::Organization, $this->organization);
        Setting::set(ResaleDraftRun::SETTING_EXTERNAL_LEAD_DAYS, 30, SettingScope::Organization, $this->organization);
        $lexoffice = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Musterkanzlei Beispiel', 'billing_mode' => BillingMode::Lexoffice]);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => '2d3e4f50-6a7b-4c8d-9e0f-1a2b3c4d5e6f', 'referenceable_type' => $lexoffice->getMorphClass(), 'referenceable_id' => $lexoffice->getKey(),
        ]);
        // Domain mit einer Lizenz: fällige Periode ab 09.09.2025 und eine, die in fünf Tagen beginnt (Vorlauf 30) → eine Zeile „2 Jahr".
        $domain = $this->subscription(['customer_id' => $lexoffice->id, 'kind' => 'domain', 'provider' => 'domainreselling', 'label' => 'beispiel-kanzlei.de', 'quantity' => 1, 'starts_on' => '2025-09-09', 'sale_unit_price' => '9.00']);
        $local = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Lokal-Kunde', 'billing_mode' => BillingMode::Workdiary]);
        $this->subscription(['customer_id' => $local->id]);
        $payloads = [];
        FakePluginHttp::fake([
            'https://api.lexware.io/v1/invoices*' => static function (\Psr\Http\Message\RequestInterface $request) use (&$payloads) {
                $payloads[] = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);

                return FakePluginHttp::response(['id' => 'draft-90', 'resourceUri' => 'https://api.lexware.io/v1/invoices/draft-90'], 201);
            },
        ]);
        $run = app(ResaleDraftRun::class);
        $this->assertTrue($run->coversRecipient($this->organization, $lexoffice), 'Serienlauf nach außen aktiv');
        $this->assertFalse($run->coversRecipient($this->organization, $local), 'lokaler Serienlauf aus');

        $dry = $run->run($this->organization, null, true, true);
        $this->assertSame(['recipients' => 2, 'drafts' => 1, 'skipped' => 1, 'errors' => []], $dry);
        $this->assertSame(0, BillingTransfer::query()->count(), 'Probelauf schreibt nichts');

        $result = $run->run($this->organization, null, false, true);
        $this->assertSame(['recipients' => 2, 'drafts' => 1, 'skipped' => 1, 'errors' => []], $result);
        $transfer = BillingTransfer::query()->where('customer_id', $lexoffice->id)->firstOrFail();
        $this->assertSame(TransferStatus::Transferred, $transfer->status);
        $this->assertSame(1, $transfer->positions()->count(), 'beide Jahre eine Position (MVP-1436)');
        $this->assertSame(2, $domain->periods()->where('draft_reference', 'draft-90')->count(), 'fällige und bevorstehende Periode gestempelt');
        $this->assertCount(1, $payloads);
        $this->assertEquals(2, $payloads[0]['lineItems'][0]['quantity']);
        $this->assertSame(0, Invoice::query()->count(), 'kein lokaler Entwurf');
        $this->assertSame(1, AuditLog::query()->where('event', ResaleDraftRun::AUDIT_EVENT)->where('auditable_type', MorphMap::stableKey(BillingTransfer::class))->count(), 'Audit an der Übergabe');

        // Zweiter Lauf: gestempelt → nichts Neues. Der lokale Lauf überspringt den Lexoffice-Empfänger und entwirft den lokalen.
        $again = $run->run($this->organization, null, false, true);
        $this->assertSame(0, $again['drafts']);
        $this->assertSame(1, BillingTransfer::query()->count());
        $localRun = $run->run($this->organization);
        $this->assertSame(['recipients' => 1, 'drafts' => 1, 'skipped' => 0, 'errors' => []], $localRun, 'Lexoffice-Perioden sind gestempelt, nur der lokale Empfänger bleibt');
        $this->assertSame(1, Invoice::query()->where('customer_id', $local->id)->count());

        // Befehl: ohne Schalter übersprungen, mit --force läuft er; Zeitplan monatlich am Ersten mit Labels in fünf Sprachen.
        Setting::set(ResaleDraftRun::SETTING_EXTERNAL_ENABLED, false, SettingScope::Organization, $this->organization);
        $this->artisan('resale:draft-external')->assertSuccessful()->expectsOutputToContain('nicht aktiv');
        $this->artisan('resale:draft-external', ['--force' => true, '--dry-run' => true])->assertSuccessful()->expectsOutputToContain('Probelauf');
        $jobs = (array) config('scheduler.jobs');
        $this->assertSame('resale:draft-external', $jobs['resale.draft_external']['command']);
        $this->assertSame(['type' => 'monthlyOn', 'day' => 1, 'time' => '06:25'], $jobs['resale.draft_external']['cadence']);
        foreach (['de', 'en', 'fr', 'it', 'es'] as $locale) {
            $this->assertTrue(app('translator')->has('scheduler.job.resale.draft_external', $locale, false), "Label fehlt: $locale");
        }
    }

    public function test_external_organization_saves_the_external_switch_from_the_billing_dialog(): void {
        $admin = $this->orgAdmin();
        $settings = (array) $this->organization->settings;
        $settings['billing_mode'] = BillingMode::Lexoffice->value;
        $this->organization->forceFill(['settings' => $settings])->save();

        $this->actingAs($admin)->get(route('finance.resale.settings.billing'))->assertOk()
            ->assertSee('name="auto_external_drafts"', false)
            ->assertDontSee('name="auto_local_drafts"', false)
            ->assertSee(__('resale.auto_draft.external_enabled', ['program' => 'Lexoffice']));
        $this->actingAs($admin)->post(route('finance.resale.auto-draft.store'), ['auto_external_drafts' => '1', 'external_lead_days' => '30'])
            ->assertRedirect(route('finance.resale.index'))
            ->assertSessionHas('success', __('resale.auto_draft.flash.external_enabled', ['days' => 30, 'program' => 'Lexoffice']));
        $organization = $this->organization->fresh();
        $this->assertNotNull($organization);
        $run = app(ResaleDraftRun::class);
        $this->assertTrue($run->enabledFor($organization, true));
        $this->assertSame(30, $run->leadDaysFor($organization, true));
        $this->assertFalse($run->enabledFor($organization), 'lokaler Schalter unberührt');
        $this->actingAs($admin)->post(route('finance.resale.auto-draft.store'), ['auto_external_drafts' => '0', 'external_lead_days' => '120'])
            ->assertSessionHasErrors(['external_lead_days']);
        $this->assertTrue($run->enabledFor($this->organization->fresh() ?? $organization, true));
    }

    public function test_switch_is_saved_from_the_billing_dialog_with_reselling_manage(): void {
        $admin = $this->orgAdmin();

        // Feature 185 (MVP-1405): Dialog „Abrechnung" statt Kasten in der Produkt-Einstufung.
        $this->actingAs($admin)->get(route('finance.resale.products'))
            ->assertOk()
            ->assertDontSee('name="auto_local_drafts"', false);
        $this->actingAs($admin)->get(route('finance.resale.settings.billing'))
            ->assertOk()
            ->assertSee(__('resale.auto_draft.enabled'))
            ->assertSee('name="auto_local_drafts"', false);
        $this->actingAs($admin)->get(route('finance.resale.index'))->assertOk()->assertSee(route('finance.resale.settings.billing'));

        $this->actingAs($admin)->post(route('finance.resale.auto-draft.store'), ['auto_local_drafts' => '1', 'lead_days' => '7', 'domain_price_class' => 'kunden'])
            ->assertRedirect(route('finance.resale.index'))
            ->assertSessionHas('success');
        $organization = $this->organization->fresh();
        $this->assertNotNull($organization);
        $run = app(ResaleDraftRun::class);
        $this->assertTrue($run->enabledFor($organization));
        $this->assertSame(7, $run->leadDaysFor($organization));
        $this->assertSame('KUNDEN', DomainSubscriptionSync::directPriceClass($organization), 'Preisstaffel für direkte Kunden, groß geschrieben (Feature 185, 2026-10-30)');
        $this->actingAs($admin)->post(route('finance.resale.auto-draft.store'), ['auto_local_drafts' => '1', 'lead_days' => '7', 'domain_price_class' => 'kein name!'])
            ->assertSessionHasErrors(['domain_price_class']);

        // Aus + außerhalb der Grenzen → Validierungsfehler, nichts gespeichert.
        $this->actingAs($admin)->post(route('finance.resale.auto-draft.store'), ['auto_local_drafts' => '0', 'lead_days' => '400'])
            ->assertSessionHasErrors(['lead_days']);
        $this->assertTrue($run->enabledFor($this->organization->fresh() ?? $organization));

        $this->actingAs($admin)->post(route('finance.resale.auto-draft.store'), ['auto_local_drafts' => '0', 'lead_days' => '0'])
            ->assertRedirect(route('finance.resale.index'));

        // Externe Hoheit: nur der Hinweis, kein Schalter.
        $this->organization->forceFill(['settings' => array_merge((array) $this->organization->settings, ['billing_mode' => 'lexoffice'])])->save();
        $this->actingAs($admin->refresh()->unsetRelation('organization'))->get(route('finance.resale.settings.billing'))
            ->assertOk()
            ->assertSee(__('resale.auto_draft.external_hint', ['program' => 'Lexoffice']))
            ->assertDontSee('name="auto_local_drafts"', false)
            ->assertSee('name="domain_price_class"', false);
        $this->organization->forceFill(['settings' => array_merge((array) $this->organization->settings, ['billing_mode' => 'workdiary'])])->save();
        $organization = $this->organization->fresh();
        $this->assertNotNull($organization);
        $this->assertFalse($run->enabledFor($organization));

        // Ohne reselling.manage: 403.
        $this->actingAs($this->orgUser())->post(route('finance.resale.auto-draft.store'), ['auto_local_drafts' => '1', 'lead_days' => '0'])
            ->assertForbidden();
    }
}
