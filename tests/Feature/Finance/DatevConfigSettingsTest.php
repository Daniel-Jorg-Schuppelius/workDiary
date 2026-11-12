<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevConfigSettingsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Finance\AccountType;
use App\Models\Platform\User;
use App\Services\Accounting\ChartOfAccountsService;
use App\Services\Finance\Datev\DatevBookingConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1010: Leere Felder der DATEV-Konfiguration entfernen den gespeicherten Wert. */
final class DatevConfigSettingsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_cleared_fields_are_removed_and_others_stay(): void {
        $this->setUpOrganization();
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)->put(route('finance.datev.config.update'), ['datev' => ['advisor_number' => '12345', 'client_number' => '67890', 'revenue_account' => '8400']])
            ->assertSessionHasNoErrors();
        $this->assertSame('8400', $this->organization->refresh()->settings['datev']['revenue_account'] ?? null);

        $this->actingAs($admin)->put(route('finance.datev.config.update'), ['datev' => ['advisor_number' => '12345', 'revenue_account' => '']])
            ->assertSessionHasNoErrors();
        $datev = $this->organization->refresh()->settings['datev'] ?? [];
        $this->assertArrayNotHasKey('revenue_account', $datev);
        $this->assertSame('67890', (string) ($datev['client_number'] ?? ''));
    }

    /** MVP-1555: Skonto- und Ausbuchungskonto stehen im Formular; mit lokalem Kontenplan muss das Konto dort existieren. */
    public function test_settlement_accounts_are_editable_and_checked_against_the_chart(): void {
        $this->setUpOrganization();
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)->get(route('finance.datev.config'))
            ->assertOk()
            ->assertSee('name="datev[discount_account]"', false)
            ->assertSee('name="datev[write_off_account]"', false);

        // Ohne lokalen Kontenplan (reiner DATEV-Export) gilt jede Nummer.
        $this->actingAs($admin)->put(route('finance.datev.config.update'), ['datev' => ['write_off_account' => '2405']])
            ->assertSessionHasNoErrors();
        $this->assertSame('2405', DatevBookingConfig::forOrganization($this->organization->refresh())->writeOffAccount);

        $chart = app(ChartOfAccountsService::class);
        $chart->create($this->organization, ['number' => '8736', 'name' => 'Gewährte Skonti', 'type' => AccountType::Expense]);

        $this->actingAs($admin)->put(route('finance.datev.config.update'), ['datev' => ['write_off_account' => '2499']])
            ->assertSessionHasErrors('datev.write_off_account');
        $this->actingAs($admin)->get(route('finance.datev.config'))
            ->assertOk()
            ->assertSee(__('finance.datev.config.settlement_accounts_missing', ['accounts' => '2405, 3736, 2709']));

        $chart->create($this->organization, ['number' => '2405', 'name' => 'Forderungsverluste', 'type' => AccountType::Expense]);
        $chart->create($this->organization, ['number' => '3736', 'name' => 'Erhaltene Skonti', 'type' => AccountType::Expense]);
        $this->actingAs($admin)->get(route('finance.datev.config'))
            ->assertOk()
            ->assertSee(__('finance.datev.config.settlement_accounts_missing', ['accounts' => '2709']));

        $chart->create($this->organization, ['number' => '2709', 'name' => 'Sonstige Erträge', 'type' => AccountType::Income]);
        $this->actingAs($admin)->get(route('finance.datev.config'))
            ->assertOk()
            ->assertDontSee('alert bg-warning/10', false);
    }

    /** MVP-1555: Der Nachzug legt das Ausbuchungskonto nur dort an, wo der Kontenplan aus der Vorlage stammt und es fehlt. */
    public function test_the_backfill_adds_the_write_off_account_to_template_charts_only(): void {
        $this->setUpOrganization();
        $chart = app(ChartOfAccountsService::class);
        $skr04 = \App\Models\Platform\Organization::factory()->create();
        $own = \App\Models\Platform\Organization::factory()->create();
        $complete = \App\Models\Platform\Organization::factory()->create();
        $chart->create($this->organization, ['number' => '8736', 'name' => 'Gewährte Skonti', 'type' => AccountType::Expense]);
        $chart->create($skr04, ['number' => '4736', 'name' => 'Gewährte Skonti', 'type' => AccountType::Expense]);
        $chart->create($own, ['number' => '7000', 'name' => 'Eigener Plan', 'type' => AccountType::Expense]);
        $chart->create($complete, ['number' => '8736', 'name' => 'Gewährte Skonti', 'type' => AccountType::Expense]);
        $chart->create($complete, ['number' => '2400', 'name' => 'Eigene Bezeichnung', 'type' => AccountType::Expense]);

        $migration = require database_path('migrations/2027_04_17_120000_add_write_off_template_accounts.php');
        $migration->up();
        $migration->up();

        $numbers = fn (int $organizationId): array => \App\Models\Accounting\AccountingAccount::query()
            ->withoutGlobalScopes()->where('organization_id', $organizationId)->orderBy('number')->pluck('number')->all();
        $this->assertSame(['2400', '8736'], $numbers($this->organization->id));
        $this->assertSame(['4736', '6930'], $numbers($skr04->id));
        $this->assertSame(['7000'], $numbers($own->id));
        $this->assertSame(['2400', '8736'], $numbers($complete->id));
        $this->assertSame('Eigene Bezeichnung', \App\Models\Accounting\AccountingAccount::query()->withoutGlobalScopes()->where('organization_id', $complete->id)->where('number', '2400')->value('name'));

        // Gegenkonten der Verbindlichkeiten: erhaltene Skonti und sonstiger Ertrag.
        $payables = require database_path('migrations/2027_04_17_120200_add_payable_settlement_template_accounts.php');
        $payables->up();
        $payables->up();
        $this->assertSame(['2400', '2709', '3736', '8736'], $numbers($this->organization->id));
        $this->assertSame(['4736', '4839', '5736', '6930'], $numbers($skr04->id));
        $this->assertSame(['7000'], $numbers($own->id));
        $income = \App\Models\Accounting\AccountingAccount::query()->withoutGlobalScopes()->where('organization_id', $skr04->id)->where('number', '4839')->sole();
        $this->assertSame([AccountType::Income, \App\Enums\Finance\BalanceSide::Credit], [$income->type, $income->normal_balance]);

        $added = \App\Models\Accounting\AccountingAccount::query()->withoutGlobalScopes()->where('organization_id', $skr04->id)->where('number', '6930')->sole();
        $this->assertSame(AccountType::Expense, $added->type);
        $this->assertSame(\App\Enums\Finance\EuerCategory::Expense, $added->euer_category);
        $this->assertTrue($added->is_active);
    }
}
