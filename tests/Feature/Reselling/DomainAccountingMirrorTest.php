<?php
/*
 * Created on   : Thu Oct 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainAccountingMirrorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\{PeriodStatus, SubscriptionProvider};
use App\Models\Customer\Customer;
use App\Models\Domain\{DomainAccountingEntry, DomainProjection, DomainProviderConnection, DomainResellerAccount};
use App\Models\Finance\AccountingVoucher;
use App\Models\Reselling\{ResalePeriod, ResalePriceEntry, ResaleSubscription};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\LexofficeVoucher;
use App\Services\Reselling\Mirror\{DomainAccountingMirrorSource, InvoiceMirror, MirrorLine};
use App\Services\Reselling\Plan\PlanAudit;
use App\Services\Reselling\Register\{LinkProposer, PeriodPlanner, ResaleInvoiceDraftService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\{Collection, Str};
use Tests\Concerns\WithOrganization;
use Tests\Support\InteractsWithPlugins;
use Tests\TestCase;

/**
 * Buchungsjournal des Domainanbieters als Belegquelle (Feature 152, Befund 2026-10-29):
 * Monatsrechnungen des Portals an Subreseller decken deren Domain-Perioden.
 */
class DomainAccountingMirrorTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;
    use WithOrganization;

    private Customer $nordlicht;

    private DomainProviderConnection $connection;

    private DomainResellerAccount $reseller;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-10-29');
        $this->nordlicht = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Nordlicht Systems GmbH']);
        $this->connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $this->reseller = DomainResellerAccount::factory()->create([
            'organization_id' => $this->organization->id, 'connection_id' => $this->connection->id, 'external_user' => 'nordlicht-systems',
            'customer_id' => $this->nordlicht->id, 'provider_customer_number' => '90021',
        ]);
    }

    private function domain(string $name): DomainProjection {
        return DomainProjection::factory()->create([
            'organization_id' => $this->organization->id, 'connection_id' => $this->connection->id, 'external_domain' => $name,
            'domain_hash' => DomainProjection::hashFor($name), 'external_user' => 'nordlicht-systems', 'customer_id' => $this->nordlicht->id,
        ]);
    }

    private function subscription(string $domain, string $startsOn): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'domain', 'provider' => 'domainreselling', 'external_id' => $domain, 'label' => $domain,
            'customer_id' => $this->nordlicht->id, 'quantity' => 1, 'starts_on' => $startsOn, 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto',
            'status' => 'active', 'currency' => 'EUR',
        ]);
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    /** @param  array<string, mixed>  $attributes */
    private function entry(string $date, string $type, string $description, string $price, array $attributes = []): DomainAccountingEntry {
        return DomainAccountingEntry::query()->create($attributes + [
            'organization_id' => $this->organization->id, 'connection_id' => $this->connection->id, 'external_user' => 'nordlicht-systems',
            'reseller_account_id' => $this->reseller->id, 'customer_id' => $this->nordlicht->id, 'entry_date' => $date, 'type' => $type,
            'description' => $description, 'invoice_period' => '1Y', 'quantity' => '1', 'net_amount' => $price, 'currency' => 'EUR',
            'raw_hash' => hash('sha256', $date . $type . $description . json_encode($attributes)), 'synced_at' => now(),
        ]);
    }

    public function test_monthly_portal_invoices_from_the_journal_cover_the_domain_periods(): void {
        $domain = $this->domain('bewegung-beispiel.de');
        $subscription = $this->subscription('bewegung-beispiel.de', '2024-10-27');
        $this->entry('2025-10-27 03:00:00', 'RENEW_DOMAIN_ANNUAL', 'bewegung-beispiel.de', '-5.36', ['domain_projection_id' => $domain->id]);
        $this->entry('2026-10-27 03:00:00', 'RENEW_DOMAIN_ANNUAL', 'bewegung-beispiel.de', '-5.36', ['domain_projection_id' => $domain->id]);
        // Gebühr ohne Domain und Buchung eines Subusers ohne Kunden zählen nicht.
        $this->entry('2026-10-28 03:03:00', 'MONTHLY_FEE', 'monthly fee (4 zones)', '-0.18', ['domain_projection_id' => null]);
        $other = DomainResellerAccount::factory()->create(['organization_id' => $this->organization->id, 'connection_id' => $this->connection->id, 'external_user' => 'nebenhof', 'customer_id' => null]);
        $this->entry('2026-10-27 04:00:00', 'RENEW_DOMAIN_ANNUAL', 'bewegung-beispiel.de', '-5.36', ['domain_projection_id' => $domain->id, 'reseller_account_id' => $other->id, 'external_user' => 'nebenhof', 'customer_id' => null]);

        $result = (new LinkProposer)->propose($this->organization);

        $periods = $subscription->periods()->orderBy('starts_on')->get();
        $this->assertSame([PeriodStatus::Open, PeriodStatus::Billed, PeriodStatus::Billed], $periods->pluck('status')->all(), '2024 vor dem Journal, 2025 und 2026 aus den Monatsrechnungen');
        $this->assertSame(['90021-10-2025', '90021-10-2026'], $periods->slice(1)->map(static fn(ResalePeriod $p) => $p->links()->first()?->voucher_number)->values()->all());
        $this->assertSame('12.00', $periods->get(1)?->links()->first()?->months);
        $this->assertSame(2, $result['linked']);
        $this->assertSame(0, $result['lines_without_subscription']);
        $this->assertSame(2, $result['auto_confirmed'], 'Domainname passt, Buchung dicht am Periodenbeginn — sichere Treffer');

        // Beleg-Dialog aus der Periode: Domain als Position, Typ übersetzt, Quelle benannt, Artikelvergleich grün.
        $link = $periods->get(2)?->links()->first();
        $this->assertNotNull($link);
        $page = $this->actingAs($this->orgAdmin())->get(route('finance.resale.vouchers.show', ['source' => 'domain-accounting', 'voucher' => $this->reseller->id . ':2026-10', 'period' => $periods->get(2)?->sqid, 'line' => $link->linkable_id]))->assertOk();
        $page->assertSee('bewegung-beispiel.de')
            ->assertSee(__('domain.accounting.types.RENEW_DOMAIN_ANNUAL'))
            ->assertDontSee('RENEW_DOMAIN_ANNUAL')
            ->assertSee(__('resale.mirror.source_domain-accounting'))
            ->assertSee(__('resale.voucher.compare.article_ok'))
            ->assertSee('90021-10-2026');

        // Abo-Seite: unter dem Soll steht das Ist laut Bezügen (Pilot 2026-10-30) — hier der Journalbetrag 5,36 je Periode.
        $this->actingAs($this->orgAdmin())->get(route('finance.resale.show', $subscription))->assertOk()
            ->assertSee(__('resale.field.actual_sale'))
            ->assertSee(\CommonToolkit\ValueObjects\Money::of('5.36', \CommonToolkit\Enums\CurrencyCode::Euro, 2)->format());
    }

    public function test_domain_cancelled_after_its_renewal_keeps_the_paid_year(): void {
        $domain = $this->domain('ruhehof-beispiel.de');
        $subscription = $this->subscription('ruhehof-beispiel.de', '2025-06-25');
        $this->entry('2025-06-18 03:00:00', 'RENEW_DOMAIN_ANNUAL', 'ruhehof-beispiel.de', '-4.50', ['domain_projection_id' => $domain->id]);
        $this->entry('2026-06-18 03:00:00', 'RENEW_DOMAIN_ANNUAL', 'ruhehof-beispiel.de', '-4.50', ['domain_projection_id' => $domain->id]);
        // Zwei Monate nach der Verlängerung gekündigt: der Anbieter hat das Jahr berechnet und erstattet nicht.
        $subscription->forceFill(['domain_projection_id' => $domain->id, 'status' => 'ended', 'ends_on' => '2026-08-25', 'renewal' => 'cancel'])->save();
        (new PeriodPlanner)->sync($subscription->refresh());

        $last = $subscription->periods()->reorder('starts_on', 'desc')->firstOrFail();
        $this->assertSame(['2026-06-25', '2027-06-24'], [$last->starts_on->toDateString(), $last->ends_on->toDateString()], 'das bezahlte Jahr läuft voll, nicht bis zum Kündigungstag');

        (new LinkProposer)->propose($this->organization);
        $this->assertSame('12.00', $last->links()->first()?->months, 'die Verlängerung deckt das ganze Jahr');
        $analysis = (new \App\Services\Reselling\Register\RecipientReconciler)->forCustomer($this->organization, $this->nordlicht);
        $this->assertSame(0.0, $analysis['free'], 'kein freier Rest, nichts „zu viel“');

        // Bestand: die Periode war schon am Kündigungstag gekürzt und vom Lauf mit zwei Monaten bestätigt.
        $link = $last->links()->firstOrFail();
        $link->forceFill(['months' => 2, 'quantity' => 1])->save();
        $last->forceFill(['ends_on' => '2026-08-25', 'status' => PeriodStatus::Billed, 'decided_at' => now(), 'decided_by_user_id' => null])->save();
        (new PeriodPlanner)->sync($subscription->refresh());
        $this->assertSame('2027-06-24', $last->fresh()?->ends_on->toDateString(), 'die gekürzte Periode wächst auf das bezahlte Jahr');
        (new LinkProposer)->propose($this->organization);
        $this->assertSame(12.0, (float) $last->fresh()?->links()->sum('months'), 'der Lauf füllt den Bezug auf das ganze Jahr auf');
        $this->assertSame(PeriodStatus::Billed, $last->fresh()?->status);

        // Ohne Verlängerung im Journal bleibt die Kürzung auf den Kündigungstag.
        $unpaid = $this->subscription('stillhof-beispiel.de', '2025-06-25');
        $unpaid->forceFill(['domain_projection_id' => $this->domain('stillhof-beispiel.de')->id, 'status' => 'ended', 'ends_on' => '2026-08-25'])->save();
        (new PeriodPlanner)->sync($unpaid->refresh());
        $this->assertSame('2026-08-25', $unpaid->periods()->reorder('starts_on', 'desc')->firstOrFail()->ends_on->toDateString());

        // Erstattet der Anbieter das Jahr, bleibt es ebenfalls gekürzt.
        $refundedDomain = $this->domain('rueckhof-beispiel.de');
        $refunded = $this->subscription('rueckhof-beispiel.de', '2025-06-25');
        $this->entry('2026-06-18 04:00:00', 'RENEW_DOMAIN_ANNUAL', 'rueckhof-beispiel.de', '-4.50', ['domain_projection_id' => $refundedDomain->id]);
        $this->entry('2026-08-24 04:00:00', 'REFUND', 'rueckhof-beispiel.de', '4.50', ['domain_projection_id' => $refundedDomain->id]);
        $refunded->forceFill(['domain_projection_id' => $refundedDomain->id, 'status' => 'ended', 'ends_on' => '2026-08-25'])->save();
        (new PeriodPlanner)->sync($refunded->refresh());
        $this->assertSame('2026-08-25', $refunded->periods()->reorder('starts_on', 'desc')->firstOrFail()->ends_on->toDateString());
    }

    public function test_transfer_with_kept_cycle_covers_the_year_from_the_next_anniversary(): void {
        // Transfer im April, die Registry verlängert ab dem alten Laufzeitende: das bezahlte Jahr beginnt am Stichtag.
        $kept = $this->domain('optikwerk-beispiel.one');
        $kept->update(['registration_at' => '2026-04-08', 'expiration_at' => '2027-11-25', 'finalization_at' => null]);
        $keptSubscription = $this->subscription('optikwerk-beispiel.one', '2026-11-25');
        $this->entry('2026-04-08 12:00:00', 'TRANSFER_DOMAIN', 'optikwerk-beispiel.one', '-7.50', ['domain_projection_id' => $kept->id]);
        // Stichtag beginnt mit dem Transfer (zwei Tage Drift): der Buchungstag bleibt.
        $fresh = $this->domain('optikwerk-beispiel.de');
        $fresh->update(['registration_at' => '2026-04-07', 'expiration_at' => '2027-04-05', 'finalization_at' => null]);
        $freshSubscription = $this->subscription('optikwerk-beispiel.de', '2026-04-07');
        $this->entry('2026-04-07 12:00:00', 'TRANSFER_DOMAIN', 'optikwerk-beispiel.de', '-4.50', ['domain_projection_id' => $fresh->id]);
        // Das Portal hat im Voraus berechnet: die Periode gilt schon vor ihrem Beginn als berechnet.
        $keptSubscription->forceFill(['domain_projection_id' => $kept->id])->save();
        $kept->update(['reseller_account_id' => $this->reseller->id]);

        (new LinkProposer)->propose($this->organization);

        $period = $keptSubscription->periods()->orderBy('starts_on')->first();
        $this->assertSame('2026-11-25', $period?->starts_on->toDateString());
        $this->assertSame(PeriodStatus::Billed, $period?->status);
        $this->assertSame('90021-04-2026', $period?->links()->first()?->voucher_number);
        $this->assertSame(PeriodStatus::Billed, $freshSubscription->periods()->orderBy('starts_on')->first()?->status);

        $lines = app(DomainAccountingMirrorSource::class)->linesFor($this->organization, [$this->nordlicht->id], licenceOnly: false)->keyBy(static fn($line): string => $line->name);
        $this->assertSame('2026-11-25', $lines['optikwerk-beispiel.one']->serviceFrom?->toDateString());
        $this->assertSame('2027-11-24', $lines['optikwerk-beispiel.one']->serviceTo?->toDateString());
        $this->assertSame('2026-04-07', $lines['optikwerk-beispiel.de']->serviceFrom?->toDateString());
    }

    public function test_months_before_the_first_booked_monthly_invoice_were_never_invoiced(): void {
        // Befund 2026-11-16: Das Journal ist das des Inhaberkontos, der Sync hängt Buchungen über den heutigen Besitzer
        // der Domain an den Subuser — auch aus Monaten, bevor es das Unterkonto gab. Berechnet ist nur, was eine gebuchte
        // Monatsrechnung trägt; vor der ersten gab es keine.
        $domain = $this->domain('altbestand-beispiel.de');
        $subscription = $this->subscription('altbestand-beispiel.de', '2016-08-10');
        foreach (['2016-08-11', '2017-08-11', '2026-08-11'] as $date) {
            $this->entry($date . ' 03:00:00', 'RENEW_DOMAIN_ANNUAL', 'altbestand-beispiel.de', '-4.50', ['domain_projection_id' => $domain->id]);
        }
        $byYear = static fn (): Collection => $subscription->periods()->orderBy('starts_on')->get()
            ->keyBy(static fn (ResalePeriod $p): string => $p->starts_on->format('Y'));

        // Stand vor dem Befund: ohne gebuchte Monatsrechnung gilt das Journal als Rechnung, der Lauf bestätigt selbst.
        (new LinkProposer)->propose($this->organization);
        $this->assertSame(PeriodStatus::Billed, $byYear()['2016']->status);
        $this->assertNotNull($byYear()['2016']->links()->first()?->confirmed_at);

        // Lexoffice führt die Monatsrechnung als Umsatzbeleg ohne Netto; eine stornierte zählt nicht.
        $voucher = ['organization_id' => $this->organization->id, 'voucher_type' => 'salesinvoice', 'total_amount' => 9, 'currency' => 'EUR', 'archived' => false];
        LexofficeVoucher::create($voucher + ['external_id' => 'v-0817', 'voucher_status' => 'paid', 'voucher_number' => '90021-08-2017', 'voucher_date' => '2017-08-31']);
        LexofficeVoucher::create($voucher + ['external_id' => 'v-0826', 'voucher_status' => 'voided', 'voucher_number' => '90021-08-2026', 'voucher_date' => '2026-08-31']);
        $this->assertSame(['90021-08-2017'], app(InvoiceMirror::class)->bookedVoucherNumbers($this->organization, ['90021-08-2016', '90021-08-2017', '90021-08-2026']));

        $source = new DomainAccountingMirrorSource;
        $offered = $source->linesFor($this->organization, [$this->nordlicht->id], licenceOnly: false);
        $this->assertSame(['90021-08-2017'], $offered->map(static fn (MirrorLine $l): ?string => $l->voucherNumber)->all(), '2016 nie berechnet, 08/2026 noch nicht gebucht');
        $never = $source->voidedLines($this->organization, null);
        $this->assertSame(['90021-08-2016'], $never->map(static fn (MirrorLine $l): ?string => $l->voucherNumber)->all());
        $this->assertSame(MirrorLine::STATUS_NOT_INVOICED, $never->first()?->voucherStatus);
        $this->assertSame(['90021-08-2017'], $source->vouchersFor($this->organization, [$this->nordlicht->id], CarbonImmutable::parse('2015-01-01'))->map(static fn ($v): ?string => $v->voucherNumber)->all());
        $this->assertSame(MirrorLine::STATUS_NOT_INVOICED, $source->voucherByKey($this->organization, $this->reseller->id . ':2016-08')?->voucherStatus, 'der Beleg hinter dem alten Bezug bleibt lesbar');

        // Der nächste Lauf nimmt dem bestätigten Bezug die Deckung; noch nicht gebuchte Monate behalten ihren Bezug.
        (new LinkProposer)->propose($this->organization);
        $periods = $byYear();
        $this->assertSame(PeriodStatus::Open, $periods['2016']->status);
        $link = $periods['2016']->links()->first();
        $this->assertSame('0.00', $link?->months);
        $this->assertStringContainsString(Str::before((string) __('resale.link.note_not_invoiced'), ' —'), (string) $link?->note);
        $this->assertSame(PeriodStatus::Billed, $periods['2017']->status);
        $this->assertSame(PeriodStatus::Billed, $periods['2026']->status);

        $this->actingAs($this->orgAdmin())->get(route('finance.resale.reconcile.show', $this->nordlicht))->assertOk()
            ->assertSee(__('resale.reconcile.periods.not_invoiced'))
            ->assertSee(__('resale.board.writeoff_not_invoiced'));
    }

    public function test_source_groups_entries_to_monthly_vouchers_and_resolves_keys(): void {
        $domain = $this->domain('bewegung-beispiel.de');
        $second = $this->domain('wochenbuecher-beispiel.de');
        $renew = $this->entry('2026-10-27 03:00:00', 'RENEW', 'bewegung-beispiel.de', '-5.36', ['domain_projection_id' => $domain->id]);
        $this->entry('2026-10-25 08:30:00', 'RENEW', 'wochenbuecher-beispiel.de', '-5.36', ['domain_projection_id' => $second->id, 'invoice_period' => '2Y']);
        $this->entry('2026-09-02 08:30:00', 'RENEW', 'wochenbuecher-beispiel.de', '-5.36', ['domain_projection_id' => $second->id, 'raw_hash' => str_repeat('a', 64)]);

        $source = new DomainAccountingMirrorSource;
        $vouchers = $source->vouchersFor($this->organization, [$this->nordlicht->id], CarbonImmutable::parse('2026-01-01'));
        $this->assertSame(['90021-10-2026', '90021-09-2026'], $vouchers->map(static fn($v) => $v->voucherNumber)->all(), 'ein Beleg je Monat, neuester zuerst');
        $october = $vouchers->firstOrFail();
        $this->assertCount(2, $october->lines);
        $this->assertSame('2026-10-31', $october->voucherDate?->format('Y-m-d'));
        $this->assertSame('2028-10-24', $october->serviceTo?->format('Y-m-d'), 'zwei Jahre Laufzeit aus INVOICE_PERIOD');
        $this->assertSame($this->nordlicht->id, $october->recipientCustomerId);

        $byKey = $source->voucherByKey($this->organization, $this->reseller->id . ':2026-10');
        $this->assertNotNull($byKey);
        $this->assertSame('90021-10-2026', $byKey->voucherNumber);
        $line = $byKey->lines[1];
        $this->assertSame('bewegung-beispiel.de', $line->name);
        $this->assertSame($renew->id, $source->lineByKey($this->organization, $line->key)?->morphId);
        $this->assertSame('5.36', $line->totalNet->getAmount());
        $this->assertTrue(app(InvoiceMirror::class)->linesMentioningDomains($this->organization, [$this->nordlicht->id], ['bewegung-beispiel.de'])->isNotEmpty(), 'die Quelle ist registriert');
        $this->assertTrue($source->linesFor($this->organization, [$this->nordlicht->id])->isEmpty(), 'keine Lizenzpositionen');
    }

    public function test_registrar_invoice_numbers_do_not_split_the_monthly_voucher(): void {
        // „accounting invoice id“ ist die Registrar-Rechnung an den Inhaber (AR2026…), nicht die des Portals an den Subuser.
        $domain = $this->domain('bewegung-beispiel.de');
        $this->entry('2026-10-27 03:00:00', 'RENEW_DOMAIN_ANNUAL', 'bewegung-beispiel.de', '-5.36', ['domain_projection_id' => $domain->id, 'invoice_number' => 'AR20261031AD012500', 'invoice_period' => null]);
        $this->entry('2026-10-05 03:00:00', 'RENEW_DOMAIN_ANNUAL', 'bewegung-beispiel.de', '-5.36', ['domain_projection_id' => $domain->id, 'invoice_period' => null, 'raw_hash' => str_repeat('b', 64)]);

        $source = new DomainAccountingMirrorSource;
        $vouchers = $source->vouchersFor($this->organization, [$this->nordlicht->id], CarbonImmutable::parse('2026-01-01'));
        $this->assertSame(['90021-10-2026'], $vouchers->map(static fn($v) => $v->voucherNumber)->all(), 'ein Monatsbeleg, Nummer aus Kundennummer und Monat');
        $lines = $vouchers->firstOrFail()->lines;
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('AR20261031AD012500', (string) $lines[1]->description, 'die Registrar-Rechnung steht als Hinweis an der Zeile');
        $this->assertSame(1.0, $lines[0]->quantity, 'ohne Laufzeit zählt die Menge: ein Jahr');
    }

    public function test_provider_customer_number_is_saved_from_the_reseller_page(): void {
        $admin = $this->orgAdmin();
        $this->actingAs($admin)->get(route('domain-reseller.show', $this->reseller))->assertOk()->assertSee(__('domain.reseller.provider_customer_number'));
        $this->actingAs($admin)->post(route('domain-reseller.provider-number', $this->reseller), ['provider_customer_number' => '90055'])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('90055', $this->reseller->refresh()->provider_customer_number);
        $this->actingAs($admin)->post(route('domain-reseller.provider-number', $this->reseller), ['provider_customer_number' => 'nicht erlaubt'])->assertSessionHasErrors('provider_customer_number');
        $this->actingAs($admin)->post(route('domain-reseller.provider-number', $this->reseller), ['provider_customer_number' => ''])->assertRedirect();
        $this->assertNull($this->reseller->refresh()->provider_customer_number);
    }

    public function test_lines_carry_the_class_price_and_the_booked_monthly_invoice_adds_a_rest_position(): void {
        // Entscheidung 2026-10-30: das Journal nennt den Einkauf (4,50), der Subuser zahlt den Preis seiner Klasse (8,10);
        // die gebuchte Monatsrechnung (8,94) enthält darüber hinaus Posten ohne Domain → Restposition nur in Belegansichten.
        $this->reseller->forceFill(['user_class' => 'STANDARD'])->save();
        ResalePriceEntry::query()->create([
            'organization_id' => $this->organization->id, 'provider' => SubscriptionProvider::DomainReselling, 'product' => '.de', 'price_class' => 'STANDARD', 'term_months' => 12, 'interval' => 'yearly',
            'valid_from' => '2025-01-01', 'purchase_unit_price' => '4.50', 'list_unit_price' => '8.10', 'currency' => 'EUR',
        ]);
        ResalePriceEntry::query()->create([
            'organization_id' => $this->organization->id, 'provider' => SubscriptionProvider::DomainReselling, 'product' => '.de', 'price_class' => 'STANDARD', 'term_months' => 12, 'interval' => 'yearly',
            'valid_from' => '2026-11-01', 'purchase_unit_price' => '4.50', 'list_unit_price' => '9.99', 'currency' => 'EUR',
        ]);
        $domain = $this->domain('bewegung-beispiel.de');
        $this->entry('2026-10-27 03:00:00', 'RENEW_DOMAIN_ANNUAL', 'bewegung-beispiel.de', '-4.50', ['domain_projection_id' => $domain->id]);
        AccountingVoucher::factory()->create([
            'organization_id' => $this->organization->id, 'customer_id' => $this->nordlicht->id, 'voucher_type' => 'salesinvoice', 'voucher_number' => '90021-10-2026',
            'voucher_date' => '2026-10-31', 'net_amount' => '8.94', 'total_amount' => '10.64', 'is_cancellation' => false,
        ]);

        $source = new DomainAccountingMirrorSource;
        $voucher = $source->vouchersFor($this->organization, [$this->nordlicht->id], CarbonImmutable::parse('2026-01-01'))->firstOrFail();
        $this->assertCount(2, $voucher->lines, 'Domainposition plus Restposition');
        [$line, $rest] = $voucher->lines;
        $this->assertSame('bewegung-beispiel.de', $line->name);
        $this->assertSame('8.10', $line->totalNet->getAmount(), 'Klassenpreis am Buchungstag, nicht der Einkauf und nicht die Zeile ab November');
        $this->assertStringContainsString(__('resale.mirror.domain_price_class', ['class' => 'STANDARD']), (string) $line->description);
        $this->assertSame(__('resale.mirror.domain_rest'), $rest->name);
        $this->assertSame('0.84', $rest->totalNet->getAmount(), 'gebuchte Rechnung minus Domainpositionen');
        $this->assertNull($rest->serviceFrom, 'die Restposition deckt keine Periode');
        $this->assertFalse($rest->articleIsLicence);

        $byKey = $source->voucherByKey($this->organization, $this->reseller->id . ':2026-10');
        $this->assertCount(2, $byKey?->lines ?? []);
        $this->assertCount(1, $source->linesFor($this->organization, [$this->nordlicht->id], licenceOnly: false), 'für Vorschläge und Bezüge nur die Domainpositionen');
        $this->assertSame('8.10', $source->linesByIds($this->organization, [$line->morphId])->first()?->totalNet->getAmount());

        // Ohne gebuchte Rechnung keine Restposition; ohne Klassenzeile der Journalbetrag.
        AccountingVoucher::query()->delete();
        $this->reseller->forceFill(['user_class' => 'RESELLER'])->save();
        $plain = $source->voucherByKey($this->organization, $this->reseller->id . ':2026-10');
        $this->assertCount(1, $plain?->lines ?? []);
        $this->assertSame('4.50', $plain?->lines[0]->totalNet->getAmount());
    }

    public function test_domains_in_the_subuser_account_are_billed_by_the_portal_not_by_the_register(): void {
        // Pilot 2026-10-31 (MVP-1446): der Subuser hat ein eigenes Konto beim Domainanbieter, das Portal stellt ihm die Rechnungen.
        $own = DomainResellerAccount::factory()->create(['organization_id' => $this->organization->id, 'connection_id' => $this->connection->id, 'external_user' => 'eigenes-konto', 'depth' => 0, 'customer_id' => null]);
        $subDomain = $this->domain('bewegung-beispiel.de');
        $subDomain->forceFill(['reseller_account_id' => $this->reseller->id])->save();
        $ownDomain = $this->domain('eigenbau-beispiel.de');
        $ownDomain->forceFill(['reseller_account_id' => $own->id])->save();
        $portal = $this->subscription('bewegung-beispiel.de', '2024-11-10');
        $portal->forceFill(['domain_projection_id' => $subDomain->id])->save();
        $direct = $this->subscription('eigenbau-beispiel.de', '2024-11-12');
        $direct->forceFill(['domain_projection_id' => $ownDomain->id])->save();

        $this->assertTrue($portal->fresh()?->isBilledByProvider());
        $this->assertSame('nordlicht-systems', $portal->fresh()?->providerBillingAccount());
        $this->assertFalse($direct->fresh()?->isBilledByProvider());

        // Plan-Abgleich: nur der Domain im eigenen Konto fehlt eine Serienvorlage.
        $this->enablePluginFor($this->organization, LexofficePlugin::ID, ['api_key' => 'lex-key', 'request_interval' => '0']);
        $without = array_values(array_filter(app(PlanAudit::class)->findings($this->organization), static fn (array $f): bool => $f['kind'] === PlanAudit::SUBSCRIPTION_WITHOUT_PLAN));
        $this->assertSame([$direct->id], array_map(static fn (array $f): ?int => $f['subscription']?->id, $without));

        // Entwurf und Serienlauf (30 Tage Vorlauf): die Subuser-Domain kommt nie in eine Rechnung des Registers.
        $open = app(ResaleInvoiceDraftService::class)->openPeriodsFor($this->nordlicht, CarbonImmutable::parse('2026-11-28'));
        $this->assertNotEmpty($open);
        $this->assertSame([$direct->id], array_values(array_unique($open->map(static fn (ResalePeriod $p): int => (int) $p->subscription_id)->all())));

        // Mappe und Abo-Seite nennen den Grund.
        $admin = $this->orgAdmin();
        $hint = __('resale.plan.portal_billed', ['account' => 'nordlicht-systems']);
        $this->actingAs($admin)->get(route('finance.resale.reconcile.show', $this->nordlicht))->assertOk()->assertSee($hint);
        $this->actingAs($admin)->get(route('finance.resale.show', $portal->sqid))->assertOk()->assertSee($hint);
        $this->actingAs($admin)->get(route('finance.resale.show', $direct->sqid))->assertOk()->assertDontSee(__('resale.plan.portal_billed', ['account' => 'eigenes-konto']));
    }
}
