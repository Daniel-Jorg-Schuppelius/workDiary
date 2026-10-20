<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiTextResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Dto;

/**
 * Textergebnis der Verben Formulieren/Zusammenfassen/Erklären (MVP-398).
 */
final class AiTextResult {
    public function __construct(
        public readonly string $text,
        public readonly AiUsage $usage = new AiUsage(),
    ) {}

    /** @return array<string, mixed> Cache-Form: der Cache hält nur Arrays, keine Objekte (CLAUDE.md „Cache“) */
    public function toArray(): array {
        return ['text' => $this->text, 'usage' => $this->usage->toArray()];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self {
        return new self((string) ($data['text'] ?? ''), AiUsage::fromArray((array) ($data['usage'] ?? [])));
    }
}
