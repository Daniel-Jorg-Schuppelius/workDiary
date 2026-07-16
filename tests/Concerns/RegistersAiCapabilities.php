<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RegistersAiCapabilities.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Concerns;

/**
 * Registriert Test-Capabilities in der KI-Registry (config/ai.php).
 * Capability-Keys enthalten Punkte — config()->set() mit Dot-Notation
 * würde sie fälschlich verschachteln, daher wird IMMER das gesamte
 * capabilities-Array mit literalen Keys geschrieben.
 */
trait RegistersAiCapabilities {
    /** @param array<string, mixed> $entry Merge über einen bestehenden Eintrag */
    protected function registerAiCapability(string $key, array $entry = []): void {
        $capabilities = (array) config('ai.capabilities', []);
        $capabilities[$key] = array_merge([
            'verb' => 'formulate',
            'sensitivity' => 'medium',
            'data_classes' => ['text'],
            'memory_scopes' => [],
            'prompt_version' => 1,
        ], (array) ($capabilities[$key] ?? []), $entry);

        config()->set('ai.capabilities', $capabilities);
    }
}
