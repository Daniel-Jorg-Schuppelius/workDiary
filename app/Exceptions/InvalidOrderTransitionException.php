<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvalidOrderTransitionException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Exceptions;

use App\Enums\Diary\Status;
use RuntimeException;

class InvalidOrderTransitionException extends RuntimeException {
    public static function forAction(Status $status, string $action): self {
        return new self((string) __('Die Aktion „:action“ ist im Status „:status“ nicht möglich.', [
            'action' => $action,
            'status' => $status->label(),
        ]));
    }

    /** @param list<string> $names Personen mit laufender Stoppuhr auf dem Auftrag */
    public static function stopwatchRunning(array $names): self {
        return new self((string) __('diary.lifecycle.error.stopwatch_running', ['names' => implode(', ', $names)]));
    }

    /** Vier Augen (Feature 198): wer vorgelegt hat, prüft nicht selbst. */
    public static function ownReview(): self {
        return new self((string) __('diary.review.error.own_submission'));
    }

    /** Die Prüfpflicht wechselt nur vor der Vorlage, nie während der Prüfung oder nach dem Abschluss. */
    public static function reviewRequirementLocked(Status $status): self {
        return new self((string) __('diary.review.error.requirement_locked', ['status' => $status->label()]));
    }
}
