<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVoucherExpenseLinkTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Expense;

use App\Enums\Expense\{ExpenseStatus, PaymentMethod};
use App\Models\Finance\AccountingVoucher;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\{PluginSetting, User};
use App\Models\Supplier\Supplier;
use App\Models\Travel\Expense;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Services\LexofficeExpenseLinkProvider;
use App\Plugins\PluginManager;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Services\Billing\{DocumentFeedFilters, DocumentFeedQuery, ExpenseLinkProviderResolver, NullExpenseLinkProvider};
use App\Services\Finance\Accounting\Vouchers\AccountingVoucherExpenseLinkProvider;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Auslage ↔ eingehender Spiegelbeleg (MVP-1498): Vorschläge, Zuordnen,
 * Lösen und Dublettenregel 3 für die Programme des allgemeinen
 * Belegspiegels; kein Push (Entscheidung E8, Feature 106).
 */
final class AccountingVoucherExpenseLinkTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Supplier $supplier;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Hafenbedarf Lüders']);
    }

    private function enable(string $pluginId): void {
        PluginSetting::query()->create(['organization_id' => $this->organization->id, 'plugin_id' => $pluginId, 'enabled' => true, 'settings' => ['api_key' => 'k-' . $pluginId]]);
        app(PluginManager::class)->flushRuntimeCaches();
    }

    private function expense(string $gross = '39.15', string $date = '2026-08-10'): Expense {
        return Expense::factory()->create([
            'organization_id' => $this->organization->id, 'user_id' => $this->admin->id, 'date' => $date, 'vendor' => 'Hafenbedarf Lüders',
            'status' => ExpenseStatus::Approved, 'payment_method' => PaymentMethod::PrivatePaid, 'currency' => 'EUR', 'amount_net' => $gross, 'tax_rate' => '0',
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function voucher(string $number, array $attributes = []): AccountingVoucher {
        return AccountingVoucher::query()->create($attributes + [
            'organization_id' => $this->organization->id, 'plugin_id' => SevDeskPlugin::ID, 'external_id' => uniqid('sd-', true),
            'supplier_id' => $this->supplier->id, 'direction' => 'incoming', 'document_kind' => 'other', 'voucher_type' => 'C', 'voucher_state' => 'paid',
            'voucher_number' => $number, 'voucher_date' => '2026-08-12', 'total_amount' => '39.15', 'currency' => 'EUR', 'archived' => false,
        ]);
    }

    private function filters(): DocumentFeedFilters {
        return new DocumentFeedFilters(
            organizationId: (int) $this->organization->id, userId: (int) $this->admin->id,
            from: CarbonImmutable::parse('2026-01-01')->startOfDay(), to: CarbonImmutable::parse('2026-12-31')->endOfDay(),
            allExpenses: true, sources: ['invoice' => true, 'quote' => true, 'voucher' => true, 'incoming_einvoice' => true, 'expense' => true],
        );
    }

    public function test_the_mirror_provider_serves_an_enabled_program_and_lexoffice_keeps_precedence(): void {
        $this->actingAs($this->admin);
        $this->assertInstanceOf(NullExpenseLinkProvider::class, app(ExpenseLinkProviderResolver::class)->current(), 'ohne Programm kein Anbieter');

        $this->enable(SevDeskPlugin::ID);
        $provider = app(ExpenseLinkProviderResolver::class)->current();
        $this->assertInstanceOf(AccountingVoucherExpenseLinkProvider::class, $provider);
        $this->assertSame('sevDesk', $provider->label());

        $this->enable(LexofficePlugin::ID);
        $this->assertInstanceOf(LexofficeExpenseLinkProvider::class, app(ExpenseLinkProviderResolver::class)->current(), 'Lexoffice mit Push geht vor');
    }

    public function test_suggest_link_and_unlink_with_dedup_rule_three(): void {
        $this->enable(SevDeskPlugin::ID);
        $expense = $this->expense();
        $match = $this->voucher('SD-BELEG-1');
        $this->voucher('SD-BELEG-ZU-ALT', ['voucher_date' => '2026-06-01']);
        $this->voucher('SD-BELEG-ANDERER-BETRAG', ['total_amount' => '41.00']);
        $this->voucher('SD-ENTWURF', ['voucher_state' => 'draft']);
        $this->voucher('SD-AUSGANG', ['direction' => 'outgoing', 'voucher_type' => 'D']);

        $this->actingAs($this->admin)->get(route('expenses.receipt', $expense))->assertOk()
            ->assertSee('SD-BELEG-1')->assertDontSee('SD-BELEG-ZU-ALT')->assertDontSee('SD-BELEG-ANDERER-BETRAG')->assertDontSee('SD-ENTWURF')->assertDontSee('SD-AUSGANG')
            ->assertDontSee('In die Buchhaltung übernehmen');

        $before = (new DocumentFeedQuery($this->filters()))->totals()[0];
        $this->assertEqualsWithDelta(39.15, $before['internal'], 0.001, 'vor der Zuordnung zählt die Auslage');
        $this->assertEqualsWithDelta(119.30, $before['expense'], 0.001, 'Aufwand aus den Eingangsbelegen, der Entwurf zählt nicht');

        $this->actingAs($this->admin)->post(route('expenses.link-voucher', $expense), ['voucher' => Sqid::encode(AccountingVoucher::class, (int) $match->id)])
            ->assertSessionHas('success');
        $reference = ExternalReference::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame([SevDeskPlugin::ID, AccountingVoucher::EXPENSE_LINK, $match->external_id], [$reference->plugin_id, $reference->external_type, $reference->external_id]);

        $totals = (new DocumentFeedQuery($this->filters()))->totals()[0];
        $this->assertEqualsWithDelta(0.0, $totals['internal'], 0.001, 'Dublettenregel 3: die zugeordnete Auslage zählt nicht doppelt');
        $this->assertEqualsWithDelta(119.30, $totals['expense'], 0.001, 'der Beleg führt, der Aufwand bleibt');

        $this->actingAs($this->admin)->get(route('expenses.receipt', $expense))->assertOk()->assertSee('SD-BELEG-1')->assertSee(route('expenses.unlink-voucher', $expense), false);

        // Ein Beleg belegt genau eine Auslage.
        $second = $this->expense();
        $this->actingAs($this->admin)->get(route('expenses.receipt', $second))->assertOk()->assertDontSee('SD-BELEG-1');
        $this->actingAs($this->admin)->post(route('expenses.link-voucher', $second), ['voucher' => Sqid::encode(AccountingVoucher::class, (int) $match->id)])
            ->assertSessionHas('error', 'Beleg SD-BELEG-1 belegt bereits eine andere Auslage.');

        $this->actingAs($this->admin)->delete(route('expenses.unlink-voucher', $expense))->assertSessionHas('success');
        $this->assertSame(0, ExternalReference::query()->withoutGlobalScopes()->count());
        $this->assertEqualsWithDelta(78.30, (new DocumentFeedQuery($this->filters()))->totals()[0]['internal'], 0.001);
    }

    public function test_push_stays_with_lexoffice(): void {
        $this->enable(SevDeskPlugin::ID);
        $expense = $this->expense();

        $this->actingAs($this->admin)->post(route('expenses.push-voucher', $expense))
            ->assertSessionHas('error', 'sevDesk übernimmt keine Auslagen als Beleg — ordnen Sie einen vorhandenen Beleg zu.');
        $this->actingAs($this->admin)->post(route('expenses.link-voucher', $expense), ['voucher' => 'kein-beleg'])
            ->assertSessionHas('error', 'Der gewählte Beleg ist nicht bekannt oder kein Einkaufsbeleg dieser Organisation.');
        $this->assertSame(0, ExternalReference::query()->withoutGlobalScopes()->count());
    }
}
