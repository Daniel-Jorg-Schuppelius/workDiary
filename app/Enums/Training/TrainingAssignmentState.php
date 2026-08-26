<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingAssignmentState.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Training;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Abgeleiteter Zustand eines Soll-Eintrags (Feature 145) — keine Spalte,
 * sondern die Auswertung von `due_at`/`fulfilled_at` gegen den Stichtag.
 * Die Sperrwirkung bleibt beim Qualifikationsstatus (Feature 013); dieser
 * Zustand ist reine Anzeige/Auswertung.
 */
enum TrainingAssignmentState: string implements HasLabel {
    use HasOptions;

    /** Nachweis vorhanden und (falls Wiederholung) noch außerhalb des Vorlaufs. */
    case Fulfilled = 'fulfilled';
    /** Termin liegt in der Zukunft, aber noch außerhalb des Vorlaufs. */
    case Planned = 'planned';
    /** Termin innerhalb des Vorlaufs — der Fristen-Scan meldet. */
    case Due = 'due';
    /** Termin überschritten. */
    case Overdue = 'overdue';

    public function label(): string {
        return (string) __('enums.training.assignment-state.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Fulfilled => 'success',
            self::Planned => 'ghost',
            self::Due => 'warning',
            self::Overdue => 'error',
        };
    }
}
