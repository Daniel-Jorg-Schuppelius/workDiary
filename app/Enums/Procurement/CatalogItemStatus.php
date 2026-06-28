<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogItemStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Procurement;

/**
 * Mappingstatus eines externen Katalogartikels (Feature 050, MVP-092/093).
 *
 * - New:          frisch importiert, noch ohne internen Bezug
 * - Proposed:     ein interner Artikel wurde vorgeschlagen
 * - Linked:       verbindlich auf internen Artikel/Variante/Bezugsquelle gemappt
 * - Conflict:     externe Änderung kollidiert mit einem lokalen Bezug
 * - Ignored:      bewusst nicht übernommen
 * - Discontinued: im letzten Import nicht mehr enthalten (abgekündigt)
 */
enum CatalogItemStatus: string {
    case New = 'new';
    case Proposed = 'proposed';
    case Linked = 'linked';
    case Conflict = 'conflict';
    case Ignored = 'ignored';
    case Discontinued = 'discontinued';

    public function label(): string {
        return __('procurement.catalog.status.' . $this->value);
    }
}
