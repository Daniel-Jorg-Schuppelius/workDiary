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

use App\Models\{Document, User};
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
        foreach ((clone $query)->with('currentVersion')->orderBy('created_at')->get() as $document) {
            $rows[] = [
                'title' => $document->title,
                'category' => $document->hr_category?->label(),
                'valid_from' => $this->date($document->valid_from),
                'valid_until' => $this->date($document->valid_until),
                'retention_until' => $this->date($document->retention_until),
                'version' => $document->currentVersion !== null ? 'v' . $document->currentVersion->version_no : null,
                'file' => $document->currentVersion?->original_name,
                'created_at' => $this->str($document->created_at),
            ];
        }

        return [
            'lists' => [__('hr.personnel_file.field.documents') => $rows],
            'families' => [
                $this->family('documents', __('hr.personnel_file.title'), $query, 'created_at'),
            ],
        ];
    }
}
