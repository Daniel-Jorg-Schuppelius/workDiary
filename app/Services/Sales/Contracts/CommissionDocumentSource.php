<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommissionDocumentSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Contracts;

/**
 * Erweiterungspunkt (MVP-1119): Buchhaltungs-Plugins liefern ihre gespiegelten
 * Rechnungen als {@see CommissionDocument}; sie tragen sich beim Booten über
 * `ModuleRegistry::contribute()` ein.
 */
interface CommissionDocumentSource {
    /** Morph-Alias der Belege dieser Quelle. */
    public function documentType(): string;

    public function find(int $organizationId, int $id): ?CommissionDocument;

    /**
     * Bezahlte Rechnungen ohne Provisionszeile, zuletzt bezahlte zuerst.
     *
     * @return list<CommissionDocument>
     */
    public function unassignedPaid(int $organizationId, int $limit): array;
}
