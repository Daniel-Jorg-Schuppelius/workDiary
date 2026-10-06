<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PricingChangeAlertStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Article;

/** Bearbeitungsstand einer Abgleichwarnung (Feature 050, MVP-094). */
enum PricingChangeAlertStatus: string {
    case Open = 'open';
    case Acknowledged = 'acknowledged';
}
