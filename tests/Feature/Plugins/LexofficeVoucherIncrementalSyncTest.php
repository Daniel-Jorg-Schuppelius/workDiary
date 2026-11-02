<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeVoucherIncrementalSyncTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\LexofficeVoucher;
use App\Plugins\Lexoffice\Services\LexofficeVoucherSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Leistungsanalyse 2026-11-02 (perf-05/perf-06): Nach dem ersten Lauf liest der
 * stündliche Sync nur noch eine Belegliste mit Änderungsfilter und ordnet die
 * Besitzer über die Kontaktkarte zu; Zahlungsdaten ohne Ergebnis werden erst
 * nach Frist erneut gefragt.
 */
final class LexofficeVoucherIncrementalSyncTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->pluginSecret('lexoffice', ['api_key' => 'test-key'], $this->organization->id);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'contact-1', 'referenceable_type' => $this->customer->getMorphClass(), 'referenceable_id' => $this->customer->getKey(),
        ]);
    }

    public function test_second_run_is_incremental_and_resolves_owners_via_contact_map(): void {
        $item = fn (string $id, string $number): array => ['id' => $id, 'contactId' => 'contact-1', 'voucherType' => 'salesinvoice', 'voucherStatus' => 'open', 'voucherNumber' => $number, 'voucherDate' => '2026-05-01T00:00:00.000+02:00', 'totalAmount' => 10.0, 'currency' => 'EUR'];
        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/voucherlist*' => [
                FakePluginHttp::response(['content' => [$item('v1', 'A')], 'totalPages' => 1]),
                FakePluginHttp::response(['content' => [], 'totalPages' => 1]),
                FakePluginHttp::response(['content' => [$item('v2', 'B')], 'totalPages' => 1]),
            ],
            '*' => [FakePluginHttp::response(['content' => [], 'totalPages' => 1])],
        ]);

        $first = (new LexofficeVoucherSync($this->organization->id))->sync($this->organization);
        $this->assertSame('full', $first['mode']);
        $this->assertSame(1, $first['created']);

        $second = (new LexofficeVoucherSync($this->organization->id))->sync($this->organization);
        $this->assertSame('incremental', $second['mode']);
        $this->assertSame(1, $second['created']);
        $this->assertSame(0, $second['archived'], 'der inkrementelle Lauf archiviert nicht');
        $this->assertDatabaseHas('lexoffice_vouchers', ['external_id' => 'v2', 'customer_id' => $this->customer->id, 'contact_external_id' => 'contact-1']);

        $calls = array_values(array_filter($fake->recorded(), static fn (array $entry): bool => str_contains((string) $entry['request']->getUri(), 'voucherlist')));
        $last = (string) end($calls)['request']->getUri();
        $this->assertStringContainsString('updatedDateFrom=', $last, 'inkrementell mit Änderungsfilter');
        $this->assertStringNotContainsString('contactId=', $last, 'eine Liste für alle Kontakte');
    }

    public function test_full_run_can_be_forced(): void {
        FakePluginHttp::fake(['*' => [FakePluginHttp::response(['content' => [], 'totalPages' => 1])]]);
        LexofficeVoucher::query()->create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-old', 'voucher_type' => 'salesinvoice', 'voucher_status' => 'open',
            'contact_external_id' => 'contact-1', 'customer_id' => $this->customer->id, 'synced_at' => now(),
        ]);

        $result = (new LexofficeVoucherSync($this->organization->id))->sync($this->organization, full: true);

        $this->assertSame('full', $result['mode']);
    }

    public function test_paid_date_lookup_marks_vouchers_without_a_result(): void {
        $fake = FakePluginHttp::fake(['*' => [FakePluginHttp::response(['paidDate' => null])]]);
        $voucher = LexofficeVoucher::query()->create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-paid', 'voucher_type' => 'salesinvoice', 'voucher_status' => 'paid',
            'voucher_date' => '2026-04-01', 'synced_at' => now(),
        ]);

        $sync = new LexofficeVoucherSync($this->organization->id);
        $this->assertSame(0, $sync->enrichPaidDates($this->organization->id));
        $this->assertNotNull($voucher->fresh()?->paid_date_checked_at);

        $before = count($fake->recorded());
        $sync->enrichPaidDates($this->organization->id);
        $this->assertSame($before, count($fake->recorded()), 'innerhalb der Frist keine zweite Anfrage');

        $voucher->forceFill(['paid_date_checked_at' => now()->subDays(8)])->save();
        $sync->enrichPaidDates($this->organization->id);
        $this->assertGreaterThan($before, count($fake->recorded()), 'nach der Frist erneut gefragt');
    }
}
