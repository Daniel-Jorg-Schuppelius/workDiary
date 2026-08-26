<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FixedAssetTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AccountType, AccountingEntryStatus, AccountingPeriodStatus, FixedAssetStatus, PostingAccountRole, PostingSourceKind, ProfitDetermination};
use App\Models\Accounting\{AccountingAccount, AccountingEntry, AccountingFiscalYear, AccountingPeriod, AccountingPostingRule, FixedAsset};
use App\Models\{Asset, Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsService, FiscalYearService, FixedAssetService, PeriodClosingService};
use App\Services\Accounting\Posting\Adapters\DepreciationAdapter;
use App\Services\Accounting\Posting\PostingInboxService;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Anlagenregister und Jahres-AfA als Inbox-Vorschlag (Feature 133, MVP-698).
 *
 * Abnahme: Der AfA-Vorschlag ist idempotent, blockiert ohne Konten oder bei
 * geschlossenem Jahr, und wird nur über die Inbox festgeschrieben.
 */
class FixedAssetTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private CarbonImmutable $startsOn;

    /** @var array<string, AccountingAccount> */
    private array $accounts = [];

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);

        $this->startsOn = CarbonImmutable::parse('2026-01-01');
        app(AccountingProfileService::class)->configure($this->org, [
            'profit_determination' => ProfitDetermination::DoubleEntry,
            'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1,
            'starts_on' => $this->startsOn,
            'note' => null,
        ]);
        app(FiscalYearService::class)->create($this->org, $this->startsOn);
        app(AccountingProfileService::class)->activateLocal($this->org, $this->admin);

        $chart = app(ChartOfAccountsService::class);
        foreach ([
            'bga' => ['0410', 'Betriebs- und Geschäftsausstattung', AccountType::Asset],
            'vehicles' => ['0320', 'Fuhrpark', AccountType::Asset],
            'depreciation' => ['4830', 'Abschreibungen auf Sachanlagen', AccountType::Expense],
        ] as $key => [$number, $name, $type]) {
            $this->accounts[$key] = $chart->create($this->org, ['number' => $number, 'name' => $name, 'type' => $type]);
        }
    }

    private function rules(): void {
        foreach ([PostingAccountRole::FixedAsset->value => 'bga', PostingAccountRole::Depreciation->value => 'depreciation'] as $role => $account) {
            AccountingPostingRule::query()->create([
                'organization_id' => $this->org->id,
                'source_kind' => PostingSourceKind::Depreciation,
                'role' => PostingAccountRole::from($role),
                'accounting_account_id' => $this->accounts[$account]->id,
                'priority' => 100,
                'version' => 1,
                'valid_from' => $this->startsOn->toDateString(),
                'is_active' => true,
            ]);
        }
    }

    /** @param array<string, mixed> $overrides */
    private function laptop(array $overrides = []): FixedAsset {
        return app(FixedAssetService::class)->create($this->org, $this->admin, $overrides + [
            'name' => 'Laptop',
            'acquired_on' => '2026-10-01',
            'acquisition_cost' => '3600.00',
            'residual_value' => '0.00',
            'useful_life_months' => 36,
        ]);
    }

    private function year2026(): AccountingFiscalYear {
        return AccountingFiscalYear::query()->where('organization_id', $this->org->id)->sole();
    }

    private function adapter(): DepreciationAdapter {
        return app(DepreciationAdapter::class);
    }

    public function test_assets_get_sequential_numbers_per_organization(): void {
        $first = $this->laptop();
        $second = $this->laptop(['name' => 'Drucker']);

        $other = Organization::factory()->create();
        app()->instance('currentOrganization', $other);
        $foreign = app(FixedAssetService::class)->create($other, $this->admin, [
            'name' => 'Fremd', 'acquired_on' => '2026-01-01', 'acquisition_cost' => '100.00', 'useful_life_months' => 12,
        ]);

        $this->assertSame('AN-1', $first->displayNo());
        $this->assertSame('AN-2', $second->displayNo());
        $this->assertSame(1, $foreign->asset_no);
        $this->assertSame(FixedAssetStatus::Active, $first->status);
    }

    /** Soll AfA-Aufwand / Haben Anlagenkonto — am Jahresende, mit Idempotenzschlüssel je Jahr. */
    public function test_the_yearly_proposal_books_expense_against_the_asset_account(): void {
        $this->rules();
        $asset = $this->laptop();

        $candidates = $this->adapter()->candidatesForYear($this->org, $this->year2026());
        $this->assertCount(1, $candidates);

        $proposal = $this->adapter()->proposalFor($this->org, $candidates->firstOrFail());

        $this->assertTrue($proposal->isPostable(), implode(' ', $proposal->blockers));
        $this->assertSame('depreciation:' . $asset->id . ':2026', $proposal->sourceKey);
        $this->assertSame('2026-12-31', $proposal->bookedOn->toDateString());
        $this->assertSame('300.00', $proposal->debitTotal());
        $this->assertSame('300.00', $proposal->creditTotal());

        $roles = array_map(fn ($line): string => $line->role->value, $proposal->lines);
        $this->assertSame(['depreciation', 'fixed_asset'], $roles);
        $this->assertSame($this->accounts['depreciation']->id, $proposal->lines[0]->account->id);
        $this->assertSame('300.00', $proposal->lines[0]->debit);
        $this->assertSame($this->accounts['bga']->id, $proposal->lines[1]->account->id);
        $this->assertSame('300.00', $proposal->lines[1]->credit);
        $this->assertSame('2026', $proposal->extra['fiscal_year']);
    }

    public function test_accounts_on_the_asset_override_the_rules(): void {
        $this->rules();
        $asset = $this->laptop(['name' => 'Transporter', 'asset_account_id' => $this->accounts['vehicles']->id]);

        $proposal = $this->adapter()->proposalFor($this->org, $asset->forFiscalYear($this->year2026()));

        $this->assertTrue($proposal->isPostable());
        $this->assertSame($this->accounts['vehicles']->id, $proposal->lines[1]->account->id);
        $this->assertStringContainsString('asset:' . $asset->id, (string) $proposal->ruleVersion);
    }

    public function test_missing_accounts_block_instead_of_guessing(): void {
        $asset = $this->laptop();

        $proposal = $this->adapter()->proposalFor($this->org, $asset->forFiscalYear($this->year2026()));

        $this->assertFalse($proposal->isPostable());
        $this->assertCount(2, $proposal->blockers);
    }

    public function test_a_closed_year_blocks_the_proposal(): void {
        $this->rules();
        $asset = $this->laptop();
        $year = $this->year2026();
        $year->update(['status' => AccountingPeriodStatus::Closed]);

        $proposal = $this->adapter()->proposalFor($this->org, $asset->forFiscalYear($year->refresh()));

        $this->assertFalse($proposal->isPostable());
        $this->assertStringContainsString('2026', implode(' ', $proposal->blockers));

        $this->expectException(ValidationException::class);
        app(FixedAssetService::class)->proposeForYear($this->org, $year, $this->admin);
    }

    /** Der Jahresvorschlag ist idempotent: ein zweiter Lauf legt nichts Neues an. */
    public function test_proposing_a_year_twice_creates_one_draft_per_asset(): void {
        $this->rules();
        $this->laptop();
        $this->laptop(['name' => 'Drucker', 'acquisition_cost' => '1200.00', 'useful_life_months' => 12]);

        $first = app(FixedAssetService::class)->proposeForYear($this->org, $this->year2026(), $this->admin);
        $second = app(FixedAssetService::class)->proposeForYear($this->org, $this->year2026(), $this->admin);

        $this->assertSame(2, $first['prepared']);
        $this->assertSame(0, $second['prepared']);
        $this->assertSame(2, $second['skipped']);
        $this->assertSame(2, AccountingEntry::query()->where('source_type', FixedAsset::class)->count());
        $this->assertSame(AccountingEntryStatus::Ready, AccountingEntry::query()->firstOrFail()->status);
    }

    /** Vollzug nur über die Inbox — dann steht die Buchung mit den richtigen Konten im Journal. */
    public function test_posting_through_the_inbox_creates_the_journal_entry(): void {
        $this->rules();
        $asset = $this->laptop();
        $inbox = app(PostingInboxService::class);

        $entry = $inbox->prepare($this->org, $this->adapter()->proposalFor($this->org, $asset->forFiscalYear($this->year2026())), $this->admin);
        $posted = $inbox->post($entry, $this->admin);

        $this->assertSame(AccountingEntryStatus::Posted, $posted->status);
        $this->assertSame(FixedAsset::class, $posted->source_type);
        $this->assertSame($asset->id, (int) $posted->source_id);
        $this->assertSame('depreciation:' . $asset->id . ':2026', $posted->source_key);

        $lines = $posted->lines()->with('account')->get();
        $this->assertCount(2, $lines);
        $this->assertSame('300.00', $lines->firstWhere('accounting_account_id', $this->accounts['depreciation']->id)?->debit?->getAmount());
        $this->assertSame('300.00', $lines->firstWhere('accounting_account_id', $this->accounts['bga']->id)?->credit?->getAmount());

        // Nach der Festschreibung sind die Wertfelder eingefroren.
        $this->expectException(ValidationException::class);
        app(FixedAssetService::class)->update($asset->refresh(), ['acquisition_cost' => '9999.00']);
    }

    public function test_a_prepared_year_shows_up_in_the_inbox_and_cannot_be_prepared_twice(): void {
        $this->rules();
        $asset = $this->laptop();
        $inbox = app(PostingInboxService::class);

        $items = $inbox->items($this->org, $this->startsOn, $this->startsOn->endOfYear(), PostingSourceKind::Depreciation);
        $this->assertCount(1, $items);
        $this->assertSame('open', $items->firstOrFail()['state']);

        $first = $inbox->prepare($this->org, $items->firstOrFail()['proposal'], $this->admin);
        $second = $inbox->prepare($this->org, $this->adapter()->proposalFor($this->org, $asset->forFiscalYear($this->year2026())), $this->admin);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('ready', $inbox->items($this->org, $this->startsOn, $this->startsOn->endOfYear(), PostingSourceKind::Depreciation)->firstOrFail()['state']);
    }

    public function test_disposal_follows_the_status_machine(): void {
        $service = app(FixedAssetService::class);
        $asset = $this->laptop(['acquired_on' => '2026-01-01']);

        $disposed = $service->dispose($asset, CarbonImmutable::parse('2026-05-15'), $this->admin, 'Verkauft');

        $this->assertSame(FixedAssetStatus::Disposed, $disposed->status);
        $this->assertSame('2026-05-15', $disposed->disposed_on?->toDateString());
        $this->assertSame('Verkauft', $disposed->note);
        $this->assertFalse(FixedAssetStatus::Disposed->canTransitionTo(FixedAssetStatus::Active));

        // Abgangsjahr: zeitanteilig bis zum Abgangsmonat, Buchung am Abgangstag.
        $this->rules();
        $proposal = $this->adapter()->proposalFor($this->org, $disposed->forFiscalYear($this->year2026()));
        $this->assertSame('500.00', $proposal->debitTotal());
        $this->assertSame('2026-05-15', $proposal->bookedOn->toDateString());

        $this->expectException(\RuntimeException::class);
        $service->dispose($disposed, CarbonImmutable::parse('2026-06-01'), $this->admin);
    }

    public function test_disposal_before_acquisition_is_rejected(): void {
        $asset = $this->laptop();

        $this->expectException(ValidationException::class);
        app(FixedAssetService::class)->dispose($asset, CarbonImmutable::parse('2026-09-30'), $this->admin);
    }

    public function test_residual_value_must_stay_below_the_cost(): void {
        $this->expectException(ValidationException::class);
        $this->laptop(['residual_value' => '3600.00']);
    }

    /** Die letzte Periode des Jahres warnt, solange AfA offen ist — blockiert aber nicht. */
    public function test_the_closing_preflight_warns_about_unposted_depreciation(): void {
        $this->rules();
        $this->laptop();
        $december = AccountingPeriod::query()->where('organization_id', $this->org->id)->covering(CarbonImmutable::parse('2026-12-15'))->sole();

        $report = app(PeriodClosingService::class)->preflight($december);
        $warning = collect($report->warnings())->firstWhere('key', 'depreciation');

        $this->assertTrue($report->isReady());
        $this->assertNotNull($warning);
        $this->assertStringContainsString('1', $warning->message);
    }

    public function test_register_pages_and_proposal_route_respect_permissions_and_tenancy(): void {
        $this->rules();
        $asset = $this->laptop();
        $member = User::factory()->create(['organization_id' => $this->org->id]);

        $this->actingAs($member)->get(route('finance.accounting.fixed-assets.index'))->assertForbidden();
        $this->actingAs($member)->post(route('finance.accounting.closing.depreciation', $this->year2026()))->assertForbidden();

        $this->actingAs($this->admin)->get(route('finance.accounting.fixed-assets.index'))->assertOk()->assertSee('AN-1');
        $this->actingAs($this->admin)->get(route('finance.accounting.fixed-assets.show', $asset))->assertOk()->assertSee('300,00');
        $this->actingAs($this->admin)->get(route('finance.accounting.fixed-assets.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('finance.accounting.fixed-assets.edit', $asset))->assertOk();
        $this->actingAs($this->admin)->get(route('finance.accounting.fixed-assets.dispose-form', $asset))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('finance.accounting.closing.depreciation', $this->year2026()))
            ->assertRedirect();
        $this->assertSame(AccountingEntryStatus::Ready, AccountingEntry::query()->where('source_key', 'depreciation:' . $asset->id . ':2026')->sole()->status);

        // Fremde Organisation: Anlage nicht sichtbar.
        $other = Organization::factory()->create();
        $foreign = FixedAsset::query()->create([
            'organization_id' => $other->id, 'asset_no' => 1, 'name' => 'Fremd', 'acquired_on' => '2026-01-01',
            'currency' => 'EUR', 'acquisition_cost' => '100.00', 'residual_value' => '0.00', 'useful_life_months' => 12,
            'depreciation_method' => 'linear', 'status' => 'active',
        ]);
        $this->actingAs($this->admin)->get(route('finance.accounting.fixed-assets.show', $foreign))->assertNotFound();
    }

    public function test_the_form_stores_an_asset_with_a_linked_device(): void {
        $device = Asset::factory()->create(['organization_id' => $this->org->id]);

        $this->actingAs($this->admin)->post(route('finance.accounting.fixed-assets.store'), [
            'name' => 'Server',
            'device' => $device->sqid,
            'acquired_on' => '2026-03-01',
            'acquisition_cost' => '2400.00',
            'residual_value' => '0',
            'useful_life_months' => 24,
            'depreciation_method' => 'linear',
            'asset_account' => $this->accounts['bga']->sqid,
            'depreciation_account' => $this->accounts['depreciation']->sqid,
        ])->assertRedirect();

        $stored = FixedAsset::query()->where('organization_id', $this->org->id)->sole();
        $this->assertSame($device->id, $stored->asset_id);
        $this->assertSame('2400.00', $stored->acquisition_cost?->getAmount());
        $this->assertSame($this->accounts['bga']->id, $stored->asset_account_id);
        $this->assertSame('1000.00', app(\App\Services\Accounting\DepreciationCalculator::class)->amountForYear($stored, 2026)->getAmount());
    }
}
