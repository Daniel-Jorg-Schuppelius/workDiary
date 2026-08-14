<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LlmProviderInterface.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

use App\Services\Ai\Dto\{AiClassificationResult, AiExtractionResult, AiFindResult, AiTextResult, ClassifyRequest, ExplainRequest, ExtractRequest, FindRequest, FormulateRequest, SummarizeRequest};

/**
 * Familien-Vertrag LLM (Feature 025, MVP-398; Extrahieren mit Feature 088):
 * die LLM-Verben als getrennte, typisierte Methoden plus Übersetzen per
 * Prompt — bewusst KEIN generischer Prompt-Aufruf, damit jede Capability
 * ein eigenes Sensibilitätsprofil und eine eigene Datenfluss-Dokumentation
 * trägt. Adapter (MVP-407/408): Anthropic, Ollama, OpenAI-kompatibel,
 * OpenAI, Gemini, Azure OpenAI.
 */
interface LlmProviderInterface extends AiProviderInterface, TranslatesTextInterface {
    /** Stichworte → sauberer Text; nur umformulieren, nie erfinden. */
    public function formulate(FormulateRequest $request): AiTextResult;

    /** Strukturierte Einträge → Kurznarrativ. */
    public function summarize(SummarizeRequest $request): AiTextResult;

    /** Freitext → Werte ausschließlich aus dem übergebenen Katalog. */
    public function classify(ClassifyRequest $request): AiClassificationResult;

    /** Kennzahlen/Codes → verständliche Handlungsempfehlung. */
    public function explain(ExplainRequest $request): AiTextResult;

    /** Frage → relevante Referenzen aus dem freigegebenen Korpus. */
    public function find(FindRequest $request): AiFindResult;

    /** Belegtext → Werte für ein festes Zielschema; nichts erfinden. */
    public function extract(ExtractRequest $request): AiExtractionResult;
}
