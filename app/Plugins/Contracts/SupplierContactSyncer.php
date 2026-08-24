<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierContactSyncer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Contracts;

use App\Models\Supplier;

/**
 * Lieferanten-Pendant zu {@see ContactSyncer} (Vollscan 2026-08-23, B6):
 * bewusst ein eigenes Interface — nicht jedes Buchhaltungssystem kennt
 * Lieferantenkontakte; der Push-Weg prüft per instanceof statt Plugins zu
 * leeren Pflicht-Implementierungen zu zwingen.
 */
interface SupplierContactSyncer {
    /** Überträgt den Lieferanten; gibt die externe ID zurück. */
    public function pushSupplierContact(Supplier $supplier): string;
}
