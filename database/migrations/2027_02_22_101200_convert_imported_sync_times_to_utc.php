<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_22_101200_convert_imported_sync_times_to_utc.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * MVP-824: Die CSV-Importe von Toggl, Kimai und Clockify lasen die
 * Export-Uhrzeit als UTC, obwohl sie Ortszeit ist; der Kimai-API-Import
 * speicherte die Wanduhr des gelieferten Offsets. Seit der Umstellung wird
 * UTC gespeichert — hier folgen die importierten Zeiteinträge und offene
 * Zuordnungsfälle, je Zeile in der Zeitzone der Organisation.
 *
 * Unverändert bleiben exportierte Zeiten, Zeiten in eingereichten/
 * freigegebenen/gesperrten Monaten und Zeiten, die nach dem Import von Hand
 * umgestellt wurden (die Formulareingabe ist bereits UTC). Umgerechnete
 * Datensätze tragen eine Markierung, an der `down()` sie wiederfindet.
 * Fingerabdrücke schreibt der nächste Abgleich um (RemoteTimeFingerprint::
 * ofWallTime) — sie sind Hashes über den Fremdstand und ohne ihn nicht neu
 * zu bilden. Herleitung: Umsetzungsplan Phase 105.
 */
return new class extends Migration {
    private const MARK = 'utc_migrated_mvp824';

    private const PLUGINS = ['toggl', 'kimai', 'clockify'];

    private const LOCKED_MONTHS = ['submitted', 'approved', 'locked'];

    private const TOLERANCE_SECONDS = 5;

    /** @var array<int, string> */
    private array $zones = [];

    public function up(): void {
        $this->shift(true);
    }

    public function down(): void {
        $this->shift(false);
    }

    private function shift(bool $toUtc): void {
        if (! Schema::hasTable('external_references') || ! Schema::hasTable('time_entries')) {
            return;
        }

        $this->timeEntries($toUtc);
        $this->openInboxSnapshots($toUtc);
    }

    private function timeEntries(bool $toUtc): void {
        DB::table('external_references')
            ->whereIn('plugin_id', self::PLUGINS)
            ->where('external_type', 'entry')
            ->where('referenceable_type', 'App\\Models\\TimeEntry')
            ->orderBy('id')
            ->chunkById(500, function ($references) use ($toUtc): void {
                $entries = DB::table('time_entries')->whereIn('id', $references->pluck('referenceable_id')->all())->get()->keyBy('id');
                foreach ($references as $reference) {
                    $entry = $entries->get($reference->referenceable_id);
                    $payload = json_decode((string) $reference->payload, true);
                    $payload = is_array($payload) ? $payload : [];
                    if ($entry === null || $entry->started_at === null || $entry->ended_at === null) {
                        continue;
                    }
                    $marked = ! empty($payload[self::MARK]);
                    if ($toUtc ? ($marked || ! $this->storedAsWallTime($reference) || ! $this->mayCorrect($entry, $reference)) : ! $marked) {
                        continue;
                    }

                    $zone = $this->zoneOf($entry->organization_id);
                    DB::table('time_entries')->where('id', $entry->id)->update([
                        'started_at' => $this->convert((string) $entry->started_at, $zone, $toUtc),
                        'ended_at' => $this->convert((string) $entry->ended_at, $zone, $toUtc),
                    ]);

                    if ($toUtc) {
                        $payload[self::MARK] = true;
                    } else {
                        unset($payload[self::MARK]);
                    }
                    DB::table('external_references')->where('id', $reference->id)->update(['payload' => json_encode($payload)]);
                }
            });
    }

    /** CSV-Schlüssel: Ortszeit als UTC gelesen. Kimai-API: Wanduhr des Kimai-Offsets (angenommen = Org-Zeitzone). */
    private function storedAsWallTime(\stdClass $reference): bool {
        $key = (string) $reference->external_id;

        return str_starts_with($key, 'csv:') || ($reference->plugin_id === 'kimai' && str_starts_with($key, 'api:'));
    }

    private function mayCorrect(\stdClass $entry, \stdClass $reference): bool {
        if ((bool) $entry->exported) {
            return false;
        }

        $day = CarbonImmutable::parse((string) $entry->date);
        if (Schema::hasTable('month_closures') && DB::table('month_closures')
            ->where('organization_id', $entry->organization_id)
            ->where('user_id', $entry->user_id)
            ->where('period_year', $day->year)
            ->where('period_month', $day->month)
            ->whereIn('status', self::LOCKED_MONTHS)
            ->exists()) {
            return false;
        }

        return ! $this->retimedByHand($entry, $reference);
    }

    /**
     * Von einer Person umgestellte Zeiten nach dem Import. Beim CSV-Abgleich
     * (Upload durch eine Person) liegt die eigene Änderung am synced_at der
     * Referenz — die ist Wanduhr und wird mit umgerechnet.
     */
    private function retimedByHand(\stdClass $entry, \stdClass $reference): bool {
        if (! Schema::hasTable('audit_logs') || $reference->created_at === null) {
            return false;
        }

        $importedAt = CarbonImmutable::parse((string) $reference->created_at);
        $syncedAt = $reference->synced_at !== null ? CarbonImmutable::parse((string) $reference->synced_at) : null;
        $csv = str_starts_with((string) $reference->external_id, 'csv:');

        $changes = DB::table('audit_logs')
            ->where('auditable_type', 'App\\Models\\TimeEntry')
            ->where('auditable_id', $entry->id)
            ->where('event', 'updated')
            ->whereNotNull('user_id')
            ->where('created_at', '>', $importedAt->addSeconds(self::TOLERANCE_SECONDS))
            ->get(['changes', 'created_at']);

        foreach ($changes as $change) {
            $decoded = json_decode((string) $change->changes, true);
            $after = is_array($decoded) && is_array($decoded['after'] ?? null) ? $decoded['after'] : [];
            if (! array_key_exists('started_at', $after) && ! array_key_exists('ended_at', $after)) {
                continue;
            }
            if ($csv && $syncedAt !== null && abs(CarbonImmutable::parse((string) $change->created_at)->diffInSeconds($syncedAt)) <= self::TOLERANCE_SECONDS) {
                continue;
            }

            return true;
        }

        return false;
    }

    /** Offene Zuordnungsfälle aus CSV-Importen tragen die Uhrzeit als UTC-ISO — die Buchung aus der Inbox übernähme sie. */
    private function openInboxSnapshots(bool $toUtc): void {
        if (! Schema::hasTable('integration_inbox_items')) {
            return;
        }

        DB::table('integration_inbox_items')
            ->whereIn('plugin_id', self::PLUGINS)
            ->where('status', 'open')
            ->orderBy('id')
            ->chunkById(500, function ($items) use ($toUtc): void {
                foreach ($items as $item) {
                    $snapshot = json_decode((string) $item->remote_snapshot, true);
                    if (! is_array($snapshot) || ! str_starts_with((string) ($snapshot['entry_key'] ?? ''), 'csv:')
                        || ! is_string($snapshot['started_at'] ?? null) || ! is_string($snapshot['ended_at'] ?? null)) {
                        continue;
                    }
                    $marked = ! empty($snapshot[self::MARK]);
                    if ($toUtc === $marked) {
                        continue;
                    }

                    $zone = $this->zoneOf($item->organization_id);
                    foreach (['started_at', 'ended_at'] as $key) {
                        $wall = CarbonImmutable::parse($snapshot[$key])->utc()->format('Y-m-d H:i:s');
                        $snapshot[$key] = CarbonImmutable::parse($this->convert($wall, $zone, $toUtc), 'UTC')->toIso8601String();
                    }
                    if ($toUtc) {
                        $snapshot[self::MARK] = true;
                    } else {
                        unset($snapshot[self::MARK]);
                    }

                    DB::table('integration_inbox_items')->where('id', $item->id)->update([
                        'remote_snapshot' => json_encode($snapshot),
                        'occurred_at' => $item->occurred_at !== null ? $this->convert((string) $item->occurred_at, $zone, $toUtc) : null,
                    ]);
                }
            });
    }

    private function convert(string $raw, string $zone, bool $toUtc): string {
        $value = $toUtc
            ? CarbonImmutable::parse($raw, $zone)->utc()
            : CarbonImmutable::parse($raw, 'UTC')->setTimezone($zone);

        return $value->format('Y-m-d H:i:s');
    }

    private function zoneOf(mixed $organizationId): string {
        $fallback = $this->validZone(config('app.display_timezone')) ?? 'Europe/Berlin';
        if ($organizationId === null) {
            return $fallback;
        }

        return $this->zones[(int) $organizationId] ??= $this->validZone(DB::table('organizations')->where('id', $organizationId)->value('timezone')) ?? $fallback;
    }

    private function validZone(mixed $zone): ?string {
        return is_string($zone) && $zone !== '' && in_array($zone, timezone_identifiers_list(), true) ? $zone : null;
    }
};
