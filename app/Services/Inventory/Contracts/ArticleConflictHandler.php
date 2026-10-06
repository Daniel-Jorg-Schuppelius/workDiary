<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleConflictHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory\Contracts;

use App\Models\Integration\PendingExternalConflict;

/**
 * Erweiterungspunkt der Konfliktliste für Artikelkonflikte eines Fremdsystems
 * (Konsolidierungs-Audit 2026-10, vierte Runde): das Plugin, das den Konflikt
 * gemeldet hat, holt auf Wunsch den Stand des Fremdsystems und schreibt ihn in
 * seine Projektion. Plugins tragen sich beim Booten über
 * `ModuleRegistry::contribute(ArticleConflictHandler::class, …)` ein; der Kern
 * kennt kein Plugin.
 */
interface ArticleConflictHandler {
    /** Zuständig für diesen Konflikt (nach `plugin_id` und `referenceable_type`)? */
    public function supports(PendingExternalConflict $conflict): bool;

    /**
     * Übernimmt den aktuellen Stand des Fremdsystems in den lokalen Artikel.
     *
     * @throws \RuntimeException Wenn der Artikel fehlt oder das Fremdsystem nicht antwortet.
     */
    public function adoptRemote(PendingExternalConflict $conflict): void;
}
