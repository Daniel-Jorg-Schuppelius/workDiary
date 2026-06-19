<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockCountType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Inventory;

/**
 * Art einer Inventur (Feature 048, E6): Stichtags-Vollzählung oder zyklische
 * Zählung einer Teilmenge (Stichprobe/ABC-Zyklus).
 */
enum StockCountType: string {
    case Full = 'full';
    case Cycle = 'cycle';
}
