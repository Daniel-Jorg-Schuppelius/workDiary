<?php
/*
 * Created on   : Mon Aug 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TextCorrectionPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Schreibfehler-Wörterbuch: verändert automatisch den Rechnungs-Output und
 * liegt damit auf derselben Vertrauensstufe wie Preise/Mengen —
 * `finance.config`. Der bestätigte „Merken?"-Dialog beim Bearbeiten von
 * Belegtexten läuft über die Fachrechte der Belegbearbeitung — dort geprüft.
 */
class TextCorrectionPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::FinanceConfig,
        'view' => P::FinanceConfig,
        'create' => P::FinanceConfig,
        'update' => P::FinanceConfig,
        'delete' => P::FinanceConfig,
    ];
}
