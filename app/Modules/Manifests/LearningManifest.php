<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Lernplattform“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class LearningManifest extends Manifest {
    public function code(): string {
        return 'learning';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Lernplattform';
    }

    public function licenseCode(): string {
        return 'module.lms';
    }

    public function description(): string {
        return 'Kurse, Prüfungen, Zertifikate und Kompetenzen; die Pflichtunterweisung bleibt im Kern.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Learning',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'learning_access_tokens',
            'learning_answers',
            'learning_assignments',
            'learning_bookings',
            'learning_certificates',
            'learning_cmi5_au_states',
            'learning_cmi5_packages',
            'learning_cmi5_registrations',
            'learning_cmi5_sessions',
            'learning_cmi5_units',
            'learning_content_translations',
            'learning_course_categories',
            'learning_course_prerequisites',
            'learning_course_trainers',
            'learning_course_versions',
            'learning_courses',
            'learning_enrollment_events',
            'learning_enrollments',
            'learning_gradebook_components',
            'learning_issuer_keys',
            'learning_lti_keys',
            'learning_lti_links',
            'learning_lti_nonces',
            'learning_lti_platforms',
            'learning_lti_subjects',
            'learning_lti_tools',
            'learning_manual_grades',
            'learning_path_items',
            'learning_paths',
            'learning_question_categories',
            'learning_question_options',
            'learning_questions',
            'learning_quiz_attempt_waivers',
            'learning_quiz_attempts',
            'learning_quiz_draw_rules',
            'learning_quiz_question',
            'learning_quizzes',
            'learning_scorm_packages',
            'learning_scorm_states',
            'learning_sections',
            'learning_submissions',
            'learning_time_sessions',
            'learning_unit_progress',
            'learning_units',
            'learning_xapi_documents',
            'learning_xapi_statements',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'learning.courses.*',
            'learning.lti-registrations.*',
            'learning.questions.*',
            'learning.settings.*',
            'learning.grading.*',
            'learning.paths.*',
            'learning.competencies.*',
            'learning.dossier.*',
            'learning.bookings.*',
            'learning.time-approvals.*',
            'api.learning.*',
        ];
    }
}
