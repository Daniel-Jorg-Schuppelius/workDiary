<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PersonnelFileSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\SubjectData;

use App\Models\Document\Document;
use App\Models\Hr\{PersonnelFileAcknowledgement, PersonnelFileSubmission};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Digitale Personalakte in der Auskunft (Feature 141 × Feature 129): je
 * Dokument Titel, Kategorie, Gültigkeit, Aufbewahrungsende und aktuelle
 * Datei — die Dateien selbst legt {@see \App\Services\Privacy\SubjectDataExporter::attachFiles()}
 * DEK-verschlüsselt am Fall ab.
 */
class PersonnelFileSection extends AbstractSubjectSection {
    public function key(): string {
        return 'personnel_file';
    }

    public function title(): string {
        return __('hr.personnel_file.title');
    }

    public function portable(): bool {
        return false;
    }

    public function build(Model $subject): array {
        $this->expect($subject, User::class);
        /** @var User $u */
        $u = $subject;

        $query = Document::query()->withoutGlobalScopes()->whereNull('deleted_at')->personnelFilesOf($u);

        $rows = [];
        $documents = (clone $query)->with('currentVersion')->orderBy('created_at')->get();
        $acknowledgements = PersonnelFileAcknowledgement::query()->withoutGlobalScopes()->where('user_id', $u->id)->get()->keyBy('document_version_id');
        foreach ($documents as $document) {
            $rows[] = [
                'title' => $document->title,
                'category' => $document->hr_category?->label(),
                'valid_from' => $this->date($document->valid_from),
                'valid_until' => $this->date($document->valid_until),
                'retention_until' => $this->date($document->retention_until),
                'version' => $document->currentVersion !== null ? 'v' . $document->currentVersion->version_no : null,
                'file' => $document->currentVersion?->original_name,
                'created_at' => $this->str($document->created_at),
                'read_confirmed_at' => $this->str($acknowledgements->get($document->current_version_id)?->acknowledged_at),
            ];
        }

        // Einreichungen (MVP-987) mit Entscheidung und Grund.
        $submissions = [];
        foreach (PersonnelFileSubmission::query()->withoutGlobalScopes()->where('user_id', $u->id)->orderBy('created_at')->get() as $submission) {
            $submissions[] = [
                'title' => $submission->title,
                'category' => $submission->hr_category->label(),
                'status' => $submission->status->label(),
                'file' => $submission->original_name,
                'created_at' => $this->str($submission->created_at),
                'reviewed_at' => $this->str($submission->reviewed_at),
                'review_note' => $submission->review_note,
            ];
        }

        return [
            'lists' => [__('hr.personnel_file.field.documents') => $rows, __('hr.personnel_file.submission.title') => $submissions],
            'families' => [
                $this->family(
                    'documents',
                    __('hr.personnel_file.title'),
                    $query,
                    'created_at',
                    columns: ['created_at' => __('Datum'), 'title' => __('Titel'), 'hr_category' => __('Kategorie'), 'retention_until' => __('Aufbewahrung bis')],
                ),
            ],
        ];
    }
}
