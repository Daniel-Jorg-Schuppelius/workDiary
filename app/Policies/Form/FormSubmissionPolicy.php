<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormSubmissionPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\{FormSubmission, User};
use App\Policies\Concerns\HasAdminBypass;

/**
 * Zugriffsregeln ausgefüllte Formulare (Feature 032):
 * - admin: alles (before()-Bypass).
 * - teamleitung+ (formTemplate.viewAny): sieht ALLE Submissions der Org.
 * - user/aussendienst: ausfüllen + ausschließlich EIGENE Submissions
 *   einsehen (Liste filtert entsprechend, siehe Controller).
 */
class FormSubmissionPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::FormSubmissionViewAny->value);
    }

    public function view(User $user, FormSubmission $submission): bool {
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
}
