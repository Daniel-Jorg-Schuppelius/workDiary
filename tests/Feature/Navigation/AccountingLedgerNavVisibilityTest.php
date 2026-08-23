<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingLedgerNavVisibilityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Navigation;

use App\Enums\Finance\ProfitDetermination;
use App\Models\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, FiscalYearService};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hauptbuch-Arbeitsplatz nur bei lokaler Buchungshoheit (Feature 125).
 *
 * Eine Organisation mit Fachanwendung (Vorstufe/extern) bekommt keine sieben
 * toten Menüpunkte mit leeren Listen: sichtbar bleibt nur die Einrichtung,
 * und ein Direktaufruf erklärt den Leerzustand über den Hoheits-Hinweis.
 */
class AccountingLedgerNavVisibilityTest extends TestCase {
    use RefreshDatabase;

    public function test_ledger_workspace_hidden_without_local_sovereignty(): void {
        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $html = (string) $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('/finanzen/buchhaltung/inbox', $html);
        $this->assertStringNotContainsString('/reports/buchhaltung', $html);
        $this->assertStringContainsString('/finanzen/buchhaltung/einrichtung', $html);

        // Direktaufruf bleibt möglich — die Seite erklärt, warum sie leer ist.
        $this->get(route('finance.accounting.inbox.index'))
            ->assertOk()
            ->assertSee(__('accounting.ledger.sovereignty_note.preaccounting'));
    }

    public function test_ledger_workspace_visible_with_local_profile(): void {
        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
        app()->instance('currentOrganization', $org);

        $startsOn = CarbonImmutable::parse('2026-01-01');
        app(AccountingProfileService::class)->configure($org, [
            'profit_determination' => ProfitDetermination::DoubleEntry,
            'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1,
            'starts_on' => $startsOn,
            'note' => null,
        ]);
        app(FiscalYearService::class)->create($org, $startsOn);
        app(AccountingProfileService::class)->activateLocal($org, $admin);

        $html = (string) $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('/finanzen/buchhaltung/inbox', $html);
        $this->assertStringContainsString('/reports/buchhaltung', $html);

        $this->get(route('finance.accounting.inbox.index'))
            ->assertOk()
            ->assertDontSee(__('accounting.ledger.sovereignty_note.preaccounting'));
    }
}
