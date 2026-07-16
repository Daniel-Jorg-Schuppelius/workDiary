<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiCapability.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Dto;

use App\Enums\Ai\{AiSensitivity, AiVerb};

/**
 * Registrierte KI-Einsatzstelle aus der Capability-Registry
 * (config/ai.php, Feature 025, MVP-398).
 */
final class AiCapability {
    /**
     * @param list<string> $dataClasses
     * @param list<string> $memoryScopes
     */
    public function __construct(
        public readonly string $key,
        public readonly AiVerb $verb,
        public readonly AiSensitivity $sensitivity,
        public readonly array $dataClasses,
        public readonly array $memoryScopes,
        public readonly int $promptVersion,
    ) {}
}
