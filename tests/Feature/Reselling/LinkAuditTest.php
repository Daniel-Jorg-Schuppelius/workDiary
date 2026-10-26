<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LinkAuditTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\PeriodStatus;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Reselling\{ResalePeriodLink, ResaleSubscription};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\{LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine};
use App\Services\Reselling\Register\{LinkAudit, PeriodLinker, PeriodPlanner};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Prüffälle an Bezügen (Feature 185, MVP-1403): die Produktivmuster vom
 * 08.09.2026 synthetisch — RE/2025/0900 Pos. 2 (24 Monate) mit 60 Lizenzmonaten
 * an zwei Perioden, RE/2023/0550 (Leistung 2023/24) an der Periode 2022/23.
 */
class LinkAuditTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private LexofficeArticle $exchange;

    private Customer $schub;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-10-26');
        $this->exchange = LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-exo', 'name' => 'Exchange Online (Plan 1)', 'article_number' => 'ART-EXO',
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '3.95', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
        $this->schub = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Schub- und Schleppreederei']);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-schub', 'referenceable_type' => $this->schub->getMorphClass(), 'referenceable_id' => $this->schub->getKey(),
        ]);
    }

    private function line(string $number, string $date, float $quantity, ?string $serviceFrom, ?string $serviceTo): LexofficeVoucherLine {
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-' . $number, 'contact_external_id' => 'c-schub', 'voucher_type' => 'invoice',
            'voucher_status' => 'paid', 'voucher_number' => $number, 'voucher_date' => $date, 'total_amount' => 100, 'currency' => 'EUR', 'archived' => false, 'lines_synced_at' => now(),
            'service_starts_on' => $serviceFrom, 'service_ends_on' => $serviceTo,
        ]);

        return LexofficeVoucherLine::create([
            'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => 1, 'type' => 'service',
            'external_article_id' => 'art-exo', 'lexoffice_article_id' => $this->exchange->id, 'name' => 'Exchange Online (Plan 1)',
            'quantity' => $quantity, 'unit_name' => 'Monat', 'unit_net' => '3.95', 'total_net' => (string) round($quantity * 3.95, 2), 'tax_rate' => 19, 'currency' => 'EUR',
        ]);
    }

    private function contract(string $externalId, int $quantity, string $startsOn, string $endsOn): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'external_id' => $externalId, 'label' => 'Exchange Online (Plan 1)',
            'customer_id' => $this->schub->id, 'article_ref' => 'lex:' . $this->exchange->id, 'quantity' => $quantity, 'starts_on' => $startsOn, 'ends_on' => $endsOn,
            'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'cancel', 'status' => 'ended', 'currency' => 'EUR', 'sale_unit_price' => '47.40',
        ]);
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    public function test_overbooked_and_mismatched_links_are_reported_and_can_be_moved(): void {
        $admin = $this->orgAdmin();
        $contract = $this->contract('ent-1', 1, '2022-10-13', '2025-10-12');
        [$p2022, $p2023, $p2024] = $contract->periods()->get()->all();
        $linker = new PeriodLinker;

        // Überbucht: eine 24-Monats-Position trägt 12 + 24 = 36 Lizenzmonate (Prüfung umgangen wie beim Altbestand).
        $block = $this->line('RE/2025/0900', '2025-08-22', 24, '2024-08-01', '2026-07-31');
        $linker->attach($p2024, $block, 12, null, $admin->id);
        ResalePeriodLink::query()->create([
            'organization_id' => $this->organization->id, 'period_id' => $p2023->id, 'subscription_id' => $contract->id, 'linkable_type' => $block->getMorphClass(), 'linkable_id' => $block->id,
            'voucher_number' => 'RE/2025/0900', 'voucher_date' => '2025-08-22', 'quantity' => 2, 'months' => 24, 'currency' => 'EUR', 'origin' => 'manual', 'confirmed_at' => now(),
        ]);
        // Zeitlich unpassend: Leistung 2023/24 hängt an der Periode 2022/23.
        $late = $this->line('RE/2023/0550', '2023-10-18', 12, '2023-10-18', '2024-10-17');
        ResalePeriodLink::query()->create([
            'organization_id' => $this->organization->id, 'period_id' => $p2022->id, 'subscription_id' => $contract->id, 'linkable_type' => $late->getMorphClass(), 'linkable_id' => $late->id,
            'voucher_number' => 'RE/2023/0550', 'voucher_date' => '2023-10-18', 'quantity' => 1, 'months' => 12, 'currency' => 'EUR', 'origin' => 'manual', 'confirmed_at' => now(),
        ]);
        $p2023->refresh();
        $p2023->forceFill(['status' => PeriodStatus::Billed])->save();

        $findings = (new LinkAudit)->forSubscriptions($this->organization, ResaleSubscription::query()->with('periods.links')->get());
        $kinds = array_count_values(array_column($findings, 'kind'));
        $this->assertSame(1, $kinds[LinkAudit::OVERBOOKED] ?? 0);
        $this->assertSame(1, $kinds[LinkAudit::MISMATCH] ?? 0);
        $overbooked = array_values(array_filter($findings, static fn(array $f): bool => $f['kind'] === LinkAudit::OVERBOOKED))[0];
        $this->assertSame(12.0, $overbooked['excess'], '36 von 24');
        $this->assertCount(2, $overbooked['links']);
        $mismatch = array_values(array_filter($findings, static fn(array $f): bool => $f['kind'] === LinkAudit::MISMATCH))[0];
        $this->assertSame($p2022->id, $mismatch['period']?->id);
        $this->assertNull($mismatch['suggested'], '2023/24 ist schon über die 24er-Position voll — kein freier Vorschlag');

        // Die 24er-Position vom falschen Jahr lösen → jetzt ist 2023/24 frei und wird vorgeschlagen.
        ResalePeriodLink::query()->where('period_id', $p2023->id)->delete();
        $p2023->forceFill(['status' => PeriodStatus::Open])->save();
        $findings = (new LinkAudit)->forSubscriptions($this->organization, ResaleSubscription::query()->with('periods.links')->get());
        $this->assertCount(1, $findings, 'nur noch der zeitliche Prüffall');
        $this->assertSame($p2023->id, $findings[0]['suggested']?->id);

        // Lizenzkonto zeigt den Prüffall; „verschieben" hängt den Bezug um.
        $group = app(\App\Services\Reselling\Register\LicenseAccounts::class)->groups($this->organization);
        $key = array_key_first($group);
        $this->actingAs($admin)->get(route('finance.resale.accounts.show', (string) $key))->assertOk()->assertSee(__('resale.account.finding.mismatch'));
        $link = ResalePeriodLink::query()->where('period_id', $p2022->id)->firstOrFail();
        $this->actingAs($admin)->post(route('finance.resale.links.move', $link->sqid), ['period_id' => $p2023->sqid])->assertRedirect()->assertSessionHas('success');
        $this->assertSame(0, ResalePeriodLink::query()->where('period_id', $p2022->id)->count());
        $this->assertSame(1, ResalePeriodLink::query()->where('period_id', $p2023->id)->count());
        $this->assertSame(PeriodStatus::Billed, $p2023->fresh()?->status);
        $this->assertSame(PeriodStatus::Open, $p2022->fresh()?->status);

        // Dialog warnt bei Positionen außerhalb der Periode.
        $this->actingAs($admin)->get(route('finance.resale.periods.link.create', $p2022->sqid))->assertOk()->assertSee(__('resale.link_ui.mismatch_hint'));
    }

    public function test_fits_uses_service_period_or_invoice_date_window(): void {
        $contract = $this->contract('ent-2', 1, '2024-10-13', '2025-10-12');
        $period = $contract->periods()->firstOrFail();
        $mirror = app(\App\Services\Reselling\Mirror\InvoiceMirror::class);
        $mirrored = function (LexofficeVoucherLine $line) use ($mirror): \App\Services\Reselling\Mirror\MirrorLine {
            return $mirror->lineById($this->organization, $line->getMorphClass(), $line->id) ?? throw new \RuntimeException('Position nicht im Spiegel');
        };
        $this->assertTrue(LinkAudit::fits($mirrored($this->line('RE/1', '2025-08-01', 12, '2024-10-18', '2025-10-17')), $period), 'Leistungszeitraum überschneidet die Periode');
        $this->assertFalse(LinkAudit::fits($mirrored($this->line('RE/2', '2025-10-20', 12, '2025-10-18', '2026-10-17')), $period), 'Leistung erst nach Periodenende');
        $this->assertTrue(LinkAudit::fits($mirrored($this->line('RE/3', '2026-06-01', 12, null, null)), $period), 'Nachberechnung binnen zwei Jahren ohne Leistungszeitraum');
        $this->assertFalse(LinkAudit::fits($mirrored($this->line('RE/4', '2024-01-01', 12, null, null)), $period), 'mehr als 90 Tage vor Beginn');
    }
}
