<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiProviderInterface.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

/**
 * Gemeinsamer Grundvertrag beider Provider-Familien (Feature 025,
 * MVP-398). Adapter werfen bei Transport-/Providerfehlern ausschließlich
 * {@see \App\Services\Ai\Exceptions\AiProviderCallException} — mit
 * redigierter Meldung (nie Prompt-Inhalte, nie Schlüssel), damit
 * Health-Tracking und Fallback-Kette einheitlich funktionieren.
 */
interface AiProviderInterface {
    /**
     * Verbindungstest vor Aktivierung (MVP-399): billigster möglicher
     * Aufruf gegen den Provider; wirft AiProviderCallException bei
     * Fehlschlag.
     */
    public function preflight(): void;
}
