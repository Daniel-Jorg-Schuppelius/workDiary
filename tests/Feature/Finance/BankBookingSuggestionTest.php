<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BankBookingSuggestionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{BookingSuggestionBasis, DirectBookingKind, MatchStatus, ProfitDetermination, TransactionDirection};
use App\Models\Accounting\{AccountingAccount, AccountingEntry, AccountingTaxCode};
use App\Models\Finance\{BankStatement, BankTransaction};
use App\Models\Platform\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsTemplateService, FiscalYearService};
use App\Services\Accounting\Posting\{BankBookingSuggestions, PostingInboxService};
use App\Settings\SettingScope;
use App\Support\Crypto\{BlindIndex, PurposeKeywordIndex};
use App\Support\Setting;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\BankHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kontierungshilfe für Bankumsätze ohne Beleg (Feature 207, MVP-1660).
 *
 * Abnahme: Nach zwei Direktbuchungen der Miete mit derselben Mandatsreferenz
 * zeigt der dritte Umsatz Konto und Steuerschlüssel mit Begründung; zwölf
 * offene Umsätze mit gleicher Gläubiger-ID lassen sich mit einer Bestätigung
 * buchen, einer mit stark abweichendem Betrag steht einzeln. Alle Daten sind
 * erfunden (Gläubiger-ID aus dem Beispiel der Bundesbank, IBAN mit Bankleitzahl
 * 99999999 und gültiger Prüfziffer).
 */
class BankBookingSuggestionTest extends TestCase {
    use RefreshDatabase;

    /** Beispiel-Gläubiger-ID der Deutschen Bundesbank — keine echte. */
    private const string CREDITOR = 'DE98ZZZ09999999999';

    private const string OTHER_CREDITOR = 'DE66ZZZ00000000042';

    private Organization $org;

    private User $admin;

    private BankStatement $statement;

    private AccountingAccount $rent;

    private AccountingAccount $insurance;

    private AccountingTaxCode $inputTax;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);

        $startsOn = CarbonImmutable::parse('2026-01-01');
        app(AccountingProfileService::class)->configure($this->org, [
            'profit_determination' => ProfitDetermination::DoubleEntry,
            'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1,
            'starts_on' => $startsOn,
            'note' => null,
        ]);
        app(FiscalYearService::class)->create($this->org, $startsOn);
        app(AccountingProfileService::class)->activateLocal($this->org, $this->admin);
        app(ChartOfAccountsTemplateService::class)->apply($this->org, 'skr03', $startsOn);

        $this->rent = AccountingAccount::query()->where('organization_id', $this->org->id)->where('number', '4210')->sole();
        $this->insurance = AccountingAccount::query()->where('organization_id', $this->org->id)->where('number', '4360')->sole();
        $this->inputTax = AccountingTaxCode::query()->where('organization_id', $this->org->id)->where('code', 'VSt19')->sole();
        $this->statement = BankStatement::factory()->create(['organization_id' => $this->org->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function transaction(string $date, string $amount, array $attributes = []): BankTransaction {
        $iban = BankHelper::generateGermanIBAN('99999999', '0000000017');
        $purpose = $attributes['purpose'] ?? 'Miete Büro ' . $date;

        return BankTransaction::factory()->create(array_merge([
            'organization_id' => $this->org->id,
            'bank_statement_id' => $this->statement->id,
            'booking_date' => $date,
            'valuta_date' => $date,
            'amount' => $amount,
            'direction' => TransactionDirection::Debit,
            'currency' => 'EUR',
            'counterparty_name' => 'Musterhausverwaltung',
            'counterparty_iban' => $iban,
            'counterparty_iban_hash' => BlindIndex::ofIban($iban),
            'purpose' => $purpose,
            'purpose_keyword_hash' => PurposeKeywordIndex::hash($purpose),
            'match_status' => MatchStatus::Unmatched,
        ], $attributes));
    }

    private function inbox(): PostingInboxService {
        return app(PostingInboxService::class);
    }

    private function suggestions(): BankBookingSuggestions {
        return app(BankBookingSuggestions::class);
    }

    private function bookRent(BankTransaction $transaction, ?AccountingAccount $account = null): AccountingEntry {
        return $this->inbox()->postBankTransactionToAccount($this->org, $transaction, $account ?? $this->rent, $this->inputTax, null, 'Miete Büro', $this->admin);
    }

    /** Abnahme 1: Zwei Mietbuchungen mit gleicher Mandatsreferenz — der dritte Umsatz bekommt Konto und Steuerschlüssel mit Begründung. */
    public function test_third_rent_transaction_gets_account_and_tax_code_with_reason(): void {
        $mandate = ['mandate_ref' => 'MIETE-0815', 'creditor_identifier' => self::CREDITOR];
        $this->bookRent($this->transaction('2026-01-03', '1190.00', $mandate));
        $this->bookRent($this->transaction('2026-02-03', '1190.00', $mandate));
        $third = $this->transaction('2026-03-03', '1190.00', $mandate);

        $suggestion = $this->suggestions()->forTransaction($this->org, $third);

        $this->assertNotNull($suggestion);
        $this->assertSame(BookingSuggestionBasis::Mandate, $suggestion->basis);
        $this->assertSame($this->rent->id, $suggestion->account->id);
        $this->assertSame($this->inputTax->id, $suggestion->taxCode?->id);
        $this->assertSame(2, $suggestion->matches);
        $this->assertFalse($suggestion->deviates);
        $this->assertSame('strong', $suggestion->strength());
        $this->assertSame('2-mal so gebucht, zuletzt 02/2026, gleiche Mandatsreferenz', $suggestion->reason());

        // Vorbelegt im Dialog, gebucht wird nichts.
        $this->actingAs($this->admin)->get(route('finance.accounting.inbox.bank-direct.create', $third->sqid))
            ->assertOk()
            ->assertSee('2-mal so gebucht, zuletzt 02/2026, gleiche Mandatsreferenz')
            ->assertSee('value="' . $this->rent->sqid . '" selected', false)
            ->assertSee('value="' . $this->inputTax->sqid . '" selected', false);
        $this->assertSame(0, AccountingEntry::query()->where('source_key', $this->inbox()->directKey($third))->count());
        $this->assertSame(MatchStatus::Unmatched, $third->refresh()->match_status);
    }

    /** Abnahme 2: Zwölf gleichartige Umsätze mit einer Bestätigung; einer mit stark abweichendem Betrag steht einzeln. */
    public function test_twelve_transactions_with_same_creditor_are_booked_with_one_confirmation(): void {
        $creditor = ['creditor_identifier' => self::CREDITOR, 'counterparty_name' => 'Versicherung Muster AG', 'purpose' => 'Beitrag Betriebshaftpflicht'];
        $this->inbox()->postBankTransactionToAccount($this->org, $this->transaction('2026-01-15', '49.90', $creditor + ['mandate_ref' => 'VS-1']), $this->insurance, null, null, 'Betriebshaftpflicht', $this->admin);

        $open = [];
        foreach (range(1, 12) as $index) {
            // Je Umsatz eine eigene Mandatsreferenz: gleich ist nur der Gläubiger.
            $open[] = $this->transaction(sprintf('2026-02-%02d', $index), '49.90', $creditor + ['mandate_ref' => 'VS-1' . $index]);
        }
        $outlier = $this->transaction('2026-02-20', '149.90', $creditor + ['mandate_ref' => 'VS-99']);

        $overview = $this->suggestions()->overview($this->org);

        $this->assertCount(1, $overview['groups']);
        $group = $overview['groups'][0];
        $this->assertCount(12, $group['items']);
        $this->assertSame(BookingSuggestionBasis::Creditor, $group['suggestion']->basis);
        $this->assertCount(1, $overview['singles']);
        $this->assertSame($outlier->id, $overview['singles'][0]['transaction']->id);
        $this->assertTrue($overview['singles'][0]['suggestion']->deviates);

        $page = $this->actingAs($this->admin)->get(route('finance.accounting.inbox.suggestions.index'))->assertOk();
        $page->assertSee('Alle 12 Umsätze buchen')
            ->assertSee('1-mal so gebucht, zuletzt 01/2026, gleiche Gläubiger-ID')
            ->assertSee(__('accounting.suggestions.deviates', ['last' => '49,90']));
        // Die Übersicht bucht nichts.
        $this->assertSame(1, AccountingEntry::query()->where('organization_id', $this->org->id)->count());

        $this->actingAs($this->admin)->post(route('finance.accounting.inbox.suggestions.confirm'), [
            'group' => $group['key'],
            'transactions' => array_map(static fn (BankTransaction $transaction): string => $transaction->sqid, $open),
        ])->assertRedirect(route('finance.accounting.inbox.suggestions.index'))
            ->assertSessionHas('status', __('accounting.suggestions.flash.confirmed', ['posted' => 12, 'submitted' => 0, 'skipped' => 0]));

        foreach ($open as $transaction) {
            $entry = AccountingEntry::query()->where('source_key', $this->inbox()->directKey($transaction))->sole()->load('lines.account');
            $this->assertTrue($entry->status->isPosted());
            $this->assertSame(DirectBookingKind::BankDirect, DirectBookingKind::of($entry));
            $this->assertContains('4360', $entry->lines->map(fn ($line) => $line->account?->number)->all());
            $this->assertSame(MatchStatus::Ignored, $transaction->refresh()->match_status);
        }
        $this->assertSame(0, AccountingEntry::query()->where('source_key', $this->inbox()->directKey($outlier))->count());
        $this->assertSame(MatchStatus::Unmatched, $outlier->refresh()->match_status);

        // Ein zweites Absenden bucht nichts doppelt.
        $this->actingAs($this->admin)->post(route('finance.accounting.inbox.suggestions.confirm'), [
            'group' => $group['key'],
            'transactions' => array_map(static fn (BankTransaction $transaction): string => $transaction->sqid, $open),
        ])->assertSessionHas('status', __('accounting.suggestions.flash.confirmed', ['posted' => 0, 'submitted' => 0, 'skipped' => 12]));
        $this->assertSame(13, AccountingEntry::query()->where('organization_id', $this->org->id)->count());
    }

    /** Eine Korrektur wirkt beim nächsten Mal: die jüngste Buchung bestimmt die Kontierung. */
    public function test_the_latest_booking_wins_so_a_correction_takes_effect_next_time(): void {
        $mandate = ['mandate_ref' => 'MIETE-0815'];
        $this->bookRent($this->transaction('2026-01-03', '1190.00', $mandate));
        $this->bookRent($this->transaction('2026-02-03', '1190.00', $mandate));
        $this->bookRent($this->transaction('2026-03-03', '1190.00', $mandate), $this->insurance);

        $suggestion = $this->suggestions()->forTransaction($this->org, $this->transaction('2026-04-03', '1190.00', $mandate));

        $this->assertNotNull($suggestion);
        $this->assertSame($this->insurance->id, $suggestion->account->id);
        $this->assertSame('1 von 3 gleichartigen Buchungen so, zuletzt 03/2026, gleiche Mandatsreferenz', $suggestion->reason());
        $this->assertSame('medium', $suggestion->strength());
    }

    /** Rangfolge: Mandat vor IBAN; anderer Gläubiger mit gleicher Mandatsreferenz ist ein anderes Mandat; Richtung zählt. */
    public function test_features_are_ranked_and_respect_creditor_and_direction(): void {
        $this->bookRent($this->transaction('2026-01-03', '1190.00', ['mandate_ref' => 'M-1', 'creditor_identifier' => self::CREDITOR]));

        // Gleiche Mandatsreferenz, anderer Gläubiger: kein Mandatstreffer, aber die IBAN passt.
        $other = $this->suggestions()->forTransaction($this->org, $this->transaction('2026-02-03', '1190.00', ['mandate_ref' => 'M-1', 'creditor_identifier' => self::OTHER_CREDITOR]));
        $this->assertNotNull($other);
        $this->assertSame(BookingSuggestionBasis::Iban, $other->basis);
        $this->assertSame('weak', $other->strength());

        // Geldeingang mit gleichen Merkmalen (etwa eine Erstattung): kein Vorschlag aus Ausgaben.
        $refund = $this->transaction('2026-02-10', '1190.00', ['mandate_ref' => 'M-1', 'creditor_identifier' => self::CREDITOR, 'direction' => TransactionDirection::Credit]);
        $this->assertNull($this->suggestions()->forTransaction($this->org, $refund));

        // Nur Stichwörter: „Miete Büro“ in einem anderen Monat, ohne IBAN und Mandat.
        $keywords = $this->transaction('2026-03-03', '1190.00', ['counterparty_iban' => null, 'counterparty_iban_hash' => null, 'purpose' => 'Miete Büro März 2026']);
        $byKeywords = $this->suggestions()->forTransaction($this->org, $keywords);
        $this->assertNotNull($byKeywords);
        $this->assertSame(BookingSuggestionBasis::Keywords, $byKeywords->basis);
    }

    /** Datenschutz: Der Zweck bleibt verschlüsselt, gespeichert wird nur ein geschlüsselter Abdruck der Stichwörter. */
    public function test_purpose_keywords_are_stored_only_as_keyed_hash(): void {
        $january = PurposeKeywordIndex::hash('SVWZ+Miete Januar 2026 Büro Hauptstraße 12 EREF+RE-2026-01');
        $february = PurposeKeywordIndex::hash('Miete Februar 2026 Buero Hauptstrasse 12');

        $this->assertSame($january, $february);
        $this->assertSame(['buero', 'hauptstrasse', 'miete'], PurposeKeywordIndex::keywords('Miete Januar 2026 Büro Hauptstraße 12'));
        $this->assertNotSame(hash('sha256', 'purpose-keywords:buero hauptstrasse miete'), $january, 'Abdruck ist geschlüsselt, nicht nachrechenbar');
        $this->assertNull(PurposeKeywordIndex::hash('12.03.2026 / 4711'));

        $transaction = $this->transaction('2026-01-03', '10.00', ['purpose' => 'Miete Büro Hauptstraße']);
        $raw = \Illuminate\Support\Facades\DB::table('bank_transactions')->where('id', $transaction->id)->first();
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('Miete', (string) $raw->purpose);
        $this->assertStringNotContainsString('miete', (string) $raw->purpose_keyword_hash);
        $this->assertSame(64, strlen((string) $raw->purpose_keyword_hash));
    }

    /** Vier-Augen-Prinzip: Die Bestätigung legt Entwürfe vor; geändertes Lernen bucht nichts Ungesehenes. */
    public function test_confirmation_respects_four_eyes_and_skips_stale_groups(): void {
        $mandate = ['mandate_ref' => 'MIETE-0815'];
        $this->bookRent($this->transaction('2026-01-03', '1190.00', $mandate));
        $first = $this->transaction('2026-02-03', '1190.00', $mandate);
        $second = $this->transaction('2026-03-03', '1190.00', $mandate);
        $group = $this->suggestions()->overview($this->org)['groups'][0];

        // Eine stale Gruppenkennung bucht nichts.
        $stale = $this->suggestions()->confirm($this->org, 'mandate-999999-1-1-0', [$first->id, $second->id], $this->admin);
        $this->assertSame(['posted' => 0, 'submitted' => 0, 'skipped' => 2, 'failed' => []], $stale);

        Setting::set(PostingInboxService::FOUR_EYES_KEY, true, SettingScope::Organization, $this->org);
        $result = $this->suggestions()->confirm($this->org, $group['key'], [$first->id, $second->id], $this->admin);

        $this->assertSame(0, $result['posted']);
        $this->assertSame(2, $result['submitted']);
        $this->assertFalse(AccountingEntry::query()->where('source_key', $this->inbox()->directKey($first))->sole()->status->isPosted());
    }

    /** Demo-Block: zwei gebuchte Monatsmieten, drei offene Umsätze als eine Gruppe mit Vorschlag. */
    public function test_demo_block_seeds_a_confirmable_group(): void {
        $demo = Organization::factory()->create();
        app()->instance('currentOrganization', $demo);
        $admin = User::factory()->admin()->create(['organization_id' => $demo->id]);
        $context = new \App\Services\Demo\Contracts\DemoSeedContext($demo, $admin, collect([$admin]), [], null, null, null);

        (new \App\Services\Finance\Demo\FinanceDemoBlock)->seed($context);
        $counts = (new \App\Services\Finance\Demo\BankBookingSuggestionDemoBlock)->seed($context);

        $this->assertSame(['bank_booking_suggestions' => 5], $counts);
        $overview = $this->suggestions()->overview($demo);
        $this->assertCount(1, $overview['groups']);
        $this->assertCount(3, $overview['groups'][0]['items']);
        $this->assertSame(BookingSuggestionBasis::Mandate, $overview['groups'][0]['suggestion']->basis);
    }

    public function test_page_and_confirmation_require_the_posting_permission(): void {
        $viewer = User::factory()->create(['organization_id' => $this->org->id]);

        $this->actingAs($viewer)->get(route('finance.accounting.inbox.suggestions.index'))->assertForbidden();
        $this->actingAs($viewer)->post(route('finance.accounting.inbox.suggestions.confirm'), [
            'group' => 'mandate-1-1-1-0',
            'transactions' => ['x'],
        ])->assertForbidden();
    }
}
