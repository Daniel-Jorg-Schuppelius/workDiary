<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShortfallStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/** Tagesbefund einer Unterschreitung des Projekt-Solls (Feature 194). */
enum ShortfallStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';

    public function label(): string {
        return (string) __('enums.project.shortfall-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Open => 'warning',
            self::Acknowledged => 'info',
            self::Resolved => 'success',
        };
    }

    public function allowedTransitions(): array {
        return match ($this) {
            self::Open => [self::Acknowledged, self::Resolved],
            // Ein bestätigter Befund bleibt bestätigt, nur eine Nachbuchung erledigt ihn.
            self::Acknowledged => [self::Resolved],
            // Fällt die gebuchte Zeit wieder unter das Soll, öffnet der Lauf den Befund erneut.
            self::Resolved => [self::Open],
        };
    }
}
