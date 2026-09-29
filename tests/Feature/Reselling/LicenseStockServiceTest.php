<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseStockServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\{LicenseAssignmentEnd, LicenseUnitStatus};
use App\Models\Audit\AuditLog;
use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Platform\User;
use App\Models\Reselling\{ResaleLicenseAssignment, ResaleLicenseBatch, ResaleLicenseKey, ResaleLicenseProduct, ResaleLicenseUnit};
use App\Services\Reselling\License\{LicenseStockException, LicenseStockService};
use App\Services\Stammdaten\CustomerMergeService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1024: Bestandsinvarianten des Lizenzbestands (Abnahmefälle A1–A6, A8–A10, A12, A13). */
final class LicenseStockServiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private LicenseStockService $stock;

    private User $actor;

    private ResaleLicenseProduct $product;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->stock = app(LicenseStockService::class);
        $this->actor = $this->orgAdmin();
        $this->product = $this->stock->saveProduct($this->organization, null, [
            'name' => 'LANCOM Advanced VPN', 'manufacturer' => 'LANCOM', 'key_labels' => ['Seriennummer', 'Aktivierungsschlüssel'], 'reorder_level' => 3,
        ], $this->actor);
    }

    private function batch(string $reference = 'P-2026-01', int $quantity = 10, string $purchasedOn = '2026-09-01'): ResaleLicenseBatch {
        return $this->stock->createBatch($this->product, ['reference' => $reference, 'purchased_on' => $purchasedOn, 'quantity' => $quantity], null, $this->actor);
    }

    private function unit(ResaleLicenseBatch $batch, int $position): ResaleLicenseUnit {
        return $batch->units()->where('position', $position)->sole();
    }

    private function fill(ResaleLicenseBatch $batch, int $from, int $to, string $prefix = 'A'): void {
        for ($position = $from; $position <= $to; $position++) {
            $this->stock->saveKeys($this->unit($batch, $position), ['key_1' => "{$prefix}-SN-{$position}", 'key_2' => "{$prefix}-AK-{$position}"], [], null, $this->actor);
        }
    }

    /** @return array{purchased: int, available: int, sold: int, incomplete: int, blocked: int, reorder: bool, sold_out: bool} */
    private function totals(): array {
        return $this->stock->productStock($this->organization, collect([$this->product->fresh()]))[$this->product->id];
    }

    private function sell(ResaleLicenseBatch $batch, int $position, Customer $customer, ?string $token = null): ResaleLicenseAssignment {
        return $this->stock->sell($this->unit($batch, $position), $customer, null, CarbonImmutable::parse('2026-09-20'), 'RE-1001', $token, $this->actor);
    }

    public function test_a_batch_counts_licenses_not_keys_and_sells_whole_key_sets(): void {
        // A1: zehn Lizenzen mit je zwei Pflichtschlüsseln — zehn, nicht zwanzig.
        $batch = $this->batch();
        $this->assertSame(10, $batch->units()->count());
        $this->assertSame(['purchased' => 10, 'available' => 0, 'sold' => 0, 'incomplete' => 10, 'blocked' => 0], array_slice($this->totals(), 0, 5));

        // A2: neun vollständige Paare, einmal nur Schlüssel 1 — der Verkauf der Lücke scheitert.
        $this->fill($batch, 1, 9);
        $this->stock->saveKeys($this->unit($batch, 10), ['key_1' => 'A-SN-10'], [], null, $this->actor);
        $this->assertSame(9, $this->totals()['available']);
        $this->assertSame(1, $this->totals()['incomplete']);
        $customerA = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->assertSame(LicenseUnitStatus::Incomplete, $this->unit($batch, 10)->status());
        $this->assertThrows(fn () => $this->sell($batch, 10, $customerA), LicenseStockException::class);

        // A3: Lücke schließen, drei Lizenzen an zwei Kunden — zehn gekauft, drei verkauft, sieben verfügbar.
        $this->stock->saveKeys($this->unit($batch, 10), ['key_2' => 'A-AK-10'], [], null, $this->actor);
        $customerB = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->sell($batch, 1, $customerA);
        $this->sell($batch, 2, $customerA);
        $this->sell($batch, 3, $customerB);
        $totals = $this->totals();
        $this->assertSame([10, 7, 3, 0, 0], [$totals['purchased'], $totals['available'], $totals['sold'], $totals['incomplete'], $totals['blocked']]);
        $this->assertFalse($totals['reorder']);
        $revealed = $this->stock->revealKeys($this->unit($batch, 3));
        $this->assertSame(['A-SN-3', 'A-AK-3'], array_column($revealed, 'value'));
        $this->assertSame(['Seriennummer', 'Aktivierungsschlüssel'], array_column($revealed, 'label'));
        $this->assertSame($customerB->id, $this->unit($batch, 3)->activeAssignment?->customer_id);

        // A4: vier weitere Verkäufe → drei verfügbar, „Nachbestellen" genau an der Grenze.
        foreach ([4, 5, 6] as $position) {
            $this->sell($batch, $position, $customerB);
            $this->assertFalse($this->totals()['reorder']);
        }
        $this->sell($batch, 7, $customerB);
        $this->assertSame(3, $this->totals()['available']);
        $this->assertTrue($this->totals()['reorder']);

        // A5: zweites Paket mit zehn vollständigen Lizenzen — zwanzig gekauft, dreizehn verfügbar.
        $second = $this->batch('P-2026-02', 10, '2026-09-15');
        $this->fill($second, 1, 10, 'B');
        $totals = $this->totals();
        $this->assertSame([20, 13, 7], [$totals['purchased'], $totals['available'], $totals['sold']]);
        $counts = $this->stock->batchCounts($this->organization);
        $this->assertSame(3, $counts[$batch->id]['available']);
        $this->assertSame(10, $counts[$second->id]['available']);
        // Vorschlag im Verkaufsdialog: älteste verfügbare Lizenz.
        $this->assertSame([$batch->id, 8], [$this->stock->nextAvailable($this->product)?->batch_id, $this->stock->nextAvailable($this->product)?->position]);
    }

    public function test_reorder_level_empty_disables_and_zero_signals_sold_out(): void {
        // A6
        $batch = $this->batch('P-1', 1);
        $this->fill($batch, 1, 1);
        $this->sell($batch, 1, Customer::factory()->create(['organization_id' => $this->organization->id]));
        $this->assertTrue($this->totals()['reorder']);

        $this->product->forceFill(['reorder_level' => null])->save();
        $this->assertFalse($this->totals()['reorder']);
        $this->assertTrue($this->totals()['sold_out']);
        $this->product->forceFill(['reorder_level' => 0])->save();
        $this->assertTrue($this->totals()['reorder']);
    }

    public function test_duplicates_repeats_and_retries_never_create_second_sales(): void {
        $batch = $this->batch('P-1', 3);
        $this->fill($batch, 1, 2);
        // Dublette über Pakete und Lizenzen hinweg — Meldung ohne Schlüsselwert.
        try {
            $this->stock->saveKeys($this->unit($batch, 3), ['key_1' => 'A-SN-1'], [], null, $this->actor);
            $this->fail('Dublette nicht erkannt.');
        } catch (LicenseStockException $e) {
            $this->assertSame('license_keys.key_1', $e->field);
            $this->assertStringNotContainsString('A-SN-1', $e->getMessage());
        }
        // Ändern braucht einen Grund, gleicher Wert ist keine Änderung.
        $this->assertSame(0, $this->stock->saveKeys($this->unit($batch, 1), ['key_1' => ' A-SN-1 '], [], null, $this->actor));
        $this->assertThrows(fn () => $this->stock->saveKeys($this->unit($batch, 1), ['key_1' => 'NEU'], [], null, $this->actor), LicenseStockException::class);
        $this->assertSame(1, $this->stock->saveKeys($this->unit($batch, 1), ['key_1' => 'NEU'], [], 'Tippfehler', $this->actor));

        // A7 (Dienstebene): derselbe Token liefert denselben Verkauf, zweiter Verkauf derselben Lizenz scheitert.
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $first = $this->sell($batch, 1, $customer, str_repeat('t', 32));
        $again = $this->sell($batch, 1, $customer, str_repeat('t', 32));
        $this->assertSame($first->id, $again->id);
        $this->assertThrows(fn () => $this->sell($batch, 1, $customer, str_repeat('u', 32)), LicenseStockException::class);
        $this->assertSame(1, ResaleLicenseAssignment::query()->count());
        // Das Netz darunter: eine zweite aktive Zuordnung scheitert am Index.
        $this->expectException(QueryException::class);
        DB::table('resale_license_assignments')->insert([
            'organization_id' => $this->organization->id, 'unit_id' => $first->unit_id, 'active_unit_id' => $first->unit_id,
            'customer_id' => $customer->id, 'sold_on' => '2026-09-20', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_csv_import_fills_positions_idempotently_and_rejects_conflicts_as_a_whole(): void {
        // A8
        $batch = $this->batch('P-1', 3);
        $csv = "position;key_1;Aktivierungsschlüssel\n1;SN-1;AK-1\n2;SN-2;\n";
        $preview = $this->stock->previewImport($batch, $csv, $this->actor);
        $this->assertSame([], $preview['errors']);
        $this->assertSame(3, $preview['changes']);
        $this->assertSame([true, false], array_column($preview['rows'], 'complete'));
        $this->assertSame(3, $this->stock->confirmImport($batch, (string) $preview['token'], $this->actor));
        $this->assertThrows(fn () => $this->stock->confirmImport($batch, (string) $preview['token'], $this->actor), LicenseStockException::class);
        $this->assertSame(1, $this->stock->batchCounts($this->organization)[$batch->id]['available']);

        // Identische Wiederholung ändert nichts.
        $repeat = $this->stock->previewImport($batch, $csv, $this->actor);
        $this->assertSame([[], 0, null], [$repeat['errors'], $repeat['changes'], $repeat['token']]);

        // Widerspruch, Dublette in der Datei und fremde Position: nichts wird übernommen.
        $bad = $this->stock->previewImport($batch, "position;key_1;key_2\n1;ANDERS;AK-1\n3;DOPPELT;X\n3;Y;Z\n9;A;B\n2;;DOPPELT-2\n", $this->actor);
        $this->assertNull($bad['token']);
        $this->assertEqualsCanonicalizing([2, 4, 5], array_column($bad['errors'], 'line'));
        $this->assertSame(3, ResaleLicenseKey::query()->count());
        foreach ($bad['errors'] as $error) {
            $this->assertStringNotContainsString('ANDERS', $error['message']);
        }
        $this->assertSame([1], array_column($this->stock->previewImport($batch, "position;key_9\n1;x\n", $this->actor)['errors'], 'line'));
        $this->assertStringContainsString('position;key_1;key_2', $this->stock->importTemplate($batch));
    }

    public function test_returns_block_and_corrections_keep_the_history(): void {
        $batch = $this->batch('P-1', 2);
        $this->fill($batch, 1, 2);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = ForeignCustomer::query()->create(['organization_id' => $this->organization->id, 'customer_id' => $other->id, 'name' => 'Endkunde GmbH']);

        // A10: falscher Kunde — die Lizenz wird nie frei, verkauft bleibt verkauft.
        $sale = $this->sell($batch, 1, $customer);
        $this->assertThrows(fn () => $this->stock->reassign($sale, $customer, $foreign, 'falsch', $this->actor), LicenseStockException::class);
        $corrected = $this->stock->reassign($sale, $other, $foreign, 'Falscher Kunde erfasst', $this->actor);
        $this->assertSame(1, $this->totals()['sold']);
        $this->assertSame(LicenseAssignmentEnd::Corrected, $sale->fresh()?->end_kind);
        $this->assertSame([$customer->id, $other->id], $this->unit($batch, 1)->assignments()->orderBy('id')->pluck('customer_id')->all());
        $this->assertSame('Endkunde GmbH', $corrected->holderLabel());

        // A9: Rücknahme sperrt die Lizenz; freigeben nur ausdrücklich mit Grund.
        $this->stock->returnSale($corrected, 'Kunde hat storniert', $this->actor);
        $unit = $this->unit($batch, 1);
        $this->assertSame(LicenseUnitStatus::Blocked, $unit->status());
        $this->assertSame([1, 0, 1], [$this->totals()['available'], $this->totals()['sold'], $this->totals()['blocked']]);
        $this->assertThrows(fn () => $this->stock->unblock($unit, 'geprüft', false, $this->actor), LicenseStockException::class);
        $this->stock->unblock($unit, 'Beim Hersteller nicht aktiviert', true, $this->actor);
        $this->assertSame(2, $this->totals()['available']);
        $this->assertThrows(fn () => $this->stock->block($this->unit($batch, 2), '', $this->actor), LicenseStockException::class);
        $this->assertThrows(fn () => $this->stock->saveKeys($this->unit($batch, 2), [], ['key_1'], null, $this->actor), LicenseStockException::class);

        $events = AuditLog::query()->where('event', 'like', 'resale_license.%')->pluck('event')->all();
        $this->assertContains('resale_license.reassigned', $events);
        $this->assertContains('resale_license.returned', $events);
        $this->assertContains('resale_license.unblocked', $events);
        $this->assertStringNotContainsString('A-SN-1', (string) AuditLog::query()->pluck('changes')->toJson());
    }

    public function test_template_changes_customer_merge_and_deletion_keep_key_sets_stable(): void {
        // A13
        $batch = $this->batch('P-1', 1);
        $this->fill($batch, 1, 1);
        $this->stock->saveProduct($this->organization, $this->product, ['name' => 'LANCOM Advanced VPN', 'key_labels' => ['Seriennummer', 'Aktivierungsschlüssel', 'PIN'], 'reorder_level' => 3], $this->actor);
        $this->assertSame(2, $batch->fresh()?->key_count);
        $this->assertSame(1, $this->totals()['available']);
        $this->assertSame(3, $this->batch('P-2', 1)->key_count);

        $source = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $target = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $sale = $this->sell($batch, 1, $source);
        app(CustomerMergeService::class)->merge($source, $target);
        $this->assertSame($target->id, $sale->fresh()?->customer_id);
        $this->assertSame(1, $this->totals()['sold']);

        $this->expectException(QueryException::class);
        $target->forceDelete();
    }

    public function test_batches_are_only_removable_while_untouched(): void {
        $empty = $this->batch('P-LEER', 2);
        $this->stock->deleteBatch($empty);
        $this->assertNull($empty->fresh());
        $this->assertSame(0, ResaleLicenseUnit::query()->count());

        $used = $this->batch('P-1', 2);
        $this->fill($used, 1, 1);
        $this->assertThrows(fn () => $this->stock->deleteBatch($used), LicenseStockException::class);
        $this->assertThrows(fn () => $this->stock->createBatch($this->product, ['reference' => 'P-X', 'purchased_on' => '2026-09-01', 'quantity' => 0], null, $this->actor), LicenseStockException::class);
    }
}
