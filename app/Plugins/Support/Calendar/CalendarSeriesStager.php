<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendarSeriesStager.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Calendar;

use App\Models\Calendar\Event;
use App\Models\Integration\IntegrationInboxItem;
use DateTimeInterface;

/**
 * Serientermine im Kalender-Rückimport (MVP-977): Jedes Vorkommen im
 * Importfenster wird ein eigener Vorschlag (`calendar-proposal:<uid>:<Beginn>`),
 * abweichende und abgesagte Einzeltermine sind damit richtig. Die Inbox bucht
 * oder verwirft eine Serie als Gruppe ({@see CalendarSeriesGroupBooker}).
 */
final class CalendarSeriesStager {
    /** Obergrenze je Serie, damit eine tägliche Serie die Inbox nicht flutet. */
    public const MAX_OCCURRENCES = 60;

    /** Vorkommen über den ursprünglichen Beginn oder — wo der Anbieter sie vergibt — die eigene ID des Vorkommens. */
    public static function key(string $uid, DateTimeInterface|string $occurrence): string {
        return 'calendar-proposal:' . $uid . ':' . ($occurrence instanceof DateTimeInterface ? (string) $occurrence->getTimestamp() : $occurrence);
    }

    /**
     * Legt die Vorkommen einer Serie an und verwirft offene Vorschläge der
     * Serie, die es nicht mehr gibt (abgesagt, Serie gekürzt).
     *
     * @param  list<array{key: string, title: string, snapshot: array<string, mixed>, mapped: array<string, mixed>}>  $occurrences
     * @return int neue Vorschläge
     */
    public function stageSeries(int $organizationId, string $pluginId, string $uid, string $subtitle, array $occurrences): int {
        $new = 0;
        $keys = [];
        foreach (array_slice($occurrences, 0, self::MAX_OCCURRENCES) as $occurrence) {
            $keys[] = $occurrence['key'];
            $item = IntegrationInboxItem::query()->firstOrCreate(
                ['organization_id' => $organizationId, 'plugin_id' => $pluginId, 'dedupe_key' => $occurrence['key']],
                $this->attributes($pluginId, $subtitle, $occurrence),
            );
            $new += $item->wasRecentlyCreated ? 1 : 0;
        }

        IntegrationInboxItem::query()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', $pluginId)
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->whereLikeEscaped('dedupe_key', 'calendar-proposal:' . $uid . ':', 'prefix')
            ->whereNotIn('dedupe_key', $keys)
            ->update(['status' => IntegrationInboxItem::STATUS_DISMISSED, 'resolved_at' => now()]);

        return $new;
    }

    /**
     * Abweichender Einzeltermin, der ohne seine Serie kommt: offenen Vorschlag
     * aktualisieren oder neu anlegen.
     *
     * @param  array{key: string, title: string, snapshot: array<string, mixed>, mapped: array<string, mixed>}  $occurrence
     * @return bool true = neuer Vorschlag
     */
    public function upsertOccurrence(int $organizationId, string $pluginId, string $subtitle, array $occurrence): bool {
        $item = IntegrationInboxItem::query()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', $pluginId)
            ->where('dedupe_key', $occurrence['key'])
            ->first();
        if ($item === null) {
            IntegrationInboxItem::query()->create(['organization_id' => $organizationId, 'plugin_id' => $pluginId, 'dedupe_key' => $occurrence['key']] + $this->attributes($pluginId, $subtitle, $occurrence));

            return true;
        }
        if ($item->status === IntegrationInboxItem::STATUS_OPEN) {
            $item->update([
                'remote_snapshot' => $occurrence['snapshot'],
                'mapped_snapshot' => $occurrence['mapped'],
                'display_title' => $occurrence['title'] !== '' ? $occurrence['title'] : '—',
            ]);
        }

        return false;
    }

    /** Gelöschtes Vorkommen, von dem nur die eigene ID bekannt ist (Graph `@removed`). */
    public function dismissByRemoteId(int $organizationId, string $pluginId, string $remoteId): void {
        IntegrationInboxItem::query()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', $pluginId)
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->whereLikeEscaped('dedupe_key', ':' . $remoteId, 'suffix')
            ->update(['status' => IntegrationInboxItem::STATUS_DISMISSED, 'resolved_at' => now()]);
    }

    /** Abgesagtes Vorkommen: ein offener Vorschlag entfällt. */
    public function dismiss(int $organizationId, string $pluginId, string $key): void {
        IntegrationInboxItem::query()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', $pluginId)
            ->where('dedupe_key', $key)
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->update(['status' => IntegrationInboxItem::STATUS_DISMISSED, 'resolved_at' => now()]);
    }

    /**
     * @param  array{key: string, title: string, snapshot: array<string, mixed>, mapped: array<string, mixed>}  $occurrence
     * @return array<string, mixed>
     */
    private function attributes(string $pluginId, string $subtitle, array $occurrence): array {
        return [
            'source' => $pluginId,
            'target_type' => (new Event)->getMorphClass(),
            'external_type' => RemoteCalendarPublishService::EXTERNAL_TYPE,
            // Je Vorkommen eigene Fremd-ID: eine angenommene Instanz bindet genau einen Termin.
            'external_id' => substr($occurrence['key'], strlen('calendar-proposal:')),
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxItem::STATUS_OPEN,
            'remote_snapshot' => $occurrence['snapshot'],
            'mapped_snapshot' => $occurrence['mapped'],
            'display_title' => $occurrence['title'] !== '' ? $occurrence['title'] : '—',
            'display_subtitle' => $subtitle,
            'occurred_at' => now(),
        ];
    }
}
