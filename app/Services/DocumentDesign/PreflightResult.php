<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PreflightResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\DocumentDesign;

/**
 * Ergebnis des Layout-/Pflichtfeld-/Kontrast-Preflights (MVP-297/298).
 * Fehler blockieren Aktivierung und Finalisierung; Warnungen sind Hinweise
 * mit Korrektur-CTA im Editor.
 */
class PreflightResult {
    /** @param array<int, array{code: string, message: string, page?: string, block?: string}> $errors
     *  @param array<int, array{code: string, message: string, page?: string, block?: string}> $warnings */
    public function __construct(
        public array $errors = [],
        public array $warnings = [],
    ) {}

    public function ok(): bool {
        return $this->errors === [];
    }

    public function error(string $code, string $message, ?string $page = null, ?string $block = null): void {
        $this->errors[] = array_filter(
            ['code' => $code, 'message' => $message, 'page' => $page, 'block' => $block],
            fn($v) => $v !== null,
        );
    }

    public function warn(string $code, string $message, ?string $page = null, ?string $block = null): void {
        $this->warnings[] = array_filter(
            ['code' => $code, 'message' => $message, 'page' => $page, 'block' => $block],
            fn($v) => $v !== null,
        );
    }

    /** @return array{ok: bool, errors: array<int, mixed>, warnings: array<int, mixed>} */
    public function toArray(): array {
        return ['ok' => $this->ok(), 'errors' => $this->errors, 'warnings' => $this->warnings];
    }
}
