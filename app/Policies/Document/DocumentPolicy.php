<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\{Document, User};
use App\Services\Hr\PersonnelFilePermissions as HR;

/**
 * Zugriffsregeln für Dokumente (MVP-031):
 * - Rollen-Matrix: admin alles (before()-Bypass), teamleitung ohne delete,
 *   user sieht/erstellt und bearbeitet nur EIGENE Dokumente,
 *   geschaeftsfuehrung/buchhaltung nur lesend.
 * - „Eigene": Erfasser darf mit `document.update` nur seine eigenen
 *   Dokumente bearbeiten; wer zusätzlich `document.archive` oder
 *   `document.delete` trägt (Verwaltungsrollen), darf alle bearbeiten.
 * - Vertraulich (Vollaudit 2026-07, N10): vertrauliche Dokumente sehen nur
 *   Erfasser + Inhaber von `document.confidential.manage` (Muster
 *   Kommunikationsnotizen); Fremdzugriff der Verwalter wird im Controller
 *   auditiert.
 * - Personalakte (Feature 141, MVP-708): Dokumente mit documentable = User
 *   haben einen EIGENEN Zugriffskreis (hrFile.*, isolierter Permission-Satz
 *   wie Hinweisgeber/Datenschutz) OHNE Admin-Bypass; die betroffene Person
 *   liest ihre eigene Akte (Eigenauskunft). document.*-Rechte spielen für
 *   Personalakten keine Rolle, eine Kundenfreigabe ist ausgeschlossen.
 */
class DocumentPolicy {
    /**
     * Admin-Bypass NUR für allgemeine Dokumente: sobald ein Argument eine
     * Personalakte (Document) oder ein Mitglied (User, Akten-Abilities) ist,
     * entscheidet ausschließlich die jeweilige Policy-Methode.
     */
    public function before(User $user, string $ability, mixed ...$arguments): ?bool {
        foreach ($arguments as $argument) {
            if ($argument instanceof User || ($argument instanceof Document && $argument->isPersonnelFile())) {
                return null;
            }
        }

        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool {
        return $user->can(P::DocumentViewAny->value);
    }

    public function view(User $user, Document $document): bool {
        if ($document->isPersonnelFile()) {
            return $this->canReadPersonnelFile($user, $document);
        }

        return $user->can(P::DocumentView->value)
            && $this->canSeeConfidential($user, $document);
    }

    public function create(User $user): bool {
        return $user->can(P::DocumentCreate->value);
    }

    /** Akte eines Mitglieds öffnen: hrFile.viewAny in derselben Org ODER die eigene Akte. */
    public function viewPersonnelFile(User $user, User $member): bool {
        return $this->sameOrganization($user, (int) $member->organization_id)
            && ($user->hasEffectivePermission(HR::VIEW_ANY) || (int) $member->id === (int) $user->id);
    }

    /** Dokument in die Akte eines Mitglieds aufnehmen — nie in die eigene. */
    public function createPersonnelFile(User $user, User $member): bool {
        return $this->sameOrganization($user, (int) $member->organization_id)
            && $user->hasEffectivePermission(HR::CREATE);
    }

    public function update(User $user, Document $document): bool {
        if ($document->isPersonnelFile()) {
            return $this->canManagePersonnelFile($user, $document, HR::UPDATE);
        }

        if (! $user->can(P::DocumentUpdate->value) || ! $this->canSeeConfidential($user, $document)) {
            return false;
        }

        return (int) $document->created_by_user_id === (int) $user->id
            || $user->can(P::DocumentArchive->value)
            || $user->can(P::DocumentDelete->value);
    }

    /** Neue Version hochladen folgt denselben Regeln wie Metadaten ändern. */
    public function addVersion(User $user, Document $document): bool {
        return $this->update($user, $document);
    }

    /**
     * Fürs Kundenportal freigeben/zurückziehen folgt dem bestehenden
     * Doku-Verwaltungsrecht (wie Metadaten ändern): Erfasser für eigene
     * Dokumente, Verwaltungsrollen (archive/delete) für alle.
     * Personalakten sind nie freigebbar.
     */
    public function releaseToCustomer(User $user, Document $document): bool {
        if ($document->isPersonnelFile()) {
            return false;
        }

        return $this->update($user, $document);
    }

    public function archive(User $user, Document $document): bool {
        if ($document->isPersonnelFile()) {
            return $this->canManagePersonnelFile($user, $document, HR::UPDATE);
        }

        return $user->can(P::DocumentArchive->value)
            && $this->canSeeConfidential($user, $document);
    }

    public function delete(User $user, Document $document): bool {
        if ($document->isPersonnelFile()) {
            return $this->canManagePersonnelFile($user, $document, HR::DELETE);
        }

        return $user->can(P::DocumentDelete->value)
            && $this->canSeeConfidential($user, $document);
    }

    private function canSeeConfidential(User $user, Document $document): bool {
        if (! $document->confidential) {
            return true;
        }

        return (int) $document->created_by_user_id === (int) $user->id
            || $user->can(P::DocumentConfidentialManage->value);
    }

    private function canReadPersonnelFile(User $user, Document $document): bool {
        return $this->sameOrganization($user, (int) $document->organization_id)
            && ($user->hasEffectivePermission(HR::VIEW_ANY) || (int) $document->documentable_id === (int) $user->id);
    }

    private function canManagePersonnelFile(User $user, Document $document, string $permission): bool {
        return $this->sameOrganization($user, (int) $document->organization_id)
            && $user->hasEffectivePermission($permission);
    }

    private function sameOrganization(User $user, int $organizationId): bool {
        return $organizationId > 0 && (int) $user->organization_id === $organizationId;
    }
}
