<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingGuardsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Enums\Finance\{AccountType, AccountingEntryStatus, ProfitDetermination};
use App\Models\Accounting\{AccountingAccount, AccountingEntry};
use App\Models\User;
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsService, FiscalYearService, JournalService};
use App\Services\Accounting\Posting\PostingInboxService;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Sicherheitsscan 2026-08-23, S-30 und S-31 — beide am selben Ort:
 * `JournalService::post()` ist die Stelle, durch die **jede** Festschreibung
 * läuft. Die Vier-Augen-Kontrolle saß daneben (nur in der Buchungs-Inbox),
 * und der Zustand wurde vor der Transaktion auf einem womöglich veralteten
 * Modell geprüft.
 */
class AccountingGuardsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private CarbonImmutable $startsOn;

    private AccountingAccount $bank;

    private AccountingAccount $revenue;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->startsOn = CarbonImmutable::create(2026, 1, 1);
        app(AccountingProfileService::class)->configure($this->organization, [
            'profit_determination' => ProfitDetermination::DoubleEntry,
            'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1,
            'starts_on' => $this->startsOn,
            'note' => null,
        ]);
        app(FiscalYearService::class)->create($this->organization, $this->startsOn);
        app(AccountingProfileService::class)->activateLocal($this->organization, $admin);

        $accounts = app(ChartOfAccountsService::class);
        $this->bank = $accounts->create($this->organization, ['number' => '1200', 'name' => 'Bank', 'type' => AccountType::Asset, 'is_bank' => true]);
        $this->revenue = $accounts->create($this->organization, ['number' => '8400', 'name' => 'Erlöse 19 %', 'type' => AccountType::Income]);
    }

    public function test_vier_augen_gilt_auch_ueber_das_journal(): void {
        // Vorher: die Inbox blockte das Selbst-Festschreiben, das Journal
        // nicht — dieselbe Person öffnete den Eintrag dort und klickte
        // „Festschreiben".
        $this->organization->forceFill([
            'settings' => ['finance' => ['accounting_four_eyes' => true]],
        ])->save();

        $this->assertTrue(
            (bool) \App\Support\Setting::get(PostingInboxService::FOUR_EYES_KEY, false),
            'Vier-Augen-Einstellung wurde nicht übernommen.'
        );

        $vorbereiter = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $entry = $this->draftEntry($vorbereiter);

        $this->expectException(ValidationException::class);
        app(JournalService::class)->post($entry, $vorbereiter);
    }

    public function test_eine_zweite_person_darf_festschreiben(): void {
        // Gegenprobe: die Kontrolle sperrt nur den Vorbereiter.
        $this->organization->forceFill([
            'settings' => ['finance' => ['accounting_four_eyes' => true]],
        ])->save();

        $vorbereiter = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $zweite = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $entry = $this->draftEntry($vorbereiter);

        $posted = app(JournalService::class)->post($entry, $zweite);

        $this->assertSame(AccountingEntryStatus::Posted, $posted->status);
        $this->assertNotNull($posted->journal_no);
    }

    public function test_zweite_festschreibung_derselben_buchung_wird_abgewiesen(): void {
        // S-31: Der zweite Lauf lief auf einem veralteten Modell durch und
        // überschrieb die Zeile mit der nächsten Journalnummer — die erste
        // fehlte danach in der lückenlosen Nummerierung.
        $actor = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $entry = $this->draftEntry($actor);

        $stale = AccountingEntry::query()->whereKey($entry->getKey())->firstOrFail();
        $stale->load('lines.account');

        $service = app(JournalService::class);
        $service->post($entry, $actor);

        $this->expectException(ValidationException::class);
        $service->post($stale, $actor);
    }

    private function draftEntry(User $creator): AccountingEntry {
        $entry = app(JournalService::class)->draft($this->organization, [
            'booked_on' => $this->startsOn->addMonth(),
            'memo' => 'Zahlungseingang Rechnung 2026-001',
            'source_key' => null,
            'lines' => [
                ['accounting_account_id' => $this->bank->id, 'debit' => '119.00', 'credit' => '0.00'],
                ['accounting_account_id' => $this->revenue->id, 'debit' => '0.00', 'credit' => '119.00'],
            ],
        ], $creator);

        return $entry->fresh(['lines.account']) ?? $entry;
    }

    // ── S-29 · Doppelfakturierung ───────────────────────────────────────

    public function test_zweiter_nachweis_ueber_dieselben_quellen_wird_abgewiesen(): void {
        // Zwei Entwürfe für denselben Kunden und Zeitraum enthalten dieselben
        // Zeiten. Die Reservierungsprüfung lief nur beim Anlegen — beide
        // ließen sich bestätigen und übertragen, der Kunde bekam die Stunden
        // doppelt berechnet.
        $kunde = \App\Models\Customer::factory()->create(['organization_id' => $this->organization->id]);
        $von = CarbonImmutable::create(2026, 5, 1);
        $bis = $von->endOfMonth();

        $projekt = \App\Models\Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $kunde->id,
        ]);

        \App\Models\TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $projekt->id,
            'date' => $von->addDays(4)->toDateString(),
            'minutes' => 480,
            'billable' => true,
            'exported' => false,
        ]);

        $service = app(\App\Services\Finance\BillingTransferService::class);

        $ersterEntwurf = $service->createDraft(
            $kunde,
            \App\Enums\Finance\TransferChannel::Time,
            \App\Enums\Finance\TransferTarget::File,
            ['from' => $von, 'to' => $bis],
        );
        $zweiterEntwurf = $service->createDraft(
            $kunde,
            \App\Enums\Finance\TransferChannel::Time,
            \App\Enums\Finance\TransferTarget::File,
            ['from' => $von, 'to' => $bis],
        );

        $service->confirm($ersterEntwurf);

        $this->expectException(\App\Services\Finance\BillingTransferException::class);
        $service->confirm($zweiterEntwurf->fresh());
    }

    // ── S-32 · Freigegebener Monat ──────────────────────────────────────

    public function test_freigegebener_monat_nimmt_keine_neuen_zeiten(): void {
        // Bis dahin kannte die harte Sperre nur `exported` und den
        // Stundenzettel. Wer seinen Juni freigegeben bekommen hatte, konnte
        // danach Reisezeit im Juni **anlegen** — Gleitzeitsaldo und Lohnzeilen
        // werden zur Exportzeit gerechnet und stiegen mit.
        $mitarbeiter = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $juni = CarbonImmutable::create(2026, 6, 15);

        \App\Models\MonthClosure::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $mitarbeiter->id,
            'period_year' => 2026,
            'period_month' => 6,
            'status' => \App\Enums\TimeApproval\MonthClosureStatus::Approved,
        ]);

        $eintrag = \App\Models\TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $mitarbeiter->id,
            'date' => $juni->toDateString(),
            'minutes' => 60,
        ]);

        $sperre = app(\App\Services\Timekeeping\TimeEntryEditPolicy::class)->isHardLocked($eintrag);

        $this->assertTrue($sperre['locked'], 'Der freigegebene Monat sperrt den Eintrag nicht.');
        $this->assertSame(
            \App\Services\Timekeeping\TimeEntryEditPolicy::REASON_MONTH_CLOSED,
            $sperre['reason'],
        );
    }

    public function test_offener_monat_bleibt_bearbeitbar(): void {
        // Gegenprobe: ohne Freigabe greift nur das übliche Korrekturfenster.
        $mitarbeiter = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $eintrag = \App\Models\TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $mitarbeiter->id,
            'date' => CarbonImmutable::now()->toDateString(),
            'minutes' => 60,
        ]);

        $this->assertFalse(app(\App\Services\Timekeeping\TimeEntryEditPolicy::class)->isHardLocked($eintrag)['locked']);
    }

}
