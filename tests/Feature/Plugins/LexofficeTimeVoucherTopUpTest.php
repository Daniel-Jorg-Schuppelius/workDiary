<?php
/*
 * Created on   : Tue Nov 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeTimeVoucherTopUpTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Enums\Finance\{BillingMode, TransferChannel, TransferTarget};
use App\Enums\Project\{ProjectStatus, ShortfallStatus};
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Finance\BillingTransferPosition;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\{PluginSetting, User};
use App\Models\Project\{Project, ProjectTimeShortfall, ProjectTimeTarget};
use App\Models\Time\TimeEntry;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Finance\{BillingPositionBuilder, BillingTransferService};
use App\Services\Invoicing\TargetTopUps;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-1733: Der Lexoffice-Zeitbeleg füllt wie Rechnungslauf und Übergabe auf
 * das Projekt-Soll auf: offene Stunden-Befunde aus `TargetTopUps::openQuery()`,
 * Preis über den `BlockPriceResolver`, Betrag in der Position seines
 * Steuersatzes; der Befund ist danach berechnet (`billed_minutes`,
 * `billed_at`, `external_reference_id` des Belegs). Pauschal-Befunde schließt
 * der Beleg weiter, ohne aufzufüllen (MVP-1716). Vorher fehlte die Auffüllung
 * im Beleg, und der Befund blieb für den nächsten Lauf offen.
 */
final class LexofficeTimeVoucherTopUpTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const array PERIOD = ['from' => '2030-04-01', 'to' => '2030-04-30'];

    private User $accountant;

    private User $anna;

    private Customer $customer;

    private FakePluginHttp $fake;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2030-04-20');
        $this->accountant = $this->orgAdmin(['hourly_rate' => null]);
        $this->actingAs($this->accountant);
        $this->anna = User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Anna Beispiel']);
        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'hourly_rate' => '90.00',
            'currency' => 'EUR',
            'country' => 'DE',
            'billing_mode' => BillingMode::Lexoffice,
        ]);
        $this->connectLexoffice();
    }

    /** Nutzlast: Stunden 600 + Auffüllung 200 + Pauschale 250 = 1.050 netto zu 19 %. */
    public function test_voucher_includes_the_top_up_and_marks_the_finding_billed(): void {
        $hourly = $this->targetProject('Objektreinigung', 'Std.', 100.0);
        $this->entry($hourly, '2030-04-01 08:00', '2030-04-01 14:00');
        $hourlyShortfall = $this->shortfall($hourly, 360);
        $flat = $this->targetProject('Heizungswartung', 'Pauschale', 250.0);
        $this->entry($flat, '2030-04-01 14:00', '2030-04-01 20:00');
        $flatShortfall = $this->shortfall($flat, 360);

        $this->export();

        $body = $this->sentVoucher();
        $this->assertEquals([['amount' => 1050.0, 'taxAmount' => 199.5, 'taxRatePercent' => 19.0]], array_map(
            static fn (array $item): array => ['amount' => (float) $item['amount'], 'taxAmount' => (float) $item['taxAmount'], 'taxRatePercent' => (float) $item['taxRatePercent']],
            $body['voucherItems'],
        ), 'Die Auffüllung (2 h × 100,00) steckt im Betrag des Satzes, die Pauschale wird nicht aufgefüllt.');
        $this->assertEquals(199.5, $body['totalTaxAmount']);
        $this->assertEquals(1249.5, $body['totalGrossAmount']);

        $voucher = ExternalReference::query()->where('external_type', LexofficePlugin::EXT_TYPE_VOUCHER)->sole();
        $hourlyShortfall->refresh();
        $this->assertSame(120, $hourlyShortfall->billed_minutes);
        $this->assertNotNull($hourlyShortfall->billed_at);
        $this->assertSame($voucher->id, $hourlyShortfall->external_reference_id);
        $this->assertSame(ShortfallStatus::Open, $hourlyShortfall->status, 'Die Auffüllung schließt den Befund nicht.');
        $this->assertFalse($hourlyShortfall->isSettledByFlatRate());

        // Gegenprobe Pauschale: geschlossen mit Verweis auf den Beleg, nichts aufgefüllt.
        $flatShortfall->refresh();
        $this->assertSame(ShortfallStatus::Resolved, $flatShortfall->status);
        $this->assertSame($voucher->id, $flatShortfall->external_reference_id);
        $this->assertNull($flatShortfall->billed_minutes);
        $this->assertTrue($flatShortfall->isSettledByFlatRate());

        // Eine Nachbuchung auf den aufgefüllten Tag ist wie nach Rechnung und Übergabe nicht abrechenbar.
        $late = $this->entry($hourly, '2030-04-01 20:00', '2030-04-01 21:00');
        $this->assertFalse((bool) $late->fresh()?->billable);
    }

    /** Ein Tag ohne gebuchte Zeit: Der Beleg entsteht auch nur aus der Auffüllung (8 h × 100,00). */
    public function test_voucher_from_top_ups_alone(): void {
        $hourly = $this->targetProject('Objektreinigung', 'Std.', 100.0);
        $shortfall = $this->shortfall($hourly, 0);

        $result = $this->export();

        $this->assertSame('voucher-time-1', $result['external_id']);
        $body = $this->sentVoucher();
        $this->assertCount(1, $body['voucherItems']);
        $this->assertEquals(800.0, $body['voucherItems'][0]['amount']);
        $this->assertEquals(952.0, $body['totalGrossAmount']);
        $this->assertStringContainsString('Objektreinigung', (string) $body['remark']);
        $this->assertSame(480, $shortfall->refresh()->billed_minutes);
    }

    /** Ein zweiter Lauf über denselben Zeitraum berechnet die Auffüllung nicht noch einmal. */
    public function test_second_export_does_not_bill_the_top_up_again(): void {
        $hourly = $this->targetProject('Objektreinigung', 'Std.', 100.0);
        $this->shortfall($hourly, 0);
        $this->export();

        $second = $this->export();

        $this->assertSame('', $second['external_id']);
        $this->assertTrue((bool) ($second['payload']['skipped'] ?? false));
        $this->assertSame(1, ExternalReference::query()->where('external_type', LexofficePlugin::EXT_TYPE_VOUCHER)->count());
    }

    /**
     * Hält ein Übergabe-Entwurf den Befund schon, nimmt seine Position die
     * Auffüllung des Belegs nicht noch einmal auf, und das Verwerfen gibt sie
     * nicht frei — sie gehört dem Beleg.
     */
    public function test_a_draft_transfer_neither_bills_nor_releases_the_voucher_top_up(): void {
        $hourly = $this->targetProject('Objektreinigung', 'Std.', 100.0);
        $shortfall = $this->shortfall($hourly, 0);
        $service = app(BillingTransferService::class);
        $draft = $service->createDraft($this->customer, TransferChannel::Time, TransferTarget::File, self::PERIOD, null, $this->accountant);

        $this->export();

        $this->assertSame([], app(BillingPositionBuilder::class)->build($draft->refresh())
            ->where('source_kind', BillingTransferPosition::KIND_TARGET)->values()->all());
        $service->void($draft->refresh(), $this->accountant);
        $shortfall->refresh();
        $this->assertSame(480, $shortfall->billed_minutes);
        $this->assertNotNull($shortfall->external_reference_id);
    }

    /** Unter der Sperre neu gelesen: Was inzwischen anderweitig berechnet ist, überschreibt der Beleg nicht. */
    public function test_marking_skips_a_finding_billed_meanwhile(): void {
        $hourly = $this->targetProject('Objektreinigung', 'Std.', 100.0);
        $shortfall = $this->shortfall($hourly, 0);
        $topUps = app(TargetTopUps::class);
        $lines = $topUps->lines($topUps->openQuery($this->customer, self::PERIOD)->get(), $this->customer, $this->organization);
        ProjectTimeShortfall::query()->whereKey($shortfall->id)->update(['billed_minutes' => 480, 'billed_at' => now()]);
        $voucher = ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_VOUCHER,
            'referenceable_type' => $this->customer->getMorphClass(),
            'referenceable_id' => $this->customer->id,
            'external_id' => 'voucher-race',
            'synced_at' => now(),
        ]);

        $this->assertSame([(int) $shortfall->id], $topUps->markBilledByVoucher($lines, $voucher));
        $this->assertNull($shortfall->refresh()->external_reference_id);
    }

    /** @return array{external_id: string, external_type: string, payload?: array<string, mixed>} */
    private function export(): array {
        return app(LexofficePlugin::class)->exportCustomerTime($this->customer, CarbonImmutable::parse('2030-04-01')->startOfDay(), CarbonImmutable::parse('2030-04-30')->endOfDay());
    }

    /** @return array<string, mixed> */
    private function sentVoucher(): array {
        foreach ($this->fake->recorded() as $entry) {
            $request = $entry['request'];
            if ($request->getMethod() === 'POST' && str_contains((string) $request->getUri(), '/vouchers')) {
                return (array) json_decode((string) $request->getBody(), true);
            }
        }
        $this->fail('Kein Beleg an Lexoffice gesendet.');
    }

    private function connectLexoffice(): void {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'test-key'],
        ]);
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'referenceable_type' => $this->customer->getMorphClass(),
            'referenceable_id' => $this->customer->id,
            'external_id' => 'contact-1',
            'synced_at' => now(),
        ]);
        $this->fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/vouchers*' => FakePluginHttp::response([
                'id' => 'voucher-time-1',
                'resourceUri' => 'https://api.lexware.io/v1/vouchers/voucher-time-1',
            ], 201),
        ]);
    }

    private function targetProject(string $name, string $unit, float $price): Project {
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => $name,
            'status' => ProjectStatus::Active->value,
            'hourly_rate' => null,
            'is_default' => false,
            'bills_target_time' => true,
            'billable' => true,
        ]);
        $project->members()->attach([$this->anna->id]);
        // Der 01.04.2030 ist ein Montag.
        ProjectTimeTarget::factory()->create(['project_id' => $project->id, 'weekday' => 1, 'target_minutes' => 480]);
        $project->billingRules()->create([
            'organization_id' => $this->organization->id,
            'applies_to_kind' => null,
            'item_type' => 'service',
            'unit_name' => $unit,
            'net_unit_price' => $price,
        ]);

        return $project;
    }

    private function shortfall(Project $project, int $booked): ProjectTimeShortfall {
        return ProjectTimeShortfall::factory()->create([
            'project_id' => $project->id,
            'user_id' => $this->anna->id,
            'date' => '2030-04-01',
            'target_minutes' => 480,
            'booked_minutes' => $booked,
            'shortfall_minutes' => 480 - $booked,
        ]);
    }

    private function entry(Project $project, string $start, string $end): TimeEntry {
        $from = CarbonImmutable::parse($start);
        $to = CarbonImmutable::parse($end);
        $order = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->anna->id,
            'project_id' => $project->id,
            'customer_id' => $this->customer->id,
            'title' => 'Einsatz Objekt',
        ]);

        return TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'diary_entry_id' => $order->id,
            'user_id' => $this->anna->id,
            'date' => $from->toDateString(),
            'started_at' => $from,
            'ended_at' => $to,
            'minutes' => (int) $from->diffInMinutes($to),
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'exported' => false,
            'description' => 'Reinigung Flure',
        ]);
    }
}
