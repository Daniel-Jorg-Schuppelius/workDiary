<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCoursePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Learning;

use App\Enums\Learning\LearningCourseStatus;
use App\Enums\User\Permission as P;
use App\Models\Learning\LearningCourse;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Lernkurse (Feature 149). Autorenschaft und Freigabe sind bewusst
 * getrennte Rechte: wer Inhalte schreibt, muss sie nicht selbst
 * freigeben dürfen (Vier-Augen für zertifizierungsrelevante Kurse).
 *
 * Der Zugriff auf die EIGENEN Kurse hängt an der Einschreibung, nicht an
 * einem Recht — „Meine Schulungen" bleibt für alle erreichbar.
 */
class LearningCoursePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::LearningViewAny->value)
            || $user->can(P::LearningAuthor->value)
            || $user->can(P::LearningManage->value);
    }

    public function view(User $user, LearningCourse $course): bool {
        unset($course);

        return $this->viewAny($user);
    }

    public function create(User $user): bool {
        return $user->can(P::LearningAuthor->value);
    }

    /** Inhalt ändern: Autorenrecht UND ein Kurs, der nicht eingefroren ist. */
    public function update(User $user, LearningCourse $course): bool {
        return $user->can(P::LearningAuthor->value) && $course->isContentEditable();
    }

    /** Stammdaten (Titel, Zielgruppen, Verantwortlicher) bleiben pflegbar. */
    public function updateMeta(User $user, LearningCourse $course): bool {
        unset($course);

        return $user->can(P::LearningAuthor->value) || $user->can(P::LearningManage->value);
    }

    public function release(User $user, LearningCourse $course): bool {
        return $user->can(P::LearningRelease->value) && $course->status !== LearningCourseStatus::Archived;
    }

    public function archive(User $user, LearningCourse $course): bool {
        unset($course);

        return $user->can(P::LearningRelease->value) || $user->can(P::LearningManage->value);
    }

    /**
     * Löschen nur, solange nie freigegeben wurde — eine freigegebene
     * Version kann Nachweise tragen und wird archiviert, nicht gelöscht.
     */
    public function delete(User $user, LearningCourse $course): bool {
        return $user->can(P::LearningAuthor->value)
            && $course->status === LearningCourseStatus::Draft
            && $course->versions()->count() === 0;
    }
}
