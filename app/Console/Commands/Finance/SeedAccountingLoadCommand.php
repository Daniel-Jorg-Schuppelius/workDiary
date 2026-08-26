<?php
/*
 * Created on   : Sat Aug 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SeedAccountingLoadCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Finance;

use App\Enums\Finance\{AccountType, AccountingEntryStatus, OpenItemDirection, OpenItemStatus, ProfitDetermination};
use App\Models\Accounting\{AccountingAccount, AccountingEntry, AccountingFiscalYear, AccountingPeriod, AccountingProfile};
use App\Models\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsService, FiscalYearService, OpenItemService};
use App\Services\Accounting\Reports\{AccountLedgerBuilder, LiquidityBuilder, TrialBalanceBuilder};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Messdatensatz für das Lastprofil der lokalen Buchhaltung (Feature 125,
 * MVP-683).
 *
 * **Nur für Messungen, nie für Produktivdaten.** Die Buchungen entstehen per
 * Sammel-Insert, ohne Buchungsdienst, ohne Ereignisse und ohne
 * Nachweis-Snapshot — sie taugen für Laufzeitmessungen, nicht für einen
 * Prüflauf. Deshalb bricht das Kommando in der Produktivumgebung ab.
 *
 * Mit `--explain` läuft anschließend eine Messung der Kernberichte inklusive
 * EXPLAIN der dabei erzeugten Abfragen: Erst messen, dann optimieren.
 */
class SeedAccountingLoadCommand extends Command {
    protected $signature = 'accounting:seed-load
        {--organization= : ID der Organisation (Standard: erste)}
        {--years=5 : Anzahl Geschäftsjahre}
        {--entries=30000 : Anzahl Buchungen insgesamt}
        {--open-items=8000 : Anzahl offener Posten}
        {--explain : Kernberichte messen und EXPLAIN ausgeben}
        {--force : In nicht-lokaler Umgebung erzwingen}';

    protected $description = 'Legt einen Messdatensatz der lokalen Buchhaltung an und misst die Kernberichte (MVP-683)';

    /** Sammel-Insert in Blöcken — ein Insert je Zeile wäre um Größenordnungen langsamer. */
    private const CHUNK = 500;

    public function handle(): int {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('In der Produktivumgebung gesperrt — der Datensatz ist ein Messwerkzeug, kein Bestand.');

            return self::FAILURE;
        }

        $organization = $this->organization();
        if (! $organization instanceof Organization) {
            $this->error('Keine Organisation gefunden.');

            return self::FAILURE;
        }

        $actor = User::query()->where('organization_id', $organization->id)->orderBy('id')->first();
        if (! $actor instanceof User) {
            $this->error('Keine Benutzerin/kein Benutzer in der Organisation.');

            return self::FAILURE;
        }

        $years = max(1, (int) $this->option('years'));
        $target = max(1, (int) $this->option('entries'));
        $openItems = max(0, (int) $this->option('open-items'));

        $start = $this->prepare($organization, $actor, $years);
        $accounts = $this->accounts($organization);

        $this->info(sprintf('Erzeuge %d Buchungen über %d Geschäftsjahre …', $target, $years));
        $written = $this->seedEntries($organization, $accounts, $start, $years, $target, $openItems, $actor);

        $this->info(sprintf(
            'Fertig: %d Buchungen, %d Zeilen, %d offene Posten.',
            $written['entries'],
            $written['lines'],
            $written['open_items'],
        ));
        $this->warn('Hinweis: Diese Buchungen tragen keinen Nachweis-Snapshot und keine Ereignisse — nur für Messungen.');

        if ($this->option('explain')) {
            $this->measure($organization, $start, $start->addYears($years)->subDay());
        }

        return self::SUCCESS;
    }

    private function organization(): ?Organization {
        $id = $this->option('organization');

        return $id === null
            ? Organization::query()->orderBy('id')->first()
            : Organization::query()->find((int) $id);
    }

    /** Profil, Geschäftsjahre und Konten sicherstellen. */
    private function prepare(Organization $organization, User $actor, int $years): CarbonImmutable {
        $start = CarbonImmutable::now()->startOfYear()->subYears($years - 1);

        $profiles = app(AccountingProfileService::class);
        $fresh = AccountingProfile::query()->where('organization_id', $organization->id)->doesntExist();
        if ($fresh) {
            $profiles->configure($organization, [
                'profit_determination' => ProfitDetermination::DoubleEntry,
                'base_currency' => CurrencyCode::Euro,
                'fiscal_year_start_month' => 1,
                'starts_on' => $start,
                'note' => 'Messdatensatz (MVP-683)',
            ]);
        }

        $fiscalYears = app(FiscalYearService::class);
        for ($i = 0; $i < $years; $i++) {
            $from = $start->addYears($i);
            $exists = AccountingFiscalYear::query()
                ->where('organization_id', $organization->id)
                ->where('starts_on', DateRange::day($from))
                ->exists();

            if (! $exists) {
                $fiscalYears->create($organization, $from);
            }
        }

        // Erst nach den Geschäftsjahren: Ohne Periode gäbe es keinen Ort für
        // die erste Buchung.
        if ($fresh) {
            $profiles->activateLocal($organization, $actor);
        }

        return $start;
    }

    /**
     * Konten des Messdatensatzes.
     *
     * @return array<string, AccountingAccount>
     */
    private function accounts(Organization $organization): array {
        $chart = app(ChartOfAccountsService::class);
        $wanted = [
            'bank' => ['1200', 'Bank', AccountType::Asset, ['is_bank' => true]],
            'receivable' => ['1400', 'Forderungen', AccountType::Asset, ['is_open_item' => true]],
            'payable' => ['1600', 'Verbindlichkeiten', AccountType::Liability, ['is_open_item' => true]],
            'revenue' => ['8400', 'Erlöse', AccountType::Income, []],
            'expense' => ['4900', 'Aufwendungen', AccountType::Expense, []],
        ];

        $accounts = [];
        foreach ($wanted as $key => [$number, $name, $type, $flags]) {
            $accounts[$key] = AccountingAccount::query()
                ->where('organization_id', $organization->id)
                ->where('number', $number)
                ->first() ?? $chart->create($organization, ['number' => $number, 'name' => $name, 'type' => $type] + $flags);
        }

        return $accounts;
    }

    /**
     * @param  array<string, AccountingAccount>  $accounts
     * @return array{entries: int, lines: int, open_items: int}
     */
    private function seedEntries(Organization $organization, array $accounts, CarbonImmutable $start, int $years, int $target, int $openItems, User $actor): array {
        $periods = AccountingPeriod::query()
            ->where('organization_id', $organization->id)
            ->orderBy('starts_on')
            ->get();

        if ($periods->isEmpty()) {
            $this->error('Keine Perioden vorhanden.');

            return ['entries' => 0, 'lines' => 0, 'open_items' => 0];
        }

        $nextJournalNo = (int) DB::table('accounting_entries')
            ->where('organization_id', $organization->id)
            ->max('journal_no') + 1;

        // Der Idempotenzschlüssel muss über mehrere Läufe eindeutig bleiben —
        // sonst bricht der zweite Aufruf am Unique-Index ab.
        $keyBase = $nextJournalNo;

        $currency = CurrencyCode::Euro->value;
        $written = ['entries' => 0, 'lines' => 0, 'open_items' => 0];
        $bar = $this->output->createProgressBar($target);
        $bar->start();

        for ($offset = 0; $offset < $target; $offset += self::CHUNK) {
            $batch = min(self::CHUNK, $target - $offset);
            $entryRows = [];
            $meta = [];

            for ($i = 0; $i < $batch; $i++) {
                $index = $offset + $i;
                $period = $periods[$index % $periods->count()];
                if (! $period instanceof AccountingPeriod) {
                    continue;
                }

                $bookedOn = CarbonImmutable::parse($period->starts_on)->addDays($index % 27);
                $amount = number_format(50 + ($index % 950) + (($index % 7) / 10), 2, '.', '');

                $entryRows[] = [
                    'organization_id' => $organization->id,
                    'accounting_fiscal_year_id' => $period->accounting_fiscal_year_id,
                    'accounting_period_id' => $period->id,
                    'journal_no' => $nextJournalNo++,
                    'booked_on' => $bookedOn->toDateString(),
                    'document_on' => $bookedOn->toDateString(),
                    'status' => AccountingEntryStatus::Posted->value,
                    'memo' => 'Messdatensatz ' . $index,
                    'document_reference' => 'LOAD-' . $index,
                    'currency' => $currency,
                    'source_key' => 'load:' . ($keyBase + $index),
                    'created_by' => $actor->id,
                    'posted_by' => $actor->id,
                    'posted_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $meta[] = ['index' => $index, 'key' => 'load:' . ($keyBase + $index), 'amount' => $amount, 'booked_on' => $bookedOn];
            }

            DB::table('accounting_entries')->insert($entryRows);
            $written['entries'] += $batch;

            $ids = DB::table('accounting_entries')
                ->where('organization_id', $organization->id)
                ->whereIn('source_key', array_column($meta, 'key'))
                ->pluck('id', 'source_key');

            $lineRows = [];
            $itemRows = [];

            foreach ($meta as $row) {
                $entryId = $ids[$row['key']] ?? null;
                if ($entryId === null) {
                    continue;
                }

                // Abwechselnd Erlös- und Aufwandsvorgang: beide Richtungen der
                // Offenen-Posten-Projektion kommen vor.
                $isSale = $row['index'] % 2 === 0;
                $money = $isSale ? $accounts['receivable'] : $accounts['payable'];
                $result = $isSale ? $accounts['revenue'] : $accounts['expense'];

                $lineRows[] = $this->lineRow($organization, $entryId, 1, $money->id, $isSale ? $row['amount'] : '0.00', $isSale ? '0.00' : $row['amount'], $currency);
                $lineRows[] = $this->lineRow($organization, $entryId, 2, $result->id, $isSale ? '0.00' : $row['amount'], $isSale ? $row['amount'] : '0.00', $currency);

                if ($written['open_items'] < $openItems) {
                    $itemRows[] = [
                        'organization_id' => $organization->id,
                        'accounting_entry_id' => $entryId,
                        'accounting_entry_line_id' => 0,
                        'accounting_account_id' => $money->id,
                        'direction' => ($isSale ? OpenItemDirection::Receivable : OpenItemDirection::Payable)->value,
                        'status' => OpenItemStatus::Open->value,
                        'document_reference' => 'LOAD-' . $row['index'],
                        'document_date' => $row['booked_on']->toDateString(),
                        'due_date' => $row['booked_on']->addDays(30)->toDateString(),
                        'currency' => $currency,
                        'original_amount' => $row['amount'],
                        'open_amount' => $row['amount'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $written['open_items']++;
                }
            }

            DB::table('accounting_entry_lines')->insert($lineRows);
            $written['lines'] += count($lineRows);

            if ($itemRows !== []) {
                // Der offene Posten hängt an der Geldzeile — ohne sie wäre er
                // eine Forderung ohne Buchungsstelle.
                $lineIds = DB::table('accounting_entry_lines')
                    ->whereIn('accounting_entry_id', array_column($itemRows, 'accounting_entry_id'))
                    ->where('line_no', 1)
                    ->pluck('id', 'accounting_entry_id');

                foreach ($itemRows as $position => $item) {
                    $itemRows[$position]['accounting_entry_line_id'] = $lineIds[$item['accounting_entry_id']] ?? 0;
                }

                DB::table('accounting_open_items')->insert($itemRows);
            }

            $bar->advance($batch);
        }

        $bar->finish();
        $this->newLine();

        return $written;
    }

    /** @return array<string, mixed> */
    private function lineRow(Organization $organization, int $entryId, int $lineNo, int $accountId, string $debit, string $credit, string $currency): array {
        return [
            'organization_id' => $organization->id,
            'accounting_entry_id' => $entryId,
            'line_no' => $lineNo,
            'accounting_account_id' => $accountId,
            'debit' => $debit,
            'credit' => $credit,
            'currency' => $currency,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Kernberichte messen und die dabei erzeugten Abfragen erklären lassen.
     *
     * Ohne Messung bleibt jede Optimierung eine Vermutung — und eine
     * materialisierte Saldentabelle wäre ein zweiter Bestand.
     */
    private function measure(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): void {
        $trialBalances = app(TrialBalanceBuilder::class);
        $accountLedgers = app(AccountLedgerBuilder::class);
        $liquidities = app(LiquidityBuilder::class);
        $openItems = app(OpenItemService::class);
        $account = AccountingAccount::query()->where('organization_id', $organization->id)->where('number', '1400')->first();

        $cases = [
            'trialBalance' => fn () => $trialBalances->build($organization, $from, $to),
            // Wie die Seite es aufruft (geblättert) und wie der Export (alles).
            'accountLedger' => fn () => $account instanceof AccountingAccount
                ? $accountLedgers->build($organization, $account, $from, $to, 100)
                : null,
            'accountLedgerFull' => fn () => $account instanceof AccountingAccount
                ? $accountLedgers->build($organization, $account, $from, $to)
                : null,
            'liquidity' => fn () => $liquidities->build($organization, $to),
            // Journalliste wie auf der Seite: erste Seite mit Zeilen und Konten.
            'journal' => fn () => AccountingEntry::query()
                ->where('organization_id', $organization->id)
                ->with(['lines.account', 'postedBy'])
                ->whereBetween('booked_on', DateRange::days($from, $to))
                ->orderByDesc('journal_no')
                ->limit(50)
                ->get(),
            // Wie die Seite (geblättert) und wie die Kennzahl (nur Bänder).
            'openItemAging' => fn () => $openItems->aging($organization, OpenItemDirection::Receivable, 50),
            'openItemBuckets' => fn () => $openItems->aging($organization, OpenItemDirection::Receivable, withItems: false),
        ];

        $this->newLine();
        $this->line('<info>Messung</info> (Zeitraum ' . $from->toDateString() . ' – ' . $to->toDateString() . ')');

        // Genau ein Zuhörer für alle Fälle — je Fall einen zu registrieren
        // würde jede Abfrage mehrfach zählen.
        /** @var list<array<string, mixed>> $queries */
        $queries = [];
        $recording = new \ArrayObject(['on' => false]);
        DB::listen(static function ($query) use (&$queries, $recording): void {
            if ($recording['on'] === true) {
                $queries[] = ['sql' => $query->sql, 'bindings' => $query->bindings, 'time' => $query->time];
            }
        });

        foreach ($cases as $name => $case) {
            // Ein Aufwärmlauf: Die erste Abfrage misst den kalten Puffer der
            // Datenbank, nicht das Verhalten der Anwendung.
            $case();

            $queries = [];
            $recording['on'] = true;

            $startedAt = microtime(true);
            $case();
            $elapsed = (microtime(true) - $startedAt) * 1000;
            $recording['on'] = false;

            $this->line(sprintf('  %-16s %8.1f ms  %3d Abfragen', $name, $elapsed, count($queries)));

            foreach ($this->slowest($queries) as $query) {
                $this->line(sprintf('    %6.1f ms  %s', $query['time'], mb_substr((string) $query['sql'], 0, 120)));
                foreach ($this->explain($query) as $row) {
                    $this->line('      EXPLAIN: ' . $row);
                }
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $queries
     * @return list<array<string, mixed>>
     */
    private function slowest(array $queries): array {
        usort($queries, static fn (array $a, array $b): int => $b['time'] <=> $a['time']);

        return array_slice($queries, 0, 2);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<string>
     */
    private function explain(array $query): array {
        if (! str_starts_with(strtolower(trim((string) $query['sql'])), 'select')) {
            return [];
        }

        try {
            $rows = DB::select('EXPLAIN ' . $query['sql'], (array) $query['bindings']);
        } catch (\Throwable $exception) {
            return ['nicht verfügbar (' . $exception->getMessage() . ')'];
        }

        return array_values(array_map(static function (object $row): string {
            $data = (array) $row;

            return implode(' | ', array_map(
                static fn (string $key): string => $key . '=' . (string) ($data[$key] ?? '—'),
                array_values(array_filter(array_keys($data), static fn (string $key): bool => in_array($key, ['table', 'type', 'key', 'rows', 'Extra'], true))),
            ));
        }, $rows));
    }
}
