<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphCalendarImportFeed.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Services;

use App\Models\Platform\Organization;
use App\Models\Plugins\Msgraph\MsgraphConnection;
use App\Plugins\Msgraph\Api\MsgraphCalendarClient;
use App\Plugins\Support\Calendar\CalendarImportDocument;
use App\Services\Import\Contracts\CalendarImportFeed;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use RuntimeException;

/**
 * Microsoft-365-Kalender als Quelle des Zeitimports (MVP-976): `calendarView`
 * liefert Serien bereits als Vorkommen; Outlook-Kategorien bleiben erhalten
 * (Kategorie-Filter des Imports), „frei“ gilt als transparent.
 */
final class MsgraphCalendarImportFeed implements CalendarImportFeed {
    public function __construct(private readonly CalendarImportDocument $document) {}

    public function key(): string {
        return 'msgraph';
    }

    public function label(): string {
        return 'Microsoft 365';
    }

    public function connections(Organization $organization): array {
        $connection = MsgraphConnection::query()->where('organization_id', $organization->id)->first();
        if ($connection === null || ! $connection->isActive()) {
            return [];
        }

        return [['id' => Sqid::encode(MsgraphConnection::class, $connection->id), 'label' => (string) ($connection->calendar_name ?? 'Microsoft 365')]];
    }

    public function fetch(Organization $organization, string $connectionId, DateTimeImmutable $from, DateTimeImmutable $until): string {
        $connection = MsgraphConnection::query()->where('organization_id', $organization->id)
            ->whereKey(Sqid::decode(MsgraphConnection::class, $connectionId))->first();
        if ($connection === null || ! $connection->isActive()) {
            throw new RuntimeException((string) __('import.error.calendar.unknown'));
        }

        $events = [];
        foreach ((new MsgraphCalendarClient($connection))->calendarView($from, $until) as $item) {
            if (($item['isCancelled'] ?? false) === true || ! is_array($item['start'] ?? null)) {
                continue;
            }
            $allDay = (bool) ($item['isAllDay'] ?? false);
            $events[] = [
                'uid' => (string) ($item['iCalUId'] ?? $item['id'] ?? '') . '#' . (string) ($item['id'] ?? ''),
                'title' => (string) ($item['subject'] ?? ''),
                'description' => is_string($item['bodyPreview'] ?? null) ? $item['bodyPreview'] : null,
                'start' => $this->moment($item['start']),
                'end' => is_array($item['end'] ?? null) ? $this->moment($item['end']) : null,
                'all_day' => $allDay,
                'transparent' => ($item['showAs'] ?? '') === 'free',
                'categories' => array_values(array_filter((array) ($item['categories'] ?? []), 'is_string')),
                'organizer' => is_string($item['organizer']['emailAddress']['address'] ?? null) ? $item['organizer']['emailAddress']['address'] : null,
            ];
        }

        return $this->document->fromEvents($events);
    }

    /** @param array<string, mixed> $node */
    private function moment(array $node): DateTimeImmutable {
        return CarbonImmutable::parse((string) ($node['dateTime'] ?? ''), (string) ($node['timeZone'] ?? 'UTC'))->utc()->toDateTimeImmutable();
    }
}
