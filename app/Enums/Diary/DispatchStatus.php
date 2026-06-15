<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DispatchStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Diary;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Dispositionsstatus eines Auftrags (Feature 028 — Terminierung/Disposition).
 *
 * Bewusst getrennt von der fachlichen Auftrags-Statusmaschine ({@see Status}):
 * Status beschreibt den Lebenszyklus (geplant → angenommen → in Arbeit …),
 * DispatchStatus beschreibt ausschließlich den Disponier-Fortschritt
 * (ist der Auftrag terminiert, einem Mitarbeiter zugewiesen, bestätigt,
 * unterwegs, erledigt). Der effektive Wert wird vom
 * {@see \App\Services\Dispatch\DispatchStatusResolver} aus den vorhandenen
 * Planungsfeldern abgeleitet bzw. aus der Spalte diary_entries.dispatch_status
 * gelesen, ohne dass die WIP-Modellklasse DiaryEntry angefasst werden muss.
 */
enum DispatchStatus: string implements HasLabel {
    use HasOptions;

    case Unplanned = 'unplanned';
    case Planned = 'planned';
    case Confirmed = 'confirmed';
    case EnRoute = 'enRoute';
    case Done = 'done';

    public function label(): string {
        return (string) __('enums.diary.dispatch_status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Unplanned => 'neutral',
            self::Planned => 'open',
            self::Confirmed => 'progress',
            self::EnRoute => 'progress',
            self::Done => 'done',
        };
    }

    /** Rangfolge für Vergleiche/Übergänge. */
    public function rank(): int {
        return match ($this) {
            self::Unplanned => 0,
            self::Planned => 1,
            self::Confirmed => 2,
            self::EnRoute => 3,
            self::Done => 4,
        };
    }

    /**
     * Erlaubte Folge-Status (lineare Disposition mit Rücksprung auf Planung).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Unplanned => [self::Planned],
            self::Planned => [self::Unplanned, self::Confirmed],
            self::Confirmed => [self::Planned, self::EnRoute, self::Done],
            self::EnRoute => [self::Confirmed, self::Done],
            self::Done => [],
        };
    }

    public function canTransitionTo(self $target): bool {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
