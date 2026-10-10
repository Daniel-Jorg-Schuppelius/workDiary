<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleCalendarImportFeed.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\GoogleCalendar\Services;

use App\Models\Platform\Organization;
use App\Plugins\GoogleCalendar\Api\GoogleCalendarClient;
use App\Plugins\GoogleCalendar\Models\GoogleCalendarConnection;
use App\Plugins\Support\Calendar\CalendarImportDocument;
use App\Services\Import\Contracts\CalendarImportFeed;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use RuntimeException;

/**
 * Google-Kalender als Quelle des Zeitimports (MVP-976). Google löst Serien
 * selbst auf (`singleEvents`); als Person gilt der eigene Teilnehmer
 * (`self`), sonst der Organisator.
 */
final class GoogleCalendarImportFeed implements CalendarImportFeed {
    public function __construct(private readonly CalendarImportDocument $document) {}

    public function key(): string {
        return 'google_calendar';
    }

    public function label(): string {
        return (string) __('google_calendar::google_calendar.title');
    }

    public function connections(Organization $organization): array {
        $connection = GoogleCalendarConnection::query()->where('organization_id', $organization->id)->first();
        if ($connection === null || ! $connection->isActive()) {
            return [];
        }

        return [['id' => Sqid::encode(GoogleCalendarConnection::class, $connection->id), 'label' => (string) ($connection->calendar_name ?? __('google_calendar::google_calendar.calendar.default'))]];
    }

    public function fetch(Organization $organization, string $connectionId, DateTimeImmutable $from, DateTimeImmutable $until): string {
        $connection = GoogleCalendarConnection::query()->where('organization_id', $organization->id)
            ->whereKey(Sqid::decode(GoogleCalendarConnection::class, $connectionId))->first();
        if ($connection === null || ! $connection->isActive()) {
            throw new RuntimeException((string) __('import.error.calendar.unknown'));
        }

        $events = [];
        foreach ((new GoogleCalendarClient($connection))->eventsBetween($from, $until) as $item) {
            $start = $item['start'] ?? null;
            if (($item['status'] ?? '') === 'cancelled' || ! is_array($start)) {
                continue;
            }
            $allDay = isset($start['date']);
            $self = null;
            foreach ((array) ($item['attendees'] ?? []) as $attendee) {
                if (is_array($attendee) && ($attendee['self'] ?? false) === true && is_string($attendee['email'] ?? null)) {
                    $self = $attendee['email'];
                }
            }
            $events[] = [
                'uid' => (string) ($item['iCalUID'] ?? $item['id'] ?? '') . '#' . (string) ($item['id'] ?? ''),
                'title' => (string) ($item['summary'] ?? ''),
                'description' => is_string($item['description'] ?? null) ? $item['description'] : null,
                'start' => $this->moment($start, $allDay),
                'end' => is_array($item['end'] ?? null) ? $this->moment($item['end'], $allDay) : null,
                'all_day' => $allDay,
                'transparent' => ($item['transparency'] ?? '') === 'transparent',
                'categories' => [],
                'organizer' => $self ?? (is_string($item['organizer']['email'] ?? null) ? $item['organizer']['email'] : null),
            ];
        }

        return $this->document->fromEvents($events);
    }

    /** @param array<string, mixed> $node */
    private function moment(array $node, bool $allDay): DateTimeImmutable {
        return $allDay
            ? CarbonImmutable::parse((string) $node['date'], (string) config('app.timezone', 'Europe/Berlin'))->toDateTimeImmutable()
            : CarbonImmutable::parse((string) ($node['dateTime'] ?? ''))->utc()->toDateTimeImmutable();
    }
}
