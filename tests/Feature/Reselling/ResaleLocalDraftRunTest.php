<?php
/*
 * Created on   : Fri Sep 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleLocalDraftRunTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Finance\BillingMode;
use App\Enums\Reselling\PeriodStatus;
use App\Models\{AuditLog, Customer, ForeignCustomer, Invoice, LexofficeArticle};
use App\Models\Reselling\{ResalePeriodLink, ResaleSubscription};
use App\Notifications\Finance\ResalePeriodsDigestNotification;
use App\Services\Reselling\Register\{PeriodPlanner, ResaleLocalDraftRun};
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Serienrechnung bei lokaler Rechnungshoheit (Feature 152, Restpunkt zu
 * MVP-764): Org-Schalter, ein lokaler Entwurf je Rechnungsempfänger aus den
 * fälligen Perioden, Idempotenz über den Stempel, externe Hoheit wird
 * übersprungen, Probelauf, Vorlauf, Digest-Zahl, Zeitplan und UI-Schalter.
 */
class ResaleLocalDraftRunTest extends TestCase {
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
        Setting::set(ResaleLocalDraftRun::SETTING_ENABLED, $enabled, SettingScope::Organization, $this->organization);
        Setting::set(ResaleLocalDraftRun::SETTING_LEAD_DAYS, $leadDays, SettingScope::Organization, $this->organization);
    }

    public function test_switch_off_means_the_command_writes_nothing(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'billing_mode' => BillingMode::Workdiary]);
        $this->subscription(['customer_id' => $customer->id]);

        $this->artisan('resale:draft-local')->assertSuccessful()->expectsOutputToContain('nicht aktiv');

        $this->assertSame(0, Invoice::query()->count());
        $this->assertFalse(app(ResaleLocalDraftRun::class)->enabledFor($this->organization->fresh() ?? $this->organization), 'Default aus');

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
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Klimpel Bäder GmbH', 'billing_mode' => BillingMode::Workdiary]);
        $direct = $this->subscription(['customer_id' => $customer->id, 'lexoffice_article_id' => $article->id]);
        // … Partner mit Endkunde (Rechnung an den Partner) …
        $partner = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'LDS Systems GmbH', 'billing_mode' => BillingMode::Workdiary]);
        $kaik = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Steuerbüro Kaik']);
        $viaPartner = $this->subscription(['foreign_customer_id' => $kaik->id, 'label' => 'Microsoft 365 Business Basic', 'quantity' => 1, 'sale_unit_price' => '67.20']);
        // … eigener Bestand und Abo ohne Verkaufspreis bleiben außen vor.
        $own = $this->subscription(['is_own_holding' => true, 'company_name' => 'Eigene GmbH']);
        $unpriced = $this->subscription(['customer_id' => $customer->id, 'label' => 'Exchange Online (Plan 1)', 'sale_unit_price' => null]);

        $result = app(ResaleLocalDraftRun::class)->run($this->organization);

        $this->assertSame(['recipients' => 2, 'drafts' => 2, 'skipped' => 0, 'errors' => []], $result);
        $invoices = Invoice::query()->orderBy('customer_id')->get();
        $this->assertCount(2, $invoices, 'ein Entwurf je Rechnungsempfänger');
        foreach ($invoices as $invoice) {
            $this->assertSame(Invoice::STATUS_DRAFT, $invoice->status);
            $this->assertSame('resale', $invoice->category);
            $this->assertNull($invoice->created_by, 'Serienlauf ohne Nutzer');
        }
        $forCustomer = $invoices->firstWhere('customer_id', $customer->id);
        $forPartner = $invoices->firstWhere('customer_id', $partner->id);
        $this->assertNotNull($forCustomer);
        $this->assertNotNull($forPartner);
        $this->assertCount(2, $forCustomer->items, 'eine Position je Periode des bepreisten Abos (preisloses Abo liefert keine)');
        $this->assertSame('24.000', $forCustomer->items->first()?->quantity, 'Monatsartikel: 2 Lizenzen × 12 Monate');
        $this->assertSame('2025-08-05', $forCustomer->items->first()?->service_from?->toDateString(), 'Leistungszeitraum je Position (Review 2026-09-11)');
        $this->assertSame('2026-08-04', $forCustomer->items->first()?->service_to?->toDateString());
        $this->assertCount(2, $forPartner->items);
        // Zeitraum nur in den Spalten (eigene Zeile auf Beleg und Seite), Beschreibung = Name · Endkunde (Review 2026-09-11, Kleinigkeiten UI).
        $this->assertSame('Microsoft 365 Business Premium', (string) $forCustomer->items->first()?->description, 'Artikelname ohne Zeitraum');
        $this->assertSame('Microsoft 365 Business Basic · ' . __('resale.draft.end_customer', ['name' => 'Steuerbüro Kaik']), (string) $forPartner->items->first()?->description, 'Endkunde in der Beschreibung');
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
        $this->assertSame(2, AuditLog::query()->where('event', ResaleLocalDraftRun::AUDIT_EVENT)->where('auditable_type', Invoice::class)->count(), 'Audit-Event je Entwurf');

        // Zweiter Lauf: alles gestempelt → nichts Neues. Das preislose Abo bleibt offen, ergibt aber keinen Empfänger mit Position.
        $again = app(ResaleLocalDraftRun::class)->run($this->organization);
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

        $result = app(ResaleLocalDraftRun::class)->run($this->organization);

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

        $result = app(ResaleLocalDraftRun::class)->run($this->organization);
        $this->assertSame(0, $result['drafts']);
        $this->assertSame(0, Invoice::query()->where('customer_id', $orgExternal->id)->count());
    }

    public function test_dry_run_counts_without_writing(): void {
        $this->enable();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'billing_mode' => BillingMode::Workdiary]);
        $subscription = $this->subscription(['customer_id' => $customer->id]);

        $result = app(ResaleLocalDraftRun::class)->run($this->organization, null, true);

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
        $result = app(ResaleLocalDraftRun::class)->run($this->organization);
        $this->assertSame(1, $result['drafts']);
        $invoice = Invoice::query()->firstOrFail();
        $this->assertCount(1, $invoice->items, 'ohne Vorlauf nur die fällige Periode');
        $this->assertSame(1, $subscription->periods()->whereNotNull('draft_reference')->count());

        // Mit Vorlauf 7 Tage kommt die Periode vom 09.09.2026 in einen zweiten Entwurf.
        $this->enable(true, 7);
        $this->assertSame(7, app(ResaleLocalDraftRun::class)->leadDaysFor($this->organization));
        $result = app(ResaleLocalDraftRun::class)->run($this->organization);
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

    public function test_switch_is_saved_from_the_products_page_with_reselling_manage(): void {
        $admin = $this->orgAdmin();

        $this->actingAs($admin)->get(route('finance.resale.products'))
            ->assertOk()
            ->assertSee(__('resale.auto_draft.title'))
            ->assertSee('name="auto_local_drafts"', false);

        $this->actingAs($admin)->post(route('finance.resale.auto-draft.store'), ['auto_local_drafts' => '1', 'lead_days' => '7'])
            ->assertRedirect(route('finance.resale.products'))
            ->assertSessionHas('success');
        $organization = $this->organization->fresh();
        $this->assertNotNull($organization);
        $run = app(ResaleLocalDraftRun::class);
        $this->assertTrue($run->enabledFor($organization));
        $this->assertSame(7, $run->leadDaysFor($organization));

        // Aus + außerhalb der Grenzen → Validierungsfehler, nichts gespeichert.
        $this->actingAs($admin)->post(route('finance.resale.auto-draft.store'), ['auto_local_drafts' => '0', 'lead_days' => '400'])
            ->assertSessionHasErrors(['lead_days']);
        $this->assertTrue($run->enabledFor($this->organization->fresh() ?? $organization));

        $this->actingAs($admin)->post(route('finance.resale.auto-draft.store'), ['auto_local_drafts' => '0', 'lead_days' => '0'])
            ->assertRedirect(route('finance.resale.products'));
        $organization = $this->organization->fresh();
        $this->assertNotNull($organization);
        $this->assertFalse($run->enabledFor($organization));

        // Ohne reselling.manage: 403.
        $this->actingAs($this->orgUser())->post(route('finance.resale.auto-draft.store'), ['auto_local_drafts' => '1', 'lead_days' => '0'])
            ->assertForbidden();
    }
}
