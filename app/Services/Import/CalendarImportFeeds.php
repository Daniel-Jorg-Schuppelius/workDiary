<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendarImportFeeds.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Platform\Organization;
use App\Modules\ModuleRegistry;
use App\Services\Import\Contracts\CalendarImportFeed;
use DateTimeImmutable;
use RuntimeException;

/**
 * Die Kalenderverbindungen aller {@see CalendarImportFeed}s unter einer
 * Quellenkennung `<quelle>:<verbindung>` — für den Einmalimport (MVP-976) und
 * das Kalender-Abo (MVP-1531). Angeboten wird nur, was der Feed für die
 * Organisation herausgibt; der Feed prüft beim Abruf die Organisation erneut.
 */
class CalendarImportFeeds {
    public function __construct(private readonly ModuleRegistry $modules) {}

    /** @return list<array{value: string, label: string}> */
    public function sources(Organization $organization): array {
        $sources = [];
        foreach ($this->feeds() as $feed) {
            foreach ($feed->connections($organization) as $connection) {
                $sources[] = ['value' => $feed->key() . ':' . $connection['id'], 'label' => $feed->label() . ' · ' . $connection['label']];
            }
        }

        return $sources;
    }

    /** @throws RuntimeException unbekannte Quelle oder Kalender nicht abrufbar */
    public function fetch(Organization $organization, string $source, DateTimeImmutable $from, DateTimeImmutable $until): string {
        [$key, $connectionId] = array_pad(explode(':', $source, 2), 2, '');
        foreach ($this->feeds() as $feed) {
            if ($feed->key() === $key) {
                return $feed->fetch($organization, $connectionId, $from, $until);
            }
        }

        throw new RuntimeException((string) __('import.error.calendar.unknown'));
    }

    /** @return iterable<CalendarImportFeed> */
    private function feeds(): iterable {
        foreach ($this->modules->extensions(CalendarImportFeed::class) as $class) {
            $feed = app($class);
            if ($feed instanceof CalendarImportFeed) {
                yield $feed;
            }
        }
    }
}
