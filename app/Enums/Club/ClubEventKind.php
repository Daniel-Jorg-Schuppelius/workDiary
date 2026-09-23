<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubEventKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;
use App\Enums\Event\EventType;

/**
 * Terminart im Verein (Feature 159, MVP-843): Training/Probe, Lehrgang,
 * Prüfung, Versammlung, sonstiger Termin. Der Termin bleibt ein `Event`;
 * die Art bestimmt nur die Darstellung und den abgeleiteten Event-Typ.
 */
enum ClubEventKind: string implements HasLabel {
    use HasOptions;

    case Training = 'training';
    case Course = 'course';
    case Exam = 'exam';
    case Meeting = 'meeting';
    case Match = 'match';
    case Competition = 'competition';
    case Other = 'other';

    public function label(): string {
        return (string) __('enums.club.event-kind.' . $this->value);
    }

    public function icon(): string {
        return match ($this) {
            self::Training => 'fitness_center',
            self::Course => 'school',
            self::Exam => 'workspace_premium',
            self::Meeting => 'groups',
            self::Match => 'sports_soccer',
            self::Competition => 'emoji_events',
            self::Other => 'event',
        };
    }

    /** Abgeleiteter Typ des zugrunde liegenden Events (Feature 028). */
    public function eventType(): EventType {
        return match ($this) {
            self::Training => EventType::Training,
            self::Course, self::Exam => EventType::Workshop,
            self::Meeting => EventType::Meeting,
            self::Other, self::Match, self::Competition => EventType::InternalBriefing,
        };
    }
}
