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
use App\Models\Ai\AiProviderConnection;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * KI-Provider-Verbindungen (Feature 025, MVP-400): Verwaltung —
 * Anlegen, Testen, Sperren, Schlüsselrotation und Capability-Routing —
 * ist durchgängig `ai.manage`; das Anfordern von Vorschlägen läuft
 * separat über `ai.use` in den Capability-Consumern.
 */
class AiProviderConnectionPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::AiManage->value);
    }

    public function view(User $user, AiProviderConnection $connection): bool {
        return $user->can(P::AiManage->value);
    }

    public function create(User $user): bool {
        return $user->can(P::AiManage->value);
    }

    public function update(User $user, AiProviderConnection $connection): bool {
        return $user->can(P::AiManage->value);
    }

    public function delete(User $user, AiProviderConnection $connection): bool {
        return $user->can(P::AiManage->value);
    }
}
