<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormSubmissionPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Form;

use App\Enums\User\Permission as P;
use App\Models\Form\FormSubmission;
use App\Models\Platform\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Zugriffsregeln ausgefüllte Formulare (Feature 032):
 * - admin: alles (before()-Bypass).
 * - teamleitung+ (formTemplate.viewAny): sieht ALLE Submissions der Org.
 * - user/aussendienst: ausfüllen + ausschließlich EIGENE Submissions
 *   einsehen (Liste filtert entsprechend, siehe Controller).
 * - Entwürfe (Feature 203): persönlich — sehen, je Feld speichern
 *   (`autosave`) und verwerfen (`discard`) nur, wer sie angelegt hat; auch
 *   die Admin-Abkürzung greift dort nicht.
 */
class FormSubmissionPolicy {
    use HasAdminBypass {
        before as adminBypass;
    }

    public function before(User $user, string $ability, mixed ...$arguments): ?bool {
        $submission = $arguments[0] ?? null;
        if ($submission instanceof FormSubmission && $submission->isDraft()) {
            return null;
        }

        return $this->adminBypass($user, $ability);
    }

    public function viewAny(User $user): bool {
        return $user->can(P::FormSubmissionViewAny->value);
    }

    public function view(User $user, FormSubmission $submission): bool {
        if ($submission->isDraft()) {
            return $this->ownsDraft($user, $submission);
        }
        if (! $user->can(P::FormSubmissionView->value)) {
            return false;
        }

        // Vorlagen-Sicht (teamleitung+) = Einsicht in alle Submissions.
        if ($user->can(P::FormTemplateViewAny->value)) {
            return true;
        }

        return (int) $submission->submitted_by_user_id === (int) $user->id;
    }

    public function create(User $user): bool {
        return $user->can(P::FormSubmissionCreate->value);
    }

    /** Entwurf je Feld speichern und absenden (Vertrag AutosavesFieldDocument). */
    public function autosave(User $user, FormSubmission $submission): bool {
        return $submission->isDraft() && $this->ownsDraft($user, $submission);
    }

    public function discard(User $user, FormSubmission $submission): bool {
        return $submission->isDraft() && $this->ownsDraft($user, $submission);
    }

    private function ownsDraft(User $user, FormSubmission $submission): bool {
        return (int) $submission->submitted_by_user_id === (int) $user->id
            && ($user->isAdmin() || $user->can(P::FormSubmissionCreate->value));
    }
}
