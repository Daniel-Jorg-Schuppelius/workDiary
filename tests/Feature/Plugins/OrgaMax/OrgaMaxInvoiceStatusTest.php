<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxInvoiceStatusTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\OrgaMax;

use App\Models\Platform\User;
use App\Plugins\OrgaMax\Enums\OrgaMaxInvoiceStatus;
use App\Plugins\OrgaMax\Models\OrgaMaxInvoice;
use App\Plugins\OrgaMax\Services\OrgaMaxDocumentFeedSource;
use App\Services\Billing\DocumentFeedFilters;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Log};
use Orgamax\Enums\InvoiceState;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Konsolidierungs-Audit 2026-10, vierte Runde: `orgamax_invoices.invoice_status`
 * ist auf das Plugin-Enum mit den SDK-Werten gecastet; Altwerte normalisiert
 * die Datenmigration 2027_03_10_100800 auf „unbekannt“. Leser (Scope „offen“,
 * Belegfluss-CASE) liefern dieselben Zustände wie vor dem Umbau.
 */
class OrgaMaxInvoiceStatusTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    /** @param  array<string, mixed>  $extra */
    private function mirror(OrgaMaxInvoiceStatus $status, array $extra = []): OrgaMaxInvoice {
        return OrgaMaxInvoice::query()->create($extra + [
            'organization_id' => $this->organization->id,
            'external_id' => 'inv-' . $status->value,
            'invoice_number' => 'OM-' . $status->value,
            'invoice_status' => $status,
            'invoice_type' => 'invoice',
            'invoice_date' => '2026-08-10',
            'total_gross' => '119.00',
            'outstanding_amount' => '119.00',
            'currency' => 'EUR',
        ]);
    }

    public function test_storage_values_are_the_sdk_values(): void {
        // Kommt ein SDK-Fall dazu, fällt dieser Test — dann das Plugin-Enum erweitern.
        foreach (InvoiceState::cases() as $state) {
            $this->assertSame($state->value, OrgaMaxInvoiceStatus::fromSdk($state)->value, $state->name);
        }
        $this->assertSame(OrgaMaxInvoiceStatus::Unknown, OrgaMaxInvoiceStatus::fromSdk(null), 'Ohne SDK-Zustand: unbekannt.');
        $this->assertSame(OrgaMaxInvoiceStatus::Unknown, OrgaMaxInvoiceStatus::fromStored('open'), 'Altwert der Datenmigration 2027_01_16.');
        $this->assertSame(OrgaMaxInvoiceStatus::Unknown, OrgaMaxInvoiceStatus::fromStored(null));
        $this->assertSame(OrgaMaxInvoiceStatus::Locked, OrgaMaxInvoiceStatus::fromStored('locked'));
    }

    public function test_open_scope_counts_locked_and_partially_paid_but_not_settled_states(): void {
        foreach (OrgaMaxInvoiceStatus::cases() as $status) {
            $this->mirror($status);
        }

        $open = OrgaMaxInvoice::query()->open()->pluck('external_id')->sort()->values()->all();

        // Unbekannt gilt als offen: lieber ein Wechsel-Blocker zu viel als ein übersehener Beleg.
        $this->assertSame(['inv-locked', 'inv-partiallyPaid', 'inv-unknown'], $open);
        $this->assertSame(6, OrgaMaxInvoice::query()->count());
    }

    public function test_feed_source_maps_states_like_before_the_cast(): void {
        foreach (OrgaMaxInvoiceStatus::cases() as $status) {
            $this->mirror($status);
        }
        $filters = new DocumentFeedFilters(
            organizationId: (int) $this->organization->id,
            userId: (int) $this->admin->id,
            from: CarbonImmutable::parse('2026-01-01')->startOfDay(),
            to: CarbonImmutable::parse('2026-12-31')->endOfDay(),
            sources: ['voucher' => true],
        );

        $builder = (new OrgaMaxDocumentFeedSource)->builder($filters);
        $this->assertNotNull($builder);
        $rows = $builder->get()->keyBy('number');

        // Stand vor dem Umbau: CASE über draft/cancelled/paid, alles andere „open“;
        // Vorzeichen 0 nur für Entwurf und Storno; offener Betrag nur bei „open“.
        $expected = [
            'OM-draft' => ['draft', 0, 0.0],
            'OM-locked' => ['open', 1, 119.0],
            'OM-partiallyPaid' => ['open', 1, 119.0],
            'OM-paid' => ['paid', 1, 0.0],
            'OM-cancelled' => ['cancelled', 0, 0.0],
            'OM-unknown' => ['open', 1, 119.0],
        ];
        $this->assertCount(count($expected), $rows);
        foreach ($expected as $number => [$state, $sign, $openAmount]) {
            $row = $rows->get($number);
            $this->assertNotNull($row, $number);
            $this->assertSame($state, $row->state, $number);
            $this->assertSame($sign, (int) $row->sign, $number);
            $this->assertEqualsWithDelta($openAmount, (float) $row->open_amount, 0.001, $number);
        }
    }

    public function test_labels_are_translated_in_every_locale(): void {
        foreach (['de', 'en', 'fr', 'es', 'it'] as $locale) {
            app()->setLocale($locale);
            foreach (OrgaMaxInvoiceStatus::cases() as $status) {
                $label = $status->label();
                $this->assertNotSame('', $label, $locale . ' ' . $status->value);
                $this->assertStringNotContainsString('orgamax::', $label, $locale . ' ' . $status->value);
                $this->assertStringNotContainsString('invoice_status.', $label, $locale . ' ' . $status->value);
            }
        }

        app()->setLocale('de');
        $this->assertSame('Unbekannt', OrgaMaxInvoiceStatus::Unknown->label());
        $this->assertSame('Teilweise bezahlt', OrgaMaxInvoiceStatus::PartiallyPaid->label());
        app()->setLocale('en');
        $this->assertSame('Unknown', OrgaMaxInvoiceStatus::Unknown->label());
        $this->assertSame('neutral', OrgaMaxInvoiceStatus::Unknown->tone());
        $this->assertSame('success', OrgaMaxInvoiceStatus::Paid->tone());
    }

    public function test_migration_normalizes_legacy_values_and_keeps_known_ones(): void {
        $legacy = ['open', null, '', 'locked', 'paid', 'partiallyPaid'];
        foreach ($legacy as $i => $status) {
            DB::table('orgamax_invoices')->insert([
                'organization_id' => $this->organization->id,
                'external_id' => 'legacy-' . $i,
                'invoice_status' => $status,
                'currency' => 'EUR',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $logged = [];
        Log::shouldReceive('info')->twice()->andReturnUsing(function (string $message, array $context) use (&$logged): void {
            $logged[] = [$message, $context];
        });

        $migration = require app_path('Plugins/OrgaMax/Database/Migrations/2027_03_10_100800_normalize_orgamax_invoice_status.php');
        $migration->up();

        $this->assertSame([
            'legacy-0' => 'unknown',
            'legacy-1' => 'unknown',
            'legacy-2' => 'unknown',
            'legacy-3' => 'locked',
            'legacy-4' => 'paid',
            'legacy-5' => 'partiallyPaid',
        ], DB::table('orgamax_invoices')->orderBy('external_id')->pluck('invoice_status', 'external_id')->all());

        // Jede Zeile lädt jetzt über den Cast.
        $loaded = OrgaMaxInvoice::query()->orderBy('external_id')->get();
        $this->assertCount(6, $loaded);
        $this->assertSame(3, $loaded->filter(fn (OrgaMaxInvoice $invoice): bool => $invoice->invoice_status === OrgaMaxInvoiceStatus::Unknown)->count());

        // Die Altwert-Prüfung (SELECT DISTINCT) steht im Log, mit der Zahl der normalisierten Zeilen;
        // NULL und '' sind getrennte Altwerte, die Reihenfolge ist DB-abhängig.
        $this->assertCount(1, $logged);
        [$message, $context] = $logged[0];
        $this->assertStringContainsString('invoice_status', $message);
        $found = array_map(static fn (?string $value): string => $value ?? '(null)', $context['found']);
        sort($found);
        $this->assertSame(['', '(null)', 'locked', 'open', 'paid', 'partiallyPaid'], $found);
        $this->assertSame(3, $context['normalized']);

        // Zweiter Lauf ändert nichts mehr.
        $migration->up();
        $this->assertSame(3, DB::table('orgamax_invoices')->where('invoice_status', 'unknown')->count());
        $this->assertSame(0, $logged[1][1]['normalized']);
    }
}
