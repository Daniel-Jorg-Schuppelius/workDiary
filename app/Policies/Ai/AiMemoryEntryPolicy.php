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
use App\Models\Ai\AiMemoryEntry;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * KI-Gedächtnis (Feature 025, MVP-401): Pflege der Glossare/Regeln/
 * Beispielpaare über die Verwaltungsseite ist `ai.manage`. Der
 * bestätigte „Merken?"-Dialog in Capability-Consumern (Feature 084)
 * erzeugt gelernte Einträge über `ai.use` + Fachrecht — dort geprüft.
 */
class AiMemoryEntryPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::AiManage->value);
    }

    public function view(User $user, AiMemoryEntry $entry): bool {
        return $user->can(P::AiManage->value);
    }

    public function create(User $user): bool {
        return $user->can(P::AiManage->value);
    }

    public function update(User $user, AiMemoryEntry $entry): bool {
        return $user->can(P::AiManage->value);
    }

    public function delete(User $user, AiMemoryEntry $entry): bool {
        return $user->can(P::AiManage->value);
    }
}
