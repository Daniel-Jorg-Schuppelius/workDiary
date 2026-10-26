<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseInvoiceLinkTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Enums\Finance\{AccountingVoucherState, BillingMode};
use App\Enums\User\Permission;
use App\Models\Customer\Customer;
use App\Models\Finance\AccountingVoucher;
use App\Models\Platform\User;
use App\Models\Reselling\{ResaleLicenseAssignment, ResaleLicenseBatch, ResaleLicenseProduct};
use App\Services\Finance\Accounting\Vouchers\{MirroredVoucherLine, VoucherMirror};
use App\Services\Reselling\License\{LicenseInvoiceLinks, LicenseStockService};
use App\Services\Reselling\Mirror\{AccountingVoucherMirrorSource, InvoiceMirror};
use App\Services\Reselling\Register\ResaleOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * Rechnungsbezug an Stücklizenzen (Feature 185, MVP-1427/1428): Verkauf mit
 * Spiegelposition, Vorschlag im Dialog, Empfängerprüfung, Prüffall „verkauft
 * ohne Rechnung“ in Überblick, Lizenzliste und Abgleich, Nachtragen über
 * „Verkauf berichtigen“ bei gleichem Halter, Kundenportal ohne Schlüssel.
 */
final class LicenseInvoiceLinkTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private LicenseStockService $stock;

    private User $manager;

    private Customer $customer;

    private ResaleLicenseProduct $product;

    private ResaleLicenseBatch $batch;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        config()->set('dataprotection.key', base64_encode(random_bytes(32)));
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->travelTo('2026-10-26');
        $this->stock = app(LicenseStockService::class);
        $this->manager = $this->orgUser();
        $this->manager->givePermissionTo([Permission::ResellingView->value, Permission::ResellingManage->value]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Klimpel Bäder GmbH', 'billing_mode' => BillingMode::SevDesk]);
        $this->product = $this->stock->saveProduct($this->organization, null, ['name' => 'LANCOM Advanced VPN', 'key_labels' => ['Seriennummer'], 'article_ref' => 'sevdesk:LC-AVPN'], $this->manager);
        $this->batch = $this->stock->createBatch($this->product, ['reference' => 'P-2026-01', 'purchased_on' => '2026-09-01', 'quantity' => 3], null, $this->manager);
        foreach ([1, 2, 3] as $position) {
            $this->stock->saveKeys($this->batch->units()->where('position', $position)->sole(), ['key_1' => "GEHEIM-SN-{$position}"], [], null, $this->manager);
        }
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    /** Gespiegelte Rechnung des Kunden mit einer Lizenzposition (Artikel LC-AVPN) und einer fremden Position. */
    private function mirroredInvoice(string $externalId, string $number, string $date, ?Customer $customer = null): AccountingVoucher {
        $voucher = AccountingVoucher::query()->create([
            'organization_id' => $this->organization->id, 'plugin_id' => 'sevdesk', 'external_id' => $externalId, 'customer_id' => ($customer ?? $this->customer)->id,
            'direction' => DocumentDirection::Outgoing->value, 'document_kind' => DocumentKind::Invoice->value, 'voucher_state' => AccountingVoucherState::Open,
            'voucher_number' => $number, 'voucher_date' => $date, 'currency' => 'EUR', 'synced_at' => now(), 'payload' => [],
        ]);
        app(VoucherMirror::class)->storeLines($this->organization->id, 'sevdesk', $externalId, [
            new MirroredVoucherLine(1, 'LANCOM Advanced VPN Option', 1.0, '150.0000', '150.00', 'EUR', 'LC-AVPN'),
            new MirroredVoucherLine(2, 'Wartung Serverraum', 1.0, '90.0000', '90.00'),
        ]);

        return $voucher;
    }

    private function unitSqid(int $position): string {
        return $this->batch->units()->where('position', $position)->sole()->sqid;
    }

    public function test_sale_links_the_mirror_line_and_lists_show_the_voucher(): void {
        $this->mirroredInvoice('invoice:1', 'RE-2026-101', '2026-10-20');
        $this->assertCount(0, app(InvoiceMirror::class)->linesFor($this->organization, [$this->customer->id]), 'Stückprodukt ist kein Abo-Lizenzartikel — der Lizenzfilter des Spiegels greift nicht');
        $lines = app(InvoiceMirror::class)->linesFor($this->organization, [$this->customer->id], null, null, false);
        $this->assertCount(2, $lines, 'alle Positionen der Rechnung');
        $line = $lines->first(static fn ($l) => $l->articleKey === 'sevdesk:LC-AVPN');

        // Dialog: die passende Position steht in den Optionen (Artikel LC-AVPN), fremde Artikel nicht.
        $this->actingAs($this->manager)->get(route('finance.resale.licenses.sell.create', ['product' => $this->product->sqid]))
            ->assertOk()->assertSee('data-lines=', false)->assertSee('RE-2026-101')->assertSee('&quot;match&quot;:true', false);
        $candidates = app(LicenseInvoiceLinks::class)->candidates($this->organization, $this->product, now()->toImmutable());
        $this->assertCount(2, $candidates);
        $this->assertSame([$line?->key, true, false], [$candidates[0]['key'], $candidates[0]['match'], $candidates[1]['match']], 'passende Position zuerst');

        $sale = ['unit_id' => $this->unitSqid(1), 'customer_id' => $this->customer->sqid, 'sold_on' => '2026-10-21', 'line' => $line?->key, 'token' => str_repeat('a', 32)];
        $this->actingAs($this->manager)->post(route('finance.resale.licenses.sell.store'), $sale)->assertRedirect()->assertSessionHas('success');
        $assignment = ResaleLicenseAssignment::query()->sole();
        $this->assertSame([app(AccountingVoucherMirrorSource::class)->morphClass(), $line?->morphId], [$assignment->linkable_type, $assignment->linkable_id]);
        $this->assertSame('RE-2026-101', $assignment->invoice_reference, 'Belegnummer wird zur Referenz');
        $this->assertTrue($assignment->hasInvoice());
        $this->assertSame($line?->key, $assignment->mirrorLine()?->key);

        // Lizenzliste, Paketseite und Kundenakte zeigen die Belegnummer der Position — nie den Schlüssel.
        foreach ([route('finance.resale.licenses.index'), route('finance.resale.licenses.batches.show', $this->batch), route('customers.show', $this->customer)] as $url) {
            $page = $this->actingAs($this->manager)->get($url)->assertOk()->assertSee('RE-2026-101');
            $this->assertStringNotContainsString('GEHEIM', (string) $page->getContent());
        }

        // Unbekannter Schlüssel und Position eines anderen Empfängers werden abgewiesen.
        $this->actingAs($this->manager)->postJson(route('finance.resale.licenses.sell.store'), ['unit_id' => $this->unitSqid(2), 'line' => 'xyz', 'token' => str_repeat('b', 32)] + $sale)
            ->assertUnprocessable()->assertJsonValidationErrors('line');
        $other = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Andere GmbH']);
        $this->mirroredInvoice('invoice:2', 'RE-2026-102', '2026-10-22', $other);
        $foreignLine = app(InvoiceMirror::class)->linesFor($this->organization, [$other->id], null, null, false)->first(static fn ($l) => $l->articleKey === 'sevdesk:LC-AVPN');
        $this->assertNotNull($foreignLine);
        $this->actingAs($this->manager)->postJson(route('finance.resale.licenses.sell.store'), ['unit_id' => $this->unitSqid(2), 'line' => $foreignLine?->key, 'token' => str_repeat('c', 32)] + $sale)
            ->assertUnprocessable()->assertJsonValidationErrors('line');
        $this->assertSame(1, ResaleLicenseAssignment::query()->count());
    }

    public function test_sales_without_invoice_become_a_check_case_after_thirty_days_and_can_be_completed_by_correction(): void {
        $this->actingAs($this->manager);
        $this->post(route('finance.resale.licenses.sell.store'), ['unit_id' => $this->unitSqid(1), 'customer_id' => $this->customer->sqid, 'sold_on' => '2026-09-10', 'token' => str_repeat('a', 32)])->assertRedirect();
        $this->post(route('finance.resale.licenses.sell.store'), ['unit_id' => $this->unitSqid(2), 'customer_id' => $this->customer->sqid, 'sold_on' => '2026-10-20', 'token' => str_repeat('b', 32)])->assertRedirect();
        $this->post(route('finance.resale.licenses.sell.store'), ['unit_id' => $this->unitSqid(3), 'customer_id' => $this->customer->sqid, 'sold_on' => '2026-09-10', 'invoice_reference' => 'RE-alt', 'token' => str_repeat('c', 32)])->assertRedirect();
        $old = ResaleLicenseAssignment::query()->where('unit_id', $this->batch->units()->where('position', 1)->sole()->id)->sole();

        $links = app(LicenseInvoiceLinks::class);
        $this->assertSame([$old->id], $links->unbilled($this->organization)->pluck('id')->all(), 'nur der Verkauf vom 10.09. ohne Referenz');
        $this->assertTrue(LicenseInvoiceLinks::isOverdue($old));
        $task = collect(app(ResaleOverview::class)->build($this->organization)['tasks'])->firstWhere('key', 'unbilled_licenses');
        $this->assertSame(1, $task['count'] ?? null);
        $this->get(route('finance.resale.index'))->assertOk()->assertSee(__('resale.overview.task.unbilled_licenses'));
        $this->get(route('finance.resale.licenses.index', ['unbilled' => 1]))->assertOk()
            ->assertSee($old->unit->label())->assertDontSee($this->batch->units()->where('position', 2)->sole()->label());
        $this->get(route('finance.resale.reconcile.show', $this->customer))->assertOk()->assertSee(__('resale.reconcile.licenses.title'));

        // Nachtragen über „Verkauf berichtigen“: gleicher Halter, aber neue Position — kein „gleicher Halter“-Fehler.
        $this->mirroredInvoice('invoice:1', 'RE-2026-099', '2026-09-12');
        $line = app(InvoiceMirror::class)->linesFor($this->organization, [$this->customer->id], null, null, false)->first(static fn ($l) => $l->articleKey === 'sevdesk:LC-AVPN');
        $this->assertNotNull($line);
        $this->get(route('finance.resale.licenses.assignments.reassign.create', $old))->assertOk()->assertSee('RE-2026-099');
        $this->postJson(route('finance.resale.licenses.assignments.reassign.store', $old), ['customer_id' => $this->customer->sqid, 'reason' => 'Rechnung nachgetragen'])
            ->assertUnprocessable()->assertJsonValidationErrors('customer_id');
        $this->post(route('finance.resale.licenses.assignments.reassign.store', $old), ['customer_id' => $this->customer->sqid, 'line' => $line?->key, 'reason' => 'Rechnung nachgetragen'])
            ->assertRedirect()->assertSessionHas('success');
        $next = $old->unit->fresh()?->activeAssignment;
        $this->assertNotSame($old->id, $next?->id);
        $this->assertSame([$line?->morphId, 'RE-2026-099'], [$next?->linkable_id, $next?->invoice_reference]);
        $this->assertSame('2026-09-10', $next?->sold_on->toDateString(), 'Verkaufsdatum bleibt');
        $this->assertSame(0, $links->unbilledCount($this->organization));
        $this->assertNull(collect(app(ResaleOverview::class)->build($this->organization)['tasks'])->firstWhere('key', 'unbilled_licenses'));

        // Berichtigen auf einen anderen Halter übernimmt den Bezug.
        $other = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Andere GmbH']);
        $this->post(route('finance.resale.licenses.assignments.reassign.store', $next), ['customer_id' => $other->sqid, 'reason' => 'Verwechselt'])->assertRedirect()->assertSessionHas('success');
        $moved = $old->unit->fresh()?->activeAssignment;
        $this->assertSame([$other->id, $line?->morphId], [$moved?->customer_id, $moved?->linkable_id]);
    }

    public function test_portal_shows_sold_licenses_with_invoice_number_but_never_keys(): void {
        $this->allowPortal($this->customer);
        $portalUser = User::factory()->kunde((int) $this->customer->id, (int) $this->organization->id)->create(['organization_id' => $this->organization->id]);
        $this->mirroredInvoice('invoice:1', 'RE-2026-101', '2026-10-20');
        $line = app(InvoiceMirror::class)->linesFor($this->organization, [$this->customer->id], null, null, false)->first(static fn ($l) => $l->articleKey === 'sevdesk:LC-AVPN');
        $this->actingAs($this->manager)->post(route('finance.resale.licenses.sell.store'), ['unit_id' => $this->unitSqid(1), 'customer_id' => $this->customer->sqid, 'sold_on' => '2026-10-21', 'line' => $line?->key, 'token' => str_repeat('a', 32)])->assertRedirect();
        $this->actingAs($this->manager)->post(route('finance.resale.licenses.sell.store'), ['unit_id' => $this->unitSqid(2), 'customer_id' => $this->customer->sqid, 'sold_on' => '2026-10-22', 'token' => str_repeat('b', 32)])->assertRedirect();
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Geheimkunde GmbH']);
        $this->actingAs($this->manager)->post(route('finance.resale.licenses.sell.store'), ['unit_id' => $this->unitSqid(3), 'customer_id' => $otherCustomer->sqid, 'sold_on' => '2026-10-22', 'token' => str_repeat('c', 32)])->assertRedirect();

        $this->flushSession(); // sonst steht die Erfolgsmeldung des letzten Verkaufs (mit Lizenz #3) in der Portalseite
        $page = $this->actingAs($portalUser, 'customer')->get(route('customer.subscriptions.index'))->assertOk()
            ->assertSee(__('resale_portal.licenses.title'))->assertSee('RE-2026-101')->assertSee(__('resale_portal.licenses.no_invoice'))
            ->assertSee($this->batch->units()->where('position', 1)->sole()->label())
            ->assertDontSee($this->batch->units()->where('position', 3)->sole()->label());
        $this->assertStringNotContainsString('GEHEIM', (string) $page->getContent());
    }
}
