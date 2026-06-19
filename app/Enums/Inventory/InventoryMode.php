<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryMode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Inventory;

/**
 * Bestandsführerschaft je Organisation (Feature 048, MVP-066). Pro Organisation
 * ist für den Datenbereich `inventory` genau ein Modus aktiv; paralleles
 * Schreiben in zwei führende Bestände ist unzulässig.
 */
enum InventoryMode: string {
    case Local = 'local';         // WorkDiary führt den Bestand
    case External = 'external';   // externes System führt; WorkDiary liest+bucht über Plugin
    case ReadOnly = 'read_only';  // externes System führt; Buchungen gesperrt

    public function label(): string {
        return match ($this) {
            self::Local => __('inventory.mode.local'),
            self::External => __('inventory.mode.external'),
            self::ReadOnly => __('inventory.mode.read_only'),
        };
    }

    public function allowsLocalWrites(): bool {
        return $this === self::Local;
    }
}
