<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SummarizeRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Dto;

use App\Enums\Ai\AiVerb;
use App\Services\Ai\Contracts\AiRequestInterface;
use App\Services\Ai\Dto\Concerns\HashesPayload;

/**
 * Verb „Zusammenfassen" (Feature 025): strukturierte Liste von
 * Einträgen → Kurznarrativ. Der Vertrag nimmt von Beginn an Listen
 * entgegen (Timeline-Events, Ticket-Threads, gebündelte Zeittexte),
 * auch wenn frühe Capabilities nur wenige Einträge senden.
 */
final class SummarizeRequest implements AiRequestInterface {
    use HashesPayload;

    /**
     * @param list<string> $items chronologisch sortierte Einzeltexte
     * @param list<string> $styleRules
     * @param list<GlossaryEntry> $glossary
     */
    public function __construct(
        public readonly array $items,
        public readonly string $language = 'de',
        public readonly ?string $period = null,
        public readonly array $styleRules = [],
        public readonly array $glossary = [],
    ) {}

    public function verb(): AiVerb {
        return AiVerb::Summarize;
    }

    public function fingerprint(): string {
        return $this->hashPayload([
            'verb' => $this->verb()->value,
            'items' => $this->items,
            'language' => $this->language,
            'period' => $this->period,
            'style' => $this->styleRules,
            'glossary' => array_map(static fn (GlossaryEntry $e): array => $e->toArray(), $this->glossary),
        ]);
    }

    public function estimatedUnits(): int {
        return $this->estimateTokens(implode("\n", $this->items));
    }
}
