<?php
/*
 * Created on   : Tue Oct 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleUnlinkedLinesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Reselling\ResaleIgnoredLine;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\{LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine};
use App\Services\Reselling\Mirror\InvoiceMirror;
use App\Services\Reselling\Register\ResaleOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Lizenzpositionen ohne Abo (Phase 163, MVP-1432): Aufgabe im Überblick,
 * Aktionen „Abo anlegen“ und „ignorieren“, ignorierte Positionen zurückholbar.
 */
class ResaleUnlinkedLinesTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-10-27');
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    public function test_unlinked_line_is_a_task_until_ignored_and_comes_back_on_restore(): void {
        $admin = $this->orgAdmin();
        $exchange = LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-exo', 'name' => 'Exchange Online (Plan 1)', 'article_number' => 'ART-EXO',
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '3.95', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Ute Mayershofer']);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-ute', 'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->getKey(),
        ]);
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-0365', 'contact_external_id' => 'c-ute', 'voucher_type' => 'invoice',
            'voucher_status' => 'paid', 'voucher_number' => 'RE/2022/0365', 'voucher_date' => '2022-04-22', 'total_amount' => 112.81, 'currency' => 'EUR', 'archived' => false, 'lines_synced_at' => now(),
        ]);
        $line = LexofficeVoucherLine::create([
            'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => 2, 'type' => 'service',
            'external_article_id' => 'art-exo', 'lexoffice_article_id' => $exchange->id, 'name' => 'Exchange Online (Plan 1)',
            'quantity' => 24, 'unit_name' => 'Monat', 'unit_net' => '3.95', 'total_net' => '94.80', 'tax_rate' => 19, 'currency' => 'EUR',
        ]);
        $mirror = app(InvoiceMirror::class);
        $this->assertSame(1, $mirror->unlinkedLines($this->organization, 10)['total']);
        $key = $mirror->lineById($this->organization, $line->getMorphClass(), $line->id)?->key;
        $this->assertNotNull($key);

        // Überblick: Aufgabe in der Gruppe „Zuordnen"; Periodenseite mit „Abo anlegen" und „Ignorieren".
        $overview = app(ResaleOverview::class)->build($this->organization);
        $byKey = array_column($overview['tasks'], null, 'key');
        $this->assertSame(1, $byKey['unlinked']['count']);
        $this->assertSame('zuordnen', $byKey['unlinked']['group']);
        $this->actingAs($admin)->get(route('finance.resale.periods.index', ['unlinked' => 1]))->assertOk()
            ->assertSee('RE/2022/0365')
            ->assertSee(route('finance.resale.create', ['line' => $key]), false)
            ->assertSee(route('finance.resale.periods.unlinked.ignore'), false);

        // Ignorieren: aus der Liste und dem Überblick verschwunden, als ignoriert gelistet, Audit vorhanden.
        $this->actingAs($admin)->post(route('finance.resale.periods.unlinked.ignore'), ['key' => $key, 'note' => 'Historie 2022'])->assertRedirect()->assertSessionHas('success');
        $ignored = ResaleIgnoredLine::query()->sole();
        $this->assertSame($line->getMorphClass(), $ignored->linkable_type);
        $this->assertSame($line->id, (int) $ignored->linkable_id);
        $this->assertSame('Historie 2022', $ignored->note);
        $this->assertSame(0, $mirror->unlinkedLines($this->organization, 10)['total']);
        $this->assertArrayNotHasKey('unlinked', array_column(app(ResaleOverview::class)->build($this->organization)['tasks'], null, 'key'));
        $this->actingAs($admin)->get(route('finance.resale.periods.index'))->assertOk()
            ->assertSee(trans_choice('resale.unlinked.ignored_title', 1, ['count' => 1]))
            ->assertSee('Historie 2022')
            ->assertSee(route('finance.resale.periods.unlinked.restore', $ignored->sqid), false);
        // Doppelt ignorieren legt keine zweite Zeile an.
        $this->actingAs($admin)->post(route('finance.resale.periods.unlinked.ignore'), ['key' => $key])->assertRedirect();
        $this->assertSame(1, ResaleIgnoredLine::query()->count());

        // Zurückholen.
        $this->actingAs($admin)->delete(route('finance.resale.periods.unlinked.restore', $ignored->sqid))->assertRedirect()->assertSessionHas('success');
        $this->assertSame(0, ResaleIgnoredLine::query()->count());
        $this->assertSame(1, $mirror->unlinkedLines($this->organization, 10)['total']);

        // Unbekannter Schlüssel: Feldfehler.
        $this->actingAs($admin)->postJson(route('finance.resale.periods.unlinked.ignore'), ['key' => 'gibt-es-nicht'])->assertStatus(422)->assertJsonValidationErrors(['key']);
    }
}
