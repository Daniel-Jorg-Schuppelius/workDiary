<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FindRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Dto;

use App\Enums\Ai\AiVerb;
use App\Services\Ai\Contracts\AiRequestInterface;
use App\Services\Ai\Dto\Concerns\HashesPayload;

/**
 * Verb „Finden" (Feature 025): Frage gegen einen freigegebenen Korpus
 * (Hilfe-Topics, Wissensartikel). Im Fundament bewusst schlicht — der
 * Korpus wird als Kandidatenliste übergeben; Embedding-/RAG-Infrastruktur
 * ist Welle 3 und ändert nur die Adapter, nicht diesen Vertrag.
 */
final class FindRequest implements AiRequestInterface {
    use HashesPayload;

    /**
     * @param array<string, string> $corpus Kandidaten: Referenz-Key => Text
     */
    public function __construct(
        public readonly string $query,
        public readonly array $corpus,
        public readonly int $maxResults = 5,
        public readonly string $language = 'de',
    ) {}

    public function verb(): AiVerb {
        return AiVerb::Find;
    }

    public function fingerprint(): string {
        return $this->hashPayload([
            'verb' => $this->verb()->value,
            'query' => $this->query,
            'corpus' => $this->corpus,
            'max' => $this->maxResults,
            'language' => $this->language,
        ]);
    }

    public function estimatedUnits(): int {
        return $this->estimateTokens($this->query, implode("\n", $this->corpus));
    }
}
