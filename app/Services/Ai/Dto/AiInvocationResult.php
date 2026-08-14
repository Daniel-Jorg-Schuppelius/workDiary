<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiInvocationResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Dto;

use App\Enums\Ai\AiProviderType;

/**
 * Umschlag eines ausgeführten KI-Aufrufs (MVP-399): fachliches Ergebnis
 * plus Herkunft (Verbindung/Provider), ob die Fallback-Kette griff und
 * ob das Ergebnis aus dem Cache kam — die Vorschlag-UI weist beides aus
 * (Feature 025: Fallback „immer sichtbar ausgewiesen").
 */
final class AiInvocationResult {
    public function __construct(
        public readonly string $capability,
        public readonly int $connectionId,
        public readonly AiProviderType $provider,
        public readonly AiTextResult|AiClassificationResult|AiFindResult|AiTranslationResult|AiExtractionResult $result,
        public readonly bool $fallbackUsed = false,
        public readonly bool $fromCache = false,
    ) {}
}
