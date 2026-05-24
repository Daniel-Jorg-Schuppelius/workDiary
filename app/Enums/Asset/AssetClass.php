<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetClass.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Asset;

enum AssetClass: string {
    case Device = 'device';
    case Machine = 'machine';
    case Tool = 'tool';
    case Vehicle = 'vehicle';
    case Installation = 'installation';
    case Software = 'software';
    case Other = 'other';
}
