<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExplainRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Dto;

use App\Enums\Ai\AiVerb;
use App\Services\Ai\Contracts\AiRequestInterface;
use App\Services\Ai\Dto\Concerns\HashesPayload;

/**
 * Verb „Erklären" (Feature 025): strukturierte Kennzahlen/Codes →
 * verständliche Handlungsempfehlung (Plan-Ist-Abweichung, Fehlercodes,
 * Report-Auffälligkeiten). Die Fakten kommen als benannte Werte, nie
 * als Roh-Datensätze.
 */
final class ExplainRequest implements AiRequestInterface {
    use HashesPayload;

    /**
     * @param array<string, scalar|null> $facts benannte Kennzahlen/Codes
     */
    public function __construct(
        public readonly array $facts,
        public readonly ?string $question = null,
        public readonly string $language = 'de',
    ) {}

    public function verb(): AiVerb {
        return AiVerb::Explain;
    }

    public function fingerprint(): string {
        return $this->hashPayload([
            'verb' => $this->verb()->value,
            'facts' => $this->facts,
            'question' => $this->question,
            'language' => $this->language,
        ]);
    }

    public function estimatedUnits(): int {
        return $this->estimateTokens((string) json_encode($this->facts), (string) $this->question);
    }
}
