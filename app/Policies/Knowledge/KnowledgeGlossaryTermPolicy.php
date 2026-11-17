<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeGlossaryTermPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Knowledge;

use App\Enums\User\Permission as P;
use App\Models\Knowledge\KnowledgeGlossaryTerm;
use App\Models\Platform\User;
use App\Policies\Concerns\HasAdminBypass;

/** Glossar der Wissensdatenbank (Feature 191, MVP-1667): lesen alle Leser, pflegen die Verwaltung. */
class KnowledgeGlossaryTermPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::KnowledgePageViewAny->value);
    }

    public function create(User $user): bool {
        return $user->can(P::KnowledgePageManage->value);
    }

    public function update(User $user, KnowledgeGlossaryTerm $term): bool {
        return (int) $term->organization_id === (int) $user->organization_id && $this->create($user);
    }

    public function delete(User $user, KnowledgeGlossaryTerm $term): bool {
        return $this->update($user, $term);
    }
}
