<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GuardsApprovalWorkflow.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\TimeApproval\Concerns;

use App\Enums\Contracts\HasStatusTransitions;
use App\Services\TimeApproval\TimeApprovalWorkflowException;
use BackedEnum;

/**
 * Status- und Begründungsprüfung der Zeitfreigabe-Workflows, vorher je Dienst
 * kopiert (Konsolidierungs-Audit 2026-10-21, ko-06). Der Dienst nennt seine
 * Ausnahme (`WORKFLOW_EXCEPTION`), die Mindestlänge (`REASON_MIN_LENGTH`)
 * und seine Meldungstexte.
 */
trait GuardsApprovalWorkflow {
    abstract private function illegalStatusMessage(string $status): string;

    abstract private function reasonTooShortMessage(int $minLength): string;

    private function assertWorkflowTransition(HasStatusTransitions&BackedEnum $current, HasStatusTransitions&BackedEnum $target): void {
        if (in_array($target, $current->allowedTransitions(), true)) {
            return;
        }

        throw $this->workflowException('illegalTransition', $this->illegalStatusMessage((string) $current->value), [
            'from' => $current->value,
            'to' => $target->value,
        ]);
    }

    private function assertReason(string $reason): void {
        if (mb_strlen(trim($reason)) < self::REASON_MIN_LENGTH) {
            throw $this->workflowException('reasonTooShort', $this->reasonTooShortMessage(self::REASON_MIN_LENGTH), ['min' => self::REASON_MIN_LENGTH]);
        }
    }

    /** @param array<string, mixed> $context */
    private function workflowException(string $reasonCode, string $message, array $context): TimeApprovalWorkflowException {
        $class = self::WORKFLOW_EXCEPTION;

        return new $class($reasonCode, $message, $context);
    }
}
