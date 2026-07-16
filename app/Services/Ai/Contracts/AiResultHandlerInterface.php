<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiResultHandlerInterface.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

use App\Services\Ai\Dto\AiInvocationResult;

/**
 * Empfänger asynchroner KI-Ergebnisse (MVP-399): Capability-Consumer
 * (z. B. Feature 084 Leistungstexte) implementieren diesen Vertrag und
 * werden vom {@see \App\Jobs\Ai\AiInvocationJob} über den Container
 * aufgelöst. Handler müssen idempotent sein — derselbe Kontext kann
 * nach einem Queue-Retry erneut eintreffen.
 */
interface AiResultHandlerInterface {
    /** @param array<string, mixed> $context vom Dispatcher mitgegeben (IDs, Idempotenz-Key) */
    public function handleAiResult(AiInvocationResult $result, array $context): void;

    /**
     * Terminaler Fehlschlag (Budget, Capability aus, alle Provider
     * gescheitert) — kein weiterer Retry.
     *
     * @param array<string, mixed> $context
     */
    public function handleAiFailure(string $reason, array $context): void;
}
