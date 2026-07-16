<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiRequestInterface.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

use App\Enums\Ai\AiVerb;

/**
 * Normalisierter KI-Request (Feature 025, MVP-398). Jedes Verb hat ein
 * eigenes Request-DTO; der Fingerprint macht Ergebnisse cachebar
 * (gleicher Input + Prompt-Version + Verbindung → gleicher Vorschlag)
 * und die Einheiten-Schätzung erlaubt den Budget-Vorab-Check, bevor ein
 * Provider aufgerufen wird. DTOs müssen queue-serialisierbar bleiben
 * (nur skalare Werte, Arrays und andere DTOs — keine Models/Closures).
 */
interface AiRequestInterface {
    public function verb(): AiVerb;

    /** Stabiler Inhalts-Hash über alle prompt-relevanten Felder. */
    public function fingerprint(): string;

    /** Grobe Einheiten-Schätzung (LLM: Token, Übersetzung: Zeichen). */
    public function estimatedUnits(): int;
}
