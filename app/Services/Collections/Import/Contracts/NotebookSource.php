<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NotebookSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Collections\Import\Contracts;

use App\Models\Platform\Organization;
use App\Services\Collections\Import\ImportedDocument;

/**
 * Notizbuch-Quelle der Wissensübernahme (MVP-1042), z. B. OneNote. Texte des
 * Dialogs unter `collections.import.<key>.*`; Plugins tragen sich in
 * {@see \App\Services\Collections\Import\NotebookSources} ein.
 */
interface NotebookSource {
    public function key(): string;

    public function icon(): string;

    /** Plugin-ID und Referenztyp übernommener Seiten (Wiedererkennung beim nächsten Lauf). */
    public function pluginId(): string;

    public function referenceType(): string;

    /** Eingeschaltet und verbunden? */
    public function ready(Organization $organization): bool;

    /**
     * @return list<array{id: string, name: string}>
     *
     * @throws \Throwable wenn die Quelle nicht antwortet
     */
    public function notebooks(Organization $organization): array;

    /**
     * @param  callable(string): bool  $known
     * @return array{documents: list<ImportedDocument>, limited: bool}
     */
    public function documents(Organization $organization, string $notebookId, string $notebookName, int $limit, callable $known): array;

    public function markImported(Organization $organization): void;
}
