<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingPreflightCheck.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Preflight;

/**
 * Ein benannter Prüfpunkt der Einrichtung (Feature 125, MVP-671).
 *
 * Benannt, nicht nur eine Meldung: Die Oberfläche zeigt je Punkt eine Zeile
 * mit eigenem Absprung, und der gespeicherte Nachweis bleibt vergleichbar,
 * wenn später Prüfpunkte hinzukommen.
 */
final class AccountingPreflightCheck {
    /** @param array<string, mixed> $context */
    private function __construct(
        public readonly string $key,
        public readonly bool $passed,
        public readonly bool $blocking,
        public readonly string $message,
        public readonly array $context = [],
    ) {}

    /** @param array<string, mixed> $context */
    public static function passed(string $key, string $message, array $context = []): self {
        return new self($key, true, false, $message, $context);
    }

    /**
     * Offener Punkt, der die Aktivierung verhindert.
     *
     * @param  array<string, mixed>  $context
     */
    public static function blocked(string $key, string $message, array $context = []): self {
        return new self($key, false, true, $message, $context);
    }

    /**
     * Hinweis, der die Aktivierung NICHT verhindert.
     *
     * @param  array<string, mixed>  $context
     */
    public static function warning(string $key, string $message, array $context = []): self {
        return new self($key, false, false, $message, $context);
    }

    public function tone(): string {
        if ($this->passed) {
            return 'success';
        }

        return $this->blocking ? 'error' : 'warning';
    }

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'key' => $this->key,
            'passed' => $this->passed,
            'blocking' => $this->blocking,
            'message' => $this->message,
            'context' => $this->context,
        ];
    }
}
