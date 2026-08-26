<?php
/*
 * Created on   : Mon Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SeedQueryLoadCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Support;

use App\Models\{Customer, DiaryEntry, Organization, User};
use App\Services\Billing\{DocumentFeedFilters, DocumentFeedQuery};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Messdatensatz für das Lastprofil der Listen-/Zeitraumabfragen (Vollscan
 * 2026-08-23, A8/A13/A14, MVP-722) — Schwester des `accounting:seed-load`.
 *
 * **Nur für Messungen, nie für Produktivdaten.** Die Zeilen entstehen per
 * Sammel-Insert ohne Modelle, ohne Ereignisse und ohne Hash-Kette; die
 * erzeugten `audit_logs` tragen deshalb bewusst keine Kettenhashes und würden
 * `audit:verify` brechen. Das Kommando verweigert in der Produktivumgebung den
 * Dienst.
 *
 * Mit `--explain` läuft anschließend die Messung der Brennpunkte inklusive
 * EXPLAIN — erst messen, dann optimieren.
 */
class SeedQueryLoadCommand extends Command {
    protected $signature = 'perf:seed-load
        {--orgs=3 : Anzahl Organisationen}
        {--time-entries=50000 : Zeiteinträge insgesamt}
        {--audit-logs=100000 : Audit-Zeilen insgesamt}
        {--diary-entries=30000 : Tagebucheinträge insgesamt}
        {--travel-logs=20000 : Fahrten insgesamt}
        {--invoices=8000 : Ausgangsrechnungen insgesamt}
        {--quotes=5000 : Angebote insgesamt}
        {--explain : Brennpunkte messen und EXPLAIN ausgeben}
        {--only= : Nur diese Messfälle (Komma-Liste)}
        {--force : In nicht-lokaler Umgebung erzwingen}';

    protected $description = 'Legt einen Messdatensatz für Listen-/Zeitraumabfragen an und misst die Brennpunkte (MVP-722)';

    /** Sammel-Insert in Blöcken — ein Insert je Zeile wäre um Größenordnungen langsamer. */
    private const CHUNK = 1000;

    /** Streuung der Messdaten über die letzten Jahre. */
    private const SPREAD_DAYS = 1095;

    /** @var list<int> */
    private array $organizationIds = [];

    /** @var list<int> */
    private array $userIds = [];

    /** @var list<int> */
    private array $customerIds = [];

    /** @var list<int> */
    private array $projectIds = [];

    public function handle(): int {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('In der Produktivumgebung gesperrt — der Datensatz ist ein Messwerkzeug, kein Bestand.');

            return self::FAILURE;
        }

        $this->resolveScaffold(max(1, (int) $this->option('orgs')));
        if ($this->organizationIds === [] || $this->userIds === []) {
            $this->error('Weder Organisationen noch Benutzer vorhanden und keine anlegbar.');

            return self::FAILURE;
        }

        $this->seedTimeEntries((int) $this->option('time-entries'));
        $this->seedAuditLogs((int) $this->option('audit-logs'));
        $this->seedDiaryEntries((int) $this->option('diary-entries'));
        $this->seedTravelLogs((int) $this->option('travel-logs'));
        $this->seedInvoices((int) $this->option('invoices'));
        $this->seedQuotes((int) $this->option('quotes'));

        $this->warn('Hinweis: Sammel-Insert ohne Modelle/Ereignisse — die erzeugten audit_logs tragen keine Hash-Kette. Nur für Messungen.');

        if ($this->option('explain')) {
            $this->measure();
        }

        return self::SUCCESS;
    }

    /** Organisationen, Benutzer, Kunden und Projekte sicherstellen. */
    private function resolveScaffold(int $wantedOrgs): void {
        $existing = Organization::query()->orderBy('id')->limit($wantedOrgs)->pluck('id')->all();
        while (count($existing) < $wantedOrgs) {
            $existing[] = Organization::factory()->create()->id;
        }
        $this->organizationIds = array_values(array_map('intval', $existing));

        foreach ($this->organizationIds as $organizationId) {
            $user = User::query()->where('organization_id', $organizationId)->orderBy('id')->first()
                ?? User::factory()->create(['organization_id' => $organizationId]);
            $this->userIds[] = (int) $user->id;

            $customer = Customer::withoutGlobalScopes()->where('organization_id', $organizationId)->orderBy('id')->first()
                ?? Customer::factory()->create(['organization_id' => $organizationId]);
            $this->customerIds[] = (int) $customer->id;
        }

        $this->projectIds = array_values(array_map('intval', DB::table('projects')
            ->whereIn('organization_id', $this->organizationIds)
            ->orderBy('id')
            ->limit(20)
            ->pluck('id')
            ->all()));
    }

    /** Rundlauf über die Organisationen — jede Org bekommt denselben Anteil. */
    private function slot(int $index): int {
        return $index % count($this->organizationIds);
    }

    private function dateFor(int $index): CarbonImmutable {
        return CarbonImmutable::now()->startOfDay()->subDays($index % self::SPREAD_DAYS);
    }

    /**
     * Schreibt $target Zeilen blockweise; $row liefert die Zeile zum Index.
     *
     * @param  callable(int, int): array<string, mixed>  $row  (Index, Org-Slot) → Zeile
     */
    private function bulkInsert(string $table, int $target, callable $row): void {
        if ($target <= 0) {
            return;
        }

        $this->line(sprintf('  %-16s %7d Zeilen …', $table, $target));
        for ($offset = 0; $offset < $target; $offset += self::CHUNK) {
            $rows = [];
            $batch = min(self::CHUNK, $target - $offset);
            for ($i = 0; $i < $batch; $i++) {
                $index = $offset + $i;
                $rows[] = $row($index, $this->slot($index));
            }
            DB::table($table)->insert($rows);
        }
    }

    private function seedTimeEntries(int $target): void {
        $this->bulkInsert('time_entries', $target, function (int $index, int $slot): array {
            $date = $this->dateFor($index);

            return [
                'organization_id' => $this->organizationIds[$slot],
                'user_id' => $this->userIds[$slot],
                'project_id' => $this->projectIds[$index % max(1, count($this->projectIds))] ?? null,
                'date' => $date->toDateString(),
                'started_at' => $date->addHours(8)->toDateTimeString(),
                'ended_at' => $date->addHours(9)->toDateTimeString(),
                'minutes' => 60 + ($index % 240),
                'billable' => $index % 3 === 0 ? 0 : 1,
                'description' => 'Messdatensatz ' . $index,
                'created_at' => $date->toDateTimeString(),
                'updated_at' => $date->toDateTimeString(),
            ];
        });
    }

    private function seedAuditLogs(int $target): void {
        $this->bulkInsert('audit_logs', $target, function (int $index, int $slot): array {
            // Nachmittag statt Mitternacht: 02:00–03:00 existiert an der
            // Sommerzeit-Umstellung nicht, MariaDB lehnt den Wert für eine
            // timestamp-Spalte ab.
            $at = $this->dateFor($index)->addHours(12)->addMinutes($index % 700);

            return [
                'organization_id' => $this->organizationIds[$slot],
                'user_id' => $this->userIds[$slot],
                'event' => ['created', 'updated', 'deleted'][$index % 3],
                'auditable_type' => 'App\\Models\\TimeEntry',
                'auditable_id' => $index + 1,
                'changes' => null,
                'created_at' => $at->toDateTimeString(),
                'updated_at' => $at->toDateTimeString(),
            ];
        });
    }

    private function seedDiaryEntries(int $target): void {
        $modes = ['fixed', 'deadline', 'window', 'backlog'];

        $this->bulkInsert('diary_entries', $target, function (int $index, int $slot) use ($modes): array {
            $date = $this->dateFor($index);
            $mode = $modes[$index % count($modes)];

            return [
                'organization_id' => $this->organizationIds[$slot],
                'user_id' => $this->userIds[$slot],
                'mode' => $mode,
                'content' => 'Messdatensatz ' . $index,
                'scheduled_for' => $date->toDateString(),
                'start_at' => $mode === 'fixed' ? $date->addHours(8)->toDateTimeString() : null,
                'end_at' => $mode === 'fixed' ? $date->addHours(10)->toDateTimeString() : null,
                'due_date' => $mode === 'deadline' ? $date->toDateString() : null,
                'window_start_date' => $mode === 'window' ? $date->toDateString() : null,
                'window_end_date' => $mode === 'window' ? $date->addDays(5)->toDateString() : null,
                'created_at' => $date->toDateTimeString(),
                'updated_at' => $date->toDateTimeString(),
            ];
        });
    }

    private function seedTravelLogs(int $target): void {
        $this->bulkInsert('travel_logs', $target, function (int $index, int $slot): array {
            $date = $this->dateFor($index);

            return [
                'organization_id' => $this->organizationIds[$slot],
                'user_id' => $this->userIds[$slot],
                'date' => $date->toDateString(),
                'distance_km' => 10 + ($index % 90),
                'duration_minutes' => 15 + ($index % 120),
                'purpose' => 'Messdatensatz ' . $index,
                'created_at' => $date->toDateTimeString(),
                'updated_at' => $date->toDateTimeString(),
            ];
        });
    }

    private function seedInvoices(int $target): void {
        $states = ['draft', 'sent', 'paid'];

        $this->bulkInsert('invoices', $target, function (int $index, int $slot) use ($states): array {
            $date = $this->dateFor($index);
            $status = $states[$index % count($states)];

            return [
                'organization_id' => $this->organizationIds[$slot],
                'customer_id' => $this->customerIds[$slot],
                'number' => 'LOAD-INV-' . $index,
                'status' => $status,
                'type' => 'invoice',
                'issued_on' => $date->toDateString(),
                'due_on' => $date->addDays(14)->toDateString(),
                'paid_on' => $status === 'paid' ? $date->addDays(10)->toDateString() : null,
                'subtotal' => 100 + ($index % 900),
                'tax_amount' => 19,
                'total' => 119 + ($index % 900),
                'created_at' => $date->toDateTimeString(),
                'updated_at' => $date->toDateTimeString(),
            ];
        });
    }

    private function seedQuotes(int $target): void {
        $states = ['draft', 'sent', 'accepted', 'rejected'];

        $this->bulkInsert('quotes', $target, function (int $index, int $slot) use ($states): array {
            $date = $this->dateFor($index);

            return [
                'organization_id' => $this->organizationIds[$slot],
                'customer_id' => $this->customerIds[$slot],
                'number' => 'LOAD-QT-' . $index,
                'version' => 1,
                'status' => $states[$index % count($states)],
                'valid_until' => $date->addDays(30)->toDateString(),
                'subtotal' => 100 + ($index % 900),
                'tax_amount' => 19,
                'total' => 119 + ($index % 900),
                'created_at' => $date->toDateTimeString(),
                'updated_at' => $date->toDateTimeString(),
            ];
        });
    }

    /**
     * Brennpunkte messen und die dabei erzeugten Abfragen erklären lassen.
     */
    private function measure(): void {
        $organizationId = $this->organizationIds[0];
        $userId = $this->userIds[0];
        $projectId = $this->projectIds[0] ?? null;
        $to = CarbonImmutable::now()->startOfDay();
        $from = $to->subDays(90);

        $cases = [
            // A8 — Datumsbereiche auf indizierten Spalten.
            'diaryOverlap' => fn (): mixed => DiaryEntry::withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->overlappingDateRange($from->toDateString(), $to->toDateString())
                ->orderByDesc('start_at')
                ->limit(50)
                ->get(),
            'travelLogRange' => fn (): mixed => DB::table('travel_logs')
                ->where('organization_id', $organizationId)
                ->whereBetween('date', DateRange::days($from, $to))
                ->orderByDesc('date')
                ->limit(50)
                ->get(),
            'timeExportRange' => fn (): mixed => DB::table('time_entries')
                ->where('organization_id', $organizationId)
                ->whereBetween('date', DateRange::days($from, $to))
                ->sum('minutes'),
            // A14 — Kombi-Index-Kandidaten.
            'timeEntriesByUser' => fn (): mixed => DB::table('time_entries')
                ->where('user_id', $userId)
                ->whereBetween('date', DateRange::days($from, $to))
                ->sum('minutes'),
            'timeEntriesByProject' => fn (): mixed => $projectId === null ? null : DB::table('time_entries')
                ->where('project_id', $projectId)
                ->whereBetween('date', DateRange::days($from, $to))
                ->sum('minutes'),
            'auditLogList' => fn (): mixed => DB::table('audit_logs')
                ->where('organization_id', $organizationId)
                ->orderByDesc('created_at')
                ->limit(50)
                ->get(),
            'quotesByStatus' => fn (): mixed => DB::table('quotes')
                ->where('organization_id', $organizationId)
                ->where('status', 'sent')
                ->orderByDesc('id')
                ->limit(50)
                ->get(),
            // A13 — Belegfluss: Kennzahlen, Tab-Zähler und Seite.
            'documentFeed' => fn (): mixed => $this->documentFeed($organizationId, $userId, $from, $to),
        ];

        $only = array_values(array_filter(explode(',', (string) $this->option('only'))));
        if ($only !== []) {
            $cases = array_intersect_key($cases, array_flip($only));
        }

        $this->newLine();
        $this->line('<info>Messung</info> (Zeitraum ' . $from->toDateString() . ' – ' . $to->toDateString() . ')');

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

            $this->line(sprintf('  %-20s %8.1f ms  %3d Abfragen', $name, $elapsed, count($queries)));

            foreach ($this->slowest($queries) as $query) {
                $this->line(sprintf('    %6.1f ms  %s', $query['time'], mb_substr((string) $query['sql'], 0, 140)));
                foreach ($this->explain($query) as $row) {
                    $this->line('      EXPLAIN: ' . $row);
                }
            }
        }
    }

    /**
     * Belegfluss wie die Seite ihn aufruft: Seite + Kennzahlen + Tab-Zähler.
     *
     * @return array{rows: int, totals: int, counts: int}
     */
    private function documentFeed(int $organizationId, int $userId, CarbonImmutable $from, CarbonImmutable $to): array {
        $filters = new DocumentFeedFilters(
            organizationId: $organizationId,
            userId: $userId,
            from: $from,
            to: $to,
            sources: ['invoice' => true, 'quote' => true, 'voucher' => true, 'incoming_einvoice' => true, 'expense' => true],
        );
        $feed = new DocumentFeedQuery($filters);

        return [
            'rows' => $feed->paginate(30, 'date', 'desc')->count(),
            'totals' => count($feed->totals()),
            'counts' => count($feed->tabCounts()),
        ];
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
