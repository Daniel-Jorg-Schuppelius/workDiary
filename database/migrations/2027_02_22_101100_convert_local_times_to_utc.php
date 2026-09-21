<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_22_101100_convert_local_times_to_utc.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * MVP-823: Termine, Wartungsfenster, Verleih, Portal-Terminanfragen,
 * Fahrzeugreservierungen und einige Fristen speicherten die Formulareingabe
 * als Ortszeit, verglichen aber mit UTC. Die Eingabe wird seither nach UTC
 * gewandelt; hier folgt der Bestand — je Zeile in der Zeitzone der
 * Organisation (Termine: eigene Zeitzone, falls gepflegt), sommerzeitgenau.
 *
 * Werte, die schon UTC waren, bleiben: aus Kalender-Importen übernommene
 * Termine, per now() geschriebene Zeitpunkte (Gerätetausch und
 * Folgebelegungen im Verleih, Wartungsende bei Abschluss/Rollback),
 * Calendly-Anfragen. Abgerechnete Tagebucheinträge bleiben unangetastet.
 * Herleitung: Umsetzungsplan Phase 105.
 */
return new class extends Migration {
    /** Ein per now() gesetztes Wartungsende liegt so nah an updated_at. */
    private const NOW_TOLERANCE_SECONDS = 5;

    private const CALENDAR_PLUGINS = ['caldav', 'google_calendar', 'msgraph'];

    /** @var array<int, string> */
    private array $zones = [];

    public function up(): void {
        $this->shift(true);
    }

    public function down(): void {
        $this->shift(false);
    }

    private function shift(bool $toUtc): void {
        if (! Schema::hasTable('organizations')) {
            return;
        }

        $this->events($toUtc);
        $this->maintenanceWindows($toUtc);
        $this->rental($toUtc);
        $this->portalAppointments($toUtc);

        $this->plain('vehicle_reservations', ['reserved_from', 'reserved_to'], $toUtc);
        $this->plain('asset_assignments', ['expected_return_at'], $toUtc);
        $this->plain('problems', ['effectiveness_check_due_at'], $toUtc);
        $this->plain('application_opportunities', ['opening_at'], $toUtc);
    }

    private function events(bool $toUtc): void {
        if (! Schema::hasTable('events')) {
            return;
        }

        // Import-Übernahmen tragen den Fremdstand bereits in UTC.
        $imported = Schema::hasTable('integration_inbox_items')
            ? DB::table('integration_inbox_items')
                ->whereIn('plugin_id', self::CALENDAR_PLUGINS)
                ->whereIn('status', ['resolved_created', 'resolved_remote'])
                ->where('resolved_to_type', 'App\\Models\\Event')
                ->whereNotNull('resolved_to_id')
                ->pluck('resolved_to_id')
                ->mapWithKeys(static fn($id): array => [(int) $id => true])
                ->all()
            : [];

        DB::table('events')->orderBy('id')->chunkById(500, function ($rows) use ($imported, $toUtc): void {
            foreach ($rows as $row) {
                if (isset($imported[(int) $row->id]) || ($row->series_id !== null && isset($imported[(int) $row->series_id]))) {
                    continue;
                }
                $own = $this->validZone($row->timezone);
                $zone = $own !== null && $own !== 'UTC' ? $own : $this->zoneOf($row->organization_id);

                $this->rewrite('events', $row, ['started_at', 'ended_at'], $zone, $toUtc);
                foreach (DB::table('event_room')->where('event_id', $row->id)->get() as $pivot) {
                    $this->rewrite('event_room', $pivot, ['started_at', 'ended_at'], $zone, $toUtc);
                }
            }
        });
    }

    private function maintenanceWindows(bool $toUtc): void {
        if (! Schema::hasTable('maintenance_windows')) {
            return;
        }

        DB::table('maintenance_windows')->orderBy('id')->chunkById(500, function ($rows) use ($toUtc): void {
            foreach ($rows as $row) {
                $columns = ['announce_from', 'starts_at'];
                // Rollback setzt das Ende immer per now(), ein manueller Abschluss ebenfalls; der Scan-Abschluss lässt es stehen.
                $endByNow = $row->status === 'rolled_back'
                    || ($row->status === 'completed' && $row->updated_at !== null
                        && abs(strtotime((string) $row->ends_at) - strtotime((string) $row->updated_at)) <= self::NOW_TOLERANCE_SECONDS);
                if (! $endByNow) {
                    $columns[] = 'ends_at';
                }
                $this->rewrite('maintenance_windows', $row, $columns, $this->zoneOf($row->organization_id), $toUtc);
            }
        });
    }

    private function rental(bool $toUtc): void {
        if (! Schema::hasTable('rental_cases')) {
            return;
        }

        // Belegungen vor den Akten: sie folgen der Akte nur, solange sie deren Zeiten tragen (Tausch/Folgebelegung = now()).
        DB::table('rental_reservations')->orderBy('id')->chunkById(500, function ($rows) use ($toUtc): void {
            $cases = DB::table('rental_cases')->whereIn('id', $rows->pluck('rental_case_id')->filter()->unique()->all())->get()->keyBy('id');
            foreach ($rows as $row) {
                $columns = ['starts_at', 'ends_at'];
                if ($row->rental_case_id !== null) {
                    $case = $cases->get($row->rental_case_id);
                    $columns = array_values(array_filter($columns, static fn(string $column): bool => $case !== null && $row->{$column} === $case->{$column}));
                }
                $this->rewrite('rental_reservations', $row, $columns, $this->zoneOf($row->organization_id), $toUtc);
            }
        });

        $this->plain('rental_cases', ['starts_at', 'ends_at'], $toUtc);
        $this->plain('rental_requests', ['starts_at', 'ends_at'], $toUtc);
    }

    private function portalAppointments(bool $toUtc): void {
        if (! Schema::hasTable('appointment_requests')) {
            return;
        }

        DB::table('appointment_requests')->where('source', 'portal')->orderBy('id')->chunkById(500, function ($rows) use ($toUtc): void {
            foreach ($rows as $row) {
                $zone = $this->zoneOf($row->organization_id);
                // Der bestätigte Eintrag erbte die Zeiten 1:1 — solange sie unverändert und nicht abgerechnet sind, mitziehen.
                $entry = $row->diary_entry_id !== null ? DB::table('diary_entries')->where('id', $row->diary_entry_id)->first() : null;
                if ($entry !== null && $entry->invoiced_at === null && $entry->invoice_reference === null) {
                    $columns = array_keys(array_filter([
                        'start_at' => $entry->start_at !== null && $entry->start_at === $row->start_at,
                        'end_at' => $entry->end_at !== null && $entry->end_at === $row->end_at,
                    ]));
                    $this->rewrite('diary_entries', $entry, $columns, $zone, $toUtc);
                }
                $this->rewrite('appointment_requests', $row, ['start_at', 'end_at'], $zone, $toUtc);
            }
        });
    }

    /** @param list<string> $columns */
    private function plain(string $table, array $columns, bool $toUtc): void {
        if (! Schema::hasTable($table)) {
            return;
        }

        DB::table($table)->orderBy('id')->chunkById(500, function ($rows) use ($table, $columns, $toUtc): void {
            foreach ($rows as $row) {
                $this->rewrite($table, $row, $columns, $this->zoneOf($row->organization_id), $toUtc);
            }
        });
    }

    /** @param list<string> $columns */
    private function rewrite(string $table, \stdClass $row, array $columns, string $zone, bool $toUtc): void {
        $changes = [];
        foreach ($columns as $column) {
            $raw = $row->{$column};
            if ($raw === null || $raw === '') {
                continue;
            }
            $value = $toUtc
                ? CarbonImmutable::parse((string) $raw, $zone)->utc()
                : CarbonImmutable::parse((string) $raw, 'UTC')->setTimezone($zone);
            $converted = $value->format('Y-m-d H:i:s');
            if ($converted !== $raw) {
                $changes[$column] = $converted;
            }
        }

        if ($changes !== []) {
            // Ohne updated_at: die Umrechnung ist keine fachliche Änderung.
            DB::table($table)->where('id', $row->id)->update($changes);
        }
    }

    private function zoneOf(mixed $organizationId): string {
        $fallback = $this->validZone((string) config('app.display_timezone')) ?? 'Europe/Berlin';
        if ($organizationId === null) {
            return $fallback;
        }

        return $this->zones[(int) $organizationId] ??= $this->validZone(DB::table('organizations')->where('id', $organizationId)->value('timezone')) ?? $fallback;
    }

    private function validZone(mixed $zone): ?string {
        return is_string($zone) && $zone !== '' && in_array($zone, timezone_identifiers_list(), true) ? $zone : null;
    }
};
