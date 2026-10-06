<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApplicationOpportunityStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Applications;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Stand einer Ausschreibungsakte (Feature 068, MVP-184): Bearbeitung bis zur Abgabe, danach die Entscheidung. */
enum ApplicationOpportunityStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Captured = 'captured';
    case Screened = 'screened';
    case InProgress = 'in_progress';
    case Question = 'question';
    case Submitted = 'submitted';
    case PostSubmission = 'post_submission';
    case Won = 'won';
    case Lost = 'lost';
    case Withdrawn = 'withdrawn';

    /** Im Schema vorgesehen, heute von keinem Code geschrieben. */
    case Archived = 'archived';

    /**
     * Offene (Pipeline-)Status — Entscheidungen sind endgültig.
     *
     * @return list<self>
     */
    public static function open(): array {
        return [self::Captured, self::Screened, self::InProgress, self::Question, self::Submitted, self::PostSubmission];
    }

    /**
     * Bearbeitungsstände, die sich an der Akte von Hand setzen lassen —
     * „eingereicht“ setzt nur die Abgabe.
     *
     * @return list<self>
     */
    public static function working(): array {
        return [self::Captured, self::Screened, self::InProgress, self::Question, self::PostSubmission];
    }

    public function label(): string {
        return (string) __('values.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Won => 'success',
            self::Lost => 'error',
            self::Question => 'warning',
            self::InProgress => 'primary',
            self::Submitted, self::PostSubmission => 'info',
            self::Withdrawn, self::Archived => 'neutral',
            self::Captured, self::Screened => 'ghost',
        };
    }

    /**
     * Eine offene Akte wechselt frei zwischen den Bearbeitungsständen; die
     * erneute Abgabe (Status bleibt) prüft der Aufrufer selbst.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Captured, self::Screened, self::InProgress, self::Question, self::Submitted, self::PostSubmission => array_values(array_filter(
                [...self::open(), self::Won, self::Lost, self::Withdrawn],
                fn (self $target): bool => $target !== $this,
            )),
            self::Won, self::Lost, self::Withdrawn, self::Archived => [],
        };
    }
}
