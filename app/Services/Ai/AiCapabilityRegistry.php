<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiCapabilityRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\Ai\{AiSensitivity, AiVerb};
use App\Services\Ai\Dto\AiCapability;
use App\Services\Ai\Exceptions\UnknownAiCapabilityException;

/**
 * Capability-Registry des KI-Fundaments (Feature 025, MVP-398/400):
 * liest config/ai.php und liefert typisierte Capabilities. Analog zur
 * Jobs-Registry (config/scheduler.php) ist die Config die Allowlist —
 * nicht registrierte Keys sind ein Programmierfehler.
 */
class AiCapabilityRegistry {
    public function has(string $key): bool {
        return is_array(config('ai.capabilities.' . $key));
    }

    public function get(string $key): AiCapability {
        $entry = config('ai.capabilities.' . $key);

        if (! is_array($entry)) {
            throw UnknownAiCapabilityException::forKey($key);
        }

        return new AiCapability(
            key: $key,
            verb: AiVerb::from((string) ($entry['verb'] ?? '')),
            sensitivity: AiSensitivity::from((string) ($entry['sensitivity'] ?? AiSensitivity::High->value)),
            dataClasses: array_values((array) ($entry['data_classes'] ?? [])),
            memoryScopes: array_values((array) ($entry['memory_scopes'] ?? [])),
            promptVersion: max(1, (int) ($entry['prompt_version'] ?? 1)),
        );
    }

    /** @return list<AiCapability> */
    public function all(): array {
        $keys = array_keys((array) config('ai.capabilities', []));

        return array_map(fn (string $key): AiCapability => $this->get($key), $keys);
    }
}
