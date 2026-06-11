<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormTemplatePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\{FormTemplate, User};
use App\Policies\Concerns\HasAdminBypass;

/**
 * Zugriffsregeln Formularvorlagen (Feature 032):
 * - admin: alles (before()-Bypass).
 * - teamleitung: viewAny + manage (anlegen/bearbeiten/aktivieren/archivieren/löschen).
 * - user/aussendienst: KEINE Vorlagenpflege; sie sehen aktive Vorlagen
 *   nur indirekt über den Ausfüll-Dialog (FormSubmissionPolicy::create).
 */
class FormTemplatePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::FormTemplateViewAny->value);
    }

    public function view(User $user, FormTemplate $template): bool {
        return $user->can(P::FormTemplateViewAny->value);
    }

    public function create(User $user): bool {
        return $user->can(P::FormTemplateManage->value);
    }

    public function update(User $user, FormTemplate $template): bool {
        return $user->can(P::FormTemplateManage->value);
    }

    /** Aktivieren/Archivieren folgt dem Pflege-Recht. */
    public function activate(User $user, FormTemplate $template): bool {
        return $user->can(P::FormTemplateManage->value);
    }

    public function archive(User $user, FormTemplate $template): bool {
        return $user->can(P::FormTemplateManage->value);
    }

    public function delete(User $user, FormTemplate $template): bool {
        return $user->can(P::FormTemplateManage->value);
    }
}
