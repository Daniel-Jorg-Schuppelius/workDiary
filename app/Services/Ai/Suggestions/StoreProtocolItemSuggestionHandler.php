<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StoreProtocolItemSuggestionHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Suggestions;

use App\Models\Platform\User;
use App\Models\Protocol\{Protocol, ProtocolItem};
use App\Services\Ai\Contracts\AiResultHandlerInterface;
use App\Services\Ai\Dto\AiInvocationResult;

/**
 * Ergebnis-Handler der Protokoll-Sammelaktion (MVP-1006). Anders als bei
 * Belegpositionen ersetzt er nur den offenen Textvorschlag — ein
 * Klassifikationsvorschlag am selben Punkt bleibt stehen.
 */
class StoreProtocolItemSuggestionHandler implements AiResultHandlerInterface {
    public function __construct(private readonly ProtocolTextSuggestionService $suggestions) {}

    public function handleAiResult(AiInvocationResult $result, array $context): void {
        // Der Job läuft ohne Organisationskontext: Scopes ausdrücklich ersetzen.
        $item = ProtocolItem::query()->withoutGlobalScopes()->find((int) ($context['item_id'] ?? 0));
        $protocol = $item === null ? null : Protocol::query()->withoutGlobalScopes()
            ->where('organization_id', (int) ($context['organization_id'] ?? 0))->find($item->protocol_id);
        if ($item === null || $protocol === null) {
            return;
        }
        $user = isset($context['user_id']) ? User::query()->withoutGlobalScopes()->find((int) $context['user_id']) : null;
        $this->suggestions->storeQueued($protocol, $item, (string) ($context['original'] ?? ''), $result, $user);
    }

    public function handleAiFailure(string $reason, array $context): void {
        // Best-Effort wie die Beleg-Sammelaktion: kein Vorschlag, kein Fehler im Protokoll.
    }
}
