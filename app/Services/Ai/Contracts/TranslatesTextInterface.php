<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TranslatesTextInterface.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

use App\Services\Ai\Dto\{AiTranslationResult, TranslateRequest};

/**
 * Verb Übersetzen (Feature 025): von beiden Familien implementiert —
 * dedizierte MT-Dienste erzwingen Glossarbegriffe deterministisch,
 * LLM-Provider übersetzen mit Prompt-Glossar (Ergebnis entsprechend
 * gekennzeichnet).
 */
interface TranslatesTextInterface {
    public function translate(TranslateRequest $request): AiTranslationResult;
}
