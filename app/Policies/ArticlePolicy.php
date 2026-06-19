<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticlePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\{Article, User};
use App\Policies\Concerns\HasAdminBypass;

/**
 * Artikelstamm (Feature 048, MVP-060): Lesen mit article.viewAny/article.view,
 * Pflege mit article.manage. Die Mandantengrenze trägt der OrganizationScope
 * (bzw. das Sqid-Model-Binding); die Policy prüft nur Berechtigungen.
 */
class ArticlePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::ArticleViewAny->value) || $user->can(P::ArticleView->value);
    }

    public function view(User $user, Article $article): bool {
        return $this->viewAny($user);
    }

    public function create(User $user): bool {
        return $user->can(P::ArticleManage->value);
    }

    public function update(User $user, Article $article): bool {
        return $user->can(P::ArticleManage->value);
    }

    public function delete(User $user, Article $article): bool {
        return $user->can(P::ArticleManage->value);
    }
}
