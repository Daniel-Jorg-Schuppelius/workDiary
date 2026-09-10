<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeRepairResaleLinksCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Enums\Reselling\LinkOrigin;
use App\Models\{LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine};
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink, ResaleSubscription};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * `lexoffice:repair-resale-links` (Feature 152, Review 2026-09-10 B1): verwaiste
 * Rechnungsbezüge über Rechnungsnummer → Lizenzposition umhängen; was nicht
 * eindeutig ist, bleibt gelistet und unangetastet.
 */
class LexofficeRepairResaleLinksCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private LexofficeArticle $premium;

    private LexofficeArticle $exchange;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->premium = $this->article('art-bp', 'Microsoft 365 Business Premium');
        $this->exchange = $this->article('art-exo', 'Exchange Online (Plan 1)');
    }

    private function article(string $externalId, string $name): LexofficeArticle {
        return LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => $externalId, 'name' => $name, 'article_number' => strtoupper($externalId),
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '20.60', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
    }

    /**
     * @param  list<array{article: LexofficeArticle|null, name: string}>  $lines
     * @return list<LexofficeVoucherLine>
     */
    private function voucher(string $number, array $lines): array {
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-' . $number, 'contact_external_id' => 'c-kl', 'voucher_type' => 'invoice',
            'voucher_status' => 'paid', 'voucher_number' => $number, 'voucher_date' => '2025-10-14', 'total_amount' => 100, 'currency' => 'EUR', 'archived' => false, 'lines_synced_at' => now(),
        ]);
        $created = [];
        foreach ($lines as $position => $line) {
            $created[] = LexofficeVoucherLine::create([
                'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => $position + 1, 'type' => 'service',
                'external_article_id' => $line['article']?->external_id, 'lexoffice_article_id' => $line['article']?->id, 'name' => $line['name'],
                'quantity' => 12, 'unit_name' => 'Monat', 'unit_net' => '20.60', 'total_net' => '247.20', 'tax_rate' => 19, 'currency' => 'EUR',
            ]);
        }

        return $created;
    }

    /** Verwaister Bezug: zeigt auf eine Positions-ID, die es nicht mehr gibt. */
    private function orphanLink(string $voucherNumber, ?int $articleId = null, ?string $note = null): ResalePeriodLink {
        $subscription = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'label' => 'Lizenz ' . $voucherNumber,
            'lexoffice_article_id' => $articleId, 'quantity' => 1, 'starts_on' => '2025-08-05', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR',
        ]);
        $period = ResalePeriod::query()->create([
            'organization_id' => $this->organization->id, 'subscription_id' => $subscription->id, 'starts_on' => '2025-08-05', 'ends_on' => '2026-08-04', 'quantity' => 1, 'currency' => 'EUR', 'status' => 'billed',
        ]);

        return ResalePeriodLink::query()->create([
            'organization_id' => $this->organization->id, 'period_id' => $period->id, 'subscription_id' => $subscription->id,
            'linkable_type' => (new LexofficeVoucherLine)->getMorphClass(), 'linkable_id' => 999_000 + $period->id, 'voucher_number' => $voucherNumber, 'voucher_date' => '2025-10-14',
            'quantity' => 1, 'months' => 12, 'amount' => '247.20', 'currency' => 'EUR', 'origin' => LinkOrigin::Confirmed, 'note' => $note, 'confirmed_at' => now(),
        ]);
    }

    public function test_orphaned_links_are_rehomed_when_unique_and_listed_otherwise(): void {
        [, $premiumLine] = $this->voucher('RE/2025/0820', [
            ['article' => null, 'name' => 'Business Support'],
            ['article' => $this->premium, 'name' => 'Microsoft 365 Business Premium'],
        ]);
        [$exoLine, $bpLine] = $this->voucher('RE/2025/0821', [
            ['article' => $this->exchange, 'name' => 'Exchange Online (Plan 1)'],
            ['article' => $this->premium, 'name' => 'Microsoft 365 Business Premium'],
        ]);
        $unique = $this->orphanLink('RE/2025/0820');
        $byArticle = $this->orphanLink('RE/2025/0821', $this->exchange->id);
        $byNote = $this->orphanLink('RE/2025/0821', null, 'Bezug auf Microsoft 365 Business Premium laut Abgleich');
        $ambiguous = $this->orphanLink('RE/2025/0821');
        $missing = $this->orphanLink('RE/2024/0001');
        $intact = $this->orphanLink('RE/2025/0820');
        $intact->forceFill(['linkable_id' => $premiumLine->id])->save();

        // Probelauf: alles nur gelistet, nichts geschrieben.
        $this->artisan('lexoffice:repair-resale-links', ['--organization' => (string) $this->organization->id, '--dry-run' => true])
            ->expectsOutputToContain('5 verwaiste Rechnungsbezüge')
            ->expectsOutputToContain('würde umgehängt: Bezug #' . $unique->id)
            ->expectsOutputToContain('nicht auflösbar: Bezug #' . $ambiguous->id)
            ->expectsOutputToContain('nicht auflösbar: Bezug #' . $missing->id)
            ->expectsOutputToContain('Probelauf: 3 umgehängt, 2 nicht auflösbar.')
            ->assertSuccessful();
        $this->assertSame(999_000 + $unique->period_id, (int) $unique->fresh()?->linkable_id);

        $this->artisan('lexoffice:repair-resale-links', ['--organization' => (string) $this->organization->id])
            ->expectsOutputToContain('Reparatur: 3 umgehängt, 2 nicht auflösbar.')
            ->assertSuccessful();

        $this->assertSame($premiumLine->id, (int) $unique->fresh()?->linkable_id, 'einzige Lizenzposition (Support zählt nicht)');
        $this->assertSame($exoLine->id, (int) $byArticle->fresh()?->linkable_id, 'Artikel des Abos entscheidet');
        $this->assertSame($bpLine->id, (int) $byNote->fresh()?->linkable_id, 'Positionsname in der Bemerkung entscheidet');
        $this->assertSame(999_000 + $ambiguous->period_id, (int) $ambiguous->fresh()?->linkable_id, 'zwei Lizenzpositionen ohne Anhaltspunkt bleiben stehen');
        $this->assertSame(999_000 + $missing->period_id, (int) $missing->fresh()?->linkable_id, 'ohne Rechnung im Spiegel nichts löschen');
        $this->assertSame($premiumLine->id, (int) $intact->fresh()?->linkable_id, 'gültige Bezüge bleiben unberührt');
        $this->assertInstanceOf(LexofficeVoucherLine::class, $unique->fresh()?->linkable);
        $this->assertSame(6, ResalePeriodLink::query()->count(), 'kein Bezug gelöscht');

        // Zweiter Lauf: nur noch die beiden Unauflösbaren.
        $this->artisan('lexoffice:repair-resale-links', ['--organization' => (string) $this->organization->id])
            ->expectsOutputToContain('2 verwaiste Rechnungsbezüge')
            ->expectsOutputToContain('Reparatur: 0 umgehängt, 2 nicht auflösbar.')
            ->assertSuccessful();
    }

    public function test_duplicate_target_at_the_same_period_is_not_rehomed(): void {
        [$line] = $this->voucher('RE/2025/0820', [['article' => $this->premium, 'name' => 'Microsoft 365 Business Premium']]);
        $orphan = $this->orphanLink('RE/2025/0820');
        // Dieselbe Periode hat die Position inzwischen (z. B. per Vorschlagslauf) erneut verknüpft.
        ResalePeriodLink::query()->create([
            'organization_id' => $this->organization->id, 'period_id' => $orphan->period_id, 'subscription_id' => $orphan->subscription_id,
            'linkable_type' => $line->getMorphClass(), 'linkable_id' => $line->id, 'voucher_number' => 'RE/2025/0820', 'voucher_date' => '2025-10-14',
            'quantity' => 1, 'months' => 12, 'amount' => '247.20', 'currency' => 'EUR', 'origin' => LinkOrigin::Proposed,
        ]);

        $this->artisan('lexoffice:repair-resale-links', ['--organization' => (string) $this->organization->id])
            ->expectsOutputToContain('Duplikat')
            ->expectsOutputToContain('Reparatur: 0 umgehängt, 1 nicht auflösbar.')
            ->assertSuccessful();
        $this->assertSame(999_000 + $orphan->period_id, (int) $orphan->fresh()?->linkable_id);
        $this->assertSame(2, ResalePeriodLink::query()->count());
    }

    public function test_organization_without_orphans_is_silent(): void {
        $this->voucher('RE/2025/0820', [['article' => $this->premium, 'name' => 'Microsoft 365 Business Premium']]);

        $this->artisan('lexoffice:repair-resale-links', ['--organization' => (string) $this->organization->id])
            ->expectsOutputToContain('Reparatur: 0 umgehängt, 0 nicht auflösbar.')
            ->assertSuccessful();
    }
}
