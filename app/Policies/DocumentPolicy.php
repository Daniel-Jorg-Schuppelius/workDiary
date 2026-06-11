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
use App\Policies\Concerns\HasAdminBypass;

/**
 * Zugriffsregeln für Dokumente (MVP-031):
 * - Rollen-Matrix: admin alles (before()-Bypass), teamleitung ohne delete,
 *   user sieht/erstellt und bearbeitet nur EIGENE Dokumente,
 *   geschaeftsfuehrung/buchhaltung nur lesend.
 * - „Eigene": Erfasser darf mit `document.update` nur seine eigenen
 *   Dokumente bearbeiten; wer zusätzlich `document.archive` oder
 *   `document.delete` trägt (Verwaltungsrollen), darf alle bearbeiten.
 */
class DocumentPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::DocumentViewAny->value);
    }

    public function view(User $user, Document $document): bool {
        return $user->can(P::DocumentView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::DocumentCreate->value);
    }

    public function update(User $user, Document $document): bool {
        if (! $user->can(P::DocumentUpdate->value)) {
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

    public function archive(User $user, Document $document): bool {
        return $user->can(P::DocumentArchive->value);
    }

    public function delete(User $user, Document $document): bool {
        return $user->can(P::DocumentDelete->value);
    }
}
