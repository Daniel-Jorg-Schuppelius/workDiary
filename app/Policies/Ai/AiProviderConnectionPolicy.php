<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiProviderConnectionPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Ai;

use App\Enums\User\Permission as P;
use App\Policies\Concerns\HasAdminBypass;
use App\Policies\PermissionPolicy;

/**
 * KI-Provider-Verbindungen (Feature 025, MVP-400): Verwaltung —
 * Anlegen, Testen, Sperren, Schlüsselrotation und Capability-Routing —
 * ist durchgängig `ai.manage`; das Anfordern von Vorschlägen läuft
 * separat über `ai.use` in den Capability-Consumern.
 */
class AiProviderConnectionPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::AiManage,
        'view' => P::AiManage,
        'create' => P::AiManage,
        'update' => P::AiManage,
        'delete' => P::AiManage,
    ];
}
