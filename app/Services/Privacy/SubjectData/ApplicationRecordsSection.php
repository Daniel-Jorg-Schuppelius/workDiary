<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApplicationRecordsSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\SubjectData;

use App\Models\Applications\JobApplication;
use Illuminate\Database\Eloquent\Model;

/** Bewerbungsakte: Unterlagen, Gespräche und Bewertungen — Zähler + Zeitraum. */
class ApplicationRecordsSection extends AbstractSubjectSection {
    public function key(): string {
        return 'application_records';
    }

    public function title(): string {
        return __('Bewerbungsakte (Übersicht)');
    }

    public function portable(): bool {
        return false;
    }

    public function build(Model $subject): array {
        $this->expect($subject, JobApplication::class);
        /** @var JobApplication $a */
        $a = $subject;

        return ['families' => [
            $this->family('job_application_documents', __('Unterlagen'), $a->documents()->getQuery(), 'created_at'),
            $this->family('job_application_interviews', __('Gespräche'), $a->interviews()->getQuery(), 'created_at'),
            $this->family('job_application_reviews', __('Bewertungen'), $a->reviews()->getQuery(), 'created_at'),
        ]];
    }
}
