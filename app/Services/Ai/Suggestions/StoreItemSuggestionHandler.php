<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StoreItemSuggestionHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Suggestions;

use App\Services\Ai\Contracts\AiResultHandlerInterface;
use App\Services\Ai\Dto\AiInvocationResult;

/**
 * Ergebnis-Handler der Sammelaktion (Feature 084, MVP-403): persistiert
 * asynchron erzeugte Vorschläge. Idempotent — ein Queue-Retry ersetzt
 * den offenen Vorschlag derselben Position. Terminale Fehlschläge
 * bleiben bewusst still: die Sammelaktion ist Best-Effort, der
 * Beleg-Workflow hängt nie an der KI (Feature 025, Leitprinzip 7).
 */
class StoreItemSuggestionHandler implements AiResultHandlerInterface {
    public function __construct(private readonly ItemTextSuggestionService $suggestions) {}

    public function handleAiResult(AiInvocationResult $result, array $context): void {
        $organizationId = (int) ($context['organization_id'] ?? 0);
        $subjectType = (string) ($context['subject_type'] ?? '');
        $subjectId = (int) ($context['subject_id'] ?? 0);

        if ($organizationId === 0 || $subjectType === '' || $subjectId === 0) {
            return;
        }

        $this->suggestions->storeSuggestion(
            $organizationId,
            $subjectType,
            $subjectId,
            (string) ($context['original'] ?? ''),
            $result,
            isset($context['user_id']) ? (int) $context['user_id'] : null,
        );
    }

    public function handleAiFailure(string $reason, array $context): void {
        // Best-Effort: kein Vorschlag, kein Fehler im Beleg-Workflow.
    }
}
