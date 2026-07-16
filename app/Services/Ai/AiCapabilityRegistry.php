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
        return is_array($this->entries()[$key] ?? null);
    }

    public function get(string $key): AiCapability {
        $entry = $this->entries()[$key] ?? null;

        if (! is_array($entry)) {
            throw UnknownAiCapabilityException::forKey($key);
        }

        return $this->fromEntry($key, $entry);
    }

    /** @return list<AiCapability> */
    public function all(): array {
        $capabilities = [];
        foreach ($this->entries() as $key => $entry) {
            if (is_array($entry)) {
                $capabilities[] = $this->fromEntry((string) $key, $entry);
            }
        }

        return $capabilities;
    }

    /**
     * Capability-Keys enthalten Punkte (z. B. `invoicing.item_text`) —
     * daher IMMER literaler Array-Zugriff, nie Dot-Notation über
     * config('ai.capabilities.<key>').
     *
     * @return array<string, mixed>
     */
    private function entries(): array {
        return (array) config('ai.capabilities', []);
    }

    /** @param array<string, mixed> $entry */
    private function fromEntry(string $key, array $entry): AiCapability {
        return new AiCapability(
            key: $key,
            verb: AiVerb::from((string) ($entry['verb'] ?? '')),
            sensitivity: AiSensitivity::from((string) ($entry['sensitivity'] ?? AiSensitivity::High->value)),
            dataClasses: array_values((array) ($entry['data_classes'] ?? [])),
            memoryScopes: array_values((array) ($entry['memory_scopes'] ?? [])),
            promptVersion: max(1, (int) ($entry['prompt_version'] ?? 1)),
        );
    }
}
