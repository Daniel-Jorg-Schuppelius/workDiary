<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiMemoryEntryPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Ai;

use App\Enums\User\Permission as P;
use App\Policies\Concerns\HasAdminBypass;
use App\Policies\PermissionPolicy;

/**
 * KI-Gedächtnis (Feature 025, MVP-401): Pflege der Glossare/Regeln/
 * Beispielpaare über die Verwaltungsseite ist `ai.manage`. Der
 * bestätigte „Merken?"-Dialog in Capability-Consumern (Feature 084)
 * erzeugt gelernte Einträge über `ai.use` + Fachrecht — dort geprüft.
 */
class AiMemoryEntryPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::AiManage,
        'view' => P::AiManage,
        'create' => P::AiManage,
        'update' => P::AiManage,
        'delete' => P::AiManage,
    ];
}
