<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IcalEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Source\Ical;

/**
 * Ein aus einem `VEVENT` extrahiertes, bereits in die Org-Zeitzone überführtes
 * Ereignis (MVP-438). Die Zeit-/Datumsfelder sind lokale Wanduhrzeit
 * (`date` = `Y-m-d`, `startTime`/`endTime` = `H:i`), damit die
 * {@see \App\Services\Import\Source\IcalEventMapper} formatneutral auf die
 * kanonischen Spalten der Spezifikation abbilden kann.
 */
final readonly class IcalEvent {
    /**
     * @param  list<string>  $categories
     */
    public function __construct(
        public string $uid,
        public ?string $date,
        public ?string $startTime,
        public ?string $endTime,
        public string $summary,
        public string $description,
        public ?string $email,
        public array $categories,
        public bool $allDay,
        public bool $transparent,
        public bool $recurring,
    ) {}

    public function hasTime(): bool {
        return $this->date !== null && $this->startTime !== null;
    }
}
