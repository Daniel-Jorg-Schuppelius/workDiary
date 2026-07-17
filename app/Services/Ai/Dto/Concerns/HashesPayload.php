<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HashesPayload.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Dto\Concerns;

use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};

/**
 * Deterministischer Inhalts-Hash für Request-Fingerprints (MVP-398):
 * JSON mit stabiler Schlüsselreihenfolge, damit identische Inhalte
 * unabhängig von der Array-Konstruktion denselben Cache-Key ergeben.
 */
trait HashesPayload {
    /** @param array<string, mixed> $payload */
    protected function hashPayload(array $payload): string {
        $normalized = $this->normalize($payload);

        return CryptoHelper::hash(JsonHelper::encode(
            $normalized,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
    }

    /** Sortiert assoziative Schlüssel rekursiv; Listen behalten ihre Reihenfolge. */
    private function normalize(mixed $value): mixed {
        if (! is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);
        $normalized = array_map(fn (mixed $v): mixed => $this->normalize($v), $value);

        if (! $isList) {
            ksort($normalized);
        }

        return $normalized;
    }

    /** Grobe Token-Schätzung für den Budget-Vorab-Check (~4 Zeichen/Token). */
    protected function estimateTokens(string ...$texts): int {
        $length = 0;
        foreach ($texts as $text) {
            $length += mb_strlen($text);
        }

        return max(1, (int) ceil($length / 4));
    }
}
