<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeSpacePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Knowledge;

use App\Enums\User\Permission as P;
use App\Models\Knowledge\KnowledgeSpace;
use App\Models\Platform\User;
use App\Policies\Concerns\HasAdminBypass;
use App\Services\Knowledge\Pages\KnowledgePageAccess;

/**
 * Bereiche und Rubriken der Wissensdatenbank (Feature 191): lesen, wer den
 * Bereich lesen darf; anlegen, ändern, Leser festlegen und löschen nur mit
 * `knowledge.page.manage`.
 */
class KnowledgeSpacePolicy {
    use HasAdminBypass;

    public function __construct(private readonly KnowledgePageAccess $access) {}

    public function view(User $user, KnowledgeSpace $space): bool {
        return $user->can(P::KnowledgePageViewAny->value) && $this->access->canReadSpace($user, (int) $space->id);
    }

    public function create(User $user): bool {
        return $user->can(P::KnowledgePageManage->value);
    }

    public function update(User $user, KnowledgeSpace $space): bool {
        return (int) $space->organization_id === (int) $user->organization_id && $this->create($user);
    }

    public function delete(User $user, KnowledgeSpace $space): bool {
        return $this->update($user, $space);
    }
}
