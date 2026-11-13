<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePagePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Knowledge;

use App\Enums\User\Permission as P;
use App\Models\Knowledge\KnowledgePage;
use App\Models\Platform\User;
use App\Policies\Concerns\HasAdminBypass;
use App\Services\Knowledge\Pages\KnowledgePageAccess;

/**
 * Zugriffsregeln der Wissensseiten (Feature 191):
 * - lesen: `knowledge.page.viewAny` und Leserecht auf den Bereich; ohne
 *   Bearbeitungsrecht nur Seiten mit freigegebener Fassung;
 * - bearbeiten: `knowledge.page.edit` im lesbaren Bereich;
 * - freigeben, „Stimmt, aktuell“, veraltet markieren, Verantwortung,
 *   Pflichtlektüre und Papierkorb: die verantwortliche Person oder
 *   `knowledge.page.manage`;
 * - endgültig löschen: `knowledge.page.forceDelete`.
 * Organisations-Admins dürfen alles (Bypass).
 */
class KnowledgePagePolicy {
    use HasAdminBypass;

    public function __construct(private readonly KnowledgePageAccess $access) {}

    public function viewAny(User $user): bool {
        return $user->can(P::KnowledgePageViewAny->value);
    }

    public function view(User $user, KnowledgePage $page): bool {
        return $this->sameOrganization($user, $page)
            && $this->viewAny($user)
            && $this->access->canReadSpace($user, (int) $page->knowledge_space_id)
            && ($page->isPublished() || $this->access->canEdit($user));
    }

    public function create(User $user): bool {
        return $user->can(P::KnowledgePageEdit->value);
    }

    /** Verwaltung über alle Seiten: Suchlücken, Papierkorb aller, fremde Freigaben. */
    public function manageAny(User $user): bool {
        return $user->can(P::KnowledgePageManage->value);
    }

    public function update(User $user, KnowledgePage $page): bool {
        return ! $page->trashed() && $this->create($user) && $this->view($user, $page);
    }

    /** Freigabe und Prüfung: die verantwortliche Person oder die Verwaltung. */
    public function approve(User $user, KnowledgePage $page): bool {
        return ! $page->trashed() && $this->view($user, $page) && ($this->isOwner($user, $page) || $user->can(P::KnowledgePageManage->value));
    }

    public function review(User $user, KnowledgePage $page): bool {
        return $this->approve($user, $page);
    }

    /** Verantwortung, Prüfintervall, Gültigkeit, Notfallhandbuch, Einordnung, Pflichtlektüre. */
    public function manage(User $user, KnowledgePage $page): bool {
        return $this->approve($user, $page);
    }

    /** In den Papierkorb legen. */
    public function delete(User $user, KnowledgePage $page): bool {
        return $this->approve($user, $page);
    }

    public function restore(User $user, KnowledgePage $page): bool {
        return $page->trashed() && $this->sameOrganization($user, $page) && $this->access->canReadSpace($user, (int) $page->knowledge_space_id)
            && ($this->isOwner($user, $page) || $user->can(P::KnowledgePageManage->value));
    }

    public function forceDelete(User $user, KnowledgePage $page): bool {
        return $this->sameOrganization($user, $page) && $user->can(P::KnowledgePageForceDelete->value);
    }

    /** Verknüpfen mit Kunde, Auftrag, Ticket, Asset, Projekt, Prozedur — wer schreiben darf. */
    public function link(User $user, KnowledgePage $page): bool {
        return $this->update($user, $page);
    }

    private function isOwner(User $user, KnowledgePage $page): bool {
        return $page->owner_user_id !== null && (int) $page->owner_user_id === (int) $user->id;
    }

    private function sameOrganization(User $user, KnowledgePage $page): bool {
        return (int) $page->organization_id === (int) $user->organization_id;
    }
}
