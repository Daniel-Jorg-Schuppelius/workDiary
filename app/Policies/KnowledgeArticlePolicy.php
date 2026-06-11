<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeArticlePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\Knowledge\ArticleStatus;
use App\Enums\User\Permission as P;
use App\Models\{KnowledgeArticle, User};
use App\Policies\Concerns\HasAdminBypass;

/**
 * Zugriffsregeln Wissensbasis (Feature 011):
 * - admin: alles (before()-Bypass).
 * - teamleitung: alles außer delete (inkl. publish/archive über
 *   knowledge.publish — Redaktionsrecht).
 * - user: viewAny/view/create + update NUR eigener Entwürfe; veröffentlichte
 *   Artikel redigiert ausschließlich, wer knowledge.publish trägt.
 * - aussendienst: viewAny/view/create (kein update).
 */
class KnowledgeArticlePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::KnowledgeViewAny->value);
    }

    public function view(User $user, KnowledgeArticle $article): bool {
        return $user->can(P::KnowledgeView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::KnowledgeCreate->value);
    }

    public function update(User $user, KnowledgeArticle $article): bool {
        if (! $user->can(P::KnowledgeUpdate->value)) {
            return false;
        }

        // Redaktion (publish-Recht) darf alle Artikel bearbeiten —
        // auch veröffentlichte und archivierte.
        if ($user->can(P::KnowledgePublish->value)) {
            return true;
        }

        // Erfasser pflegen ausschließlich ihre EIGENEN Entwürfe.
        return $article->status === ArticleStatus::Draft
            && (int) $article->created_by_user_id === (int) $user->id;
    }

    /** Veröffentlichen ist ein eigenes Redaktionsrecht. */
    public function publish(User $user, KnowledgeArticle $article): bool {
        return $user->can(P::KnowledgePublish->value);
    }

    /** Archivieren folgt dem Redaktionsrecht (teamleitung+). */
    public function archive(User $user, KnowledgeArticle $article): bool {
        return $user->can(P::KnowledgePublish->value);
    }

    public function delete(User $user, KnowledgeArticle $article): bool {
        return $user->can(P::KnowledgeDelete->value);
    }

    /** Feedback darf jeder abgeben, der den Artikel sehen kann. */
    public function feedback(User $user, KnowledgeArticle $article): bool {
        return $this->view($user, $article);
    }

    /** Verknüpfen/Lösen folgt dem Erfassen-Recht (operative Aktion). */
    public function link(User $user, KnowledgeArticle $article): bool {
        return $user->can(P::KnowledgeCreate->value);
    }
}
