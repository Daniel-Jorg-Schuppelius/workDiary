<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentRelationSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

use App\Services\Billing\Dto\{DocumentRef, DocumentRelation};

/**
 * Quelle von Belegbezügen (MVP-1157): kennt die Bezüge einiger Belegarten,
 * lokal oder im Buchhaltungsprogramm. Module tragen ihre Quelle im Manifest
 * unter `extensions()` ein, Plugins über `ModuleRegistry::contribute()`.
 */
interface DocumentRelationSource {
    /**
     * Verbundene Belege der Organisation; leer, wenn die Quelle die Belegart
     * nicht kennt. Jede Abfrage filtert ausdrücklich nach der Organisation.
     *
     * @return list<DocumentRelation>
     */
    public function relationsOf(DocumentRef $document, int $organizationId): array;
}
