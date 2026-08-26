<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PersonnelFileService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Hr;

use App\Enums\Document\DocumentStatus;
use App\Enums\Hr\HrDocumentCategory;
use App\Models\{Document, User};
use App\Services\Attachments\FileAttacher;
use App\Services\Document\DocumentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\Rule;

/**
 * Digitale Personalakte (Feature 141, MVP-708): Dokumente mit documentable =
 * User. Geschäftsregeln, die das allgemeine DMS nicht kennt:
 *  - IMMER vertraulich (erzwungen, nicht abwählbar),
 *  - HR-Kategorie mit Aufbewahrung ab Austritt (users.left_at + Jahre),
 *  - eigene Audit-Events (hrFile.*) inkl. Download,
 *  - Löschen ist Vernichtung (Dateien + Versionen), kein Papierkorb.
 */
class PersonnelFileService {
    public function __construct(private readonly DocumentService $documents) {}

    /**
     * Validierungsregeln des Akten-Dialogs (Upload und Metadaten).
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(bool $includeFile): array {
        $rules = [
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'hr_category' => ['required', Rule::enum(HrDocumentCategory::class)],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'description' => ['nullable', 'string', 'max:4000'],
        ];
        if ($includeFile) {
            $rules['file'] = ['required', 'file', 'max:' . FileAttacher::maxKb()];
            $rules['version_note'] = ['nullable', 'string', 'max:500'];
        }

        return $rules;
    }

    /**
     * Dokument in die Akte des Mitglieds aufnehmen. Vertraulich erzwungen;
     * für bereits ausgetretene Mitglieder wird das Aufbewahrungsende sofort
     * aus left_at + Kategorie-Frist gesetzt.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $member, User $actor, array $attributes, UploadedFile $file): Document {
        $category = HrDocumentCategory::from((string) $attributes['hr_category']);

        $document = $this->documents->create($member, $actor, [
            'title' => $attributes['title'],
            'document_type' => $category->documentType()->value,
            'status' => DocumentStatus::Active->value,
            'valid_from' => $attributes['valid_from'] ?? null,
            'valid_until' => $attributes['valid_until'] ?? null,
            'description' => $attributes['description'] ?? null,
            'version_note' => $attributes['version_note'] ?? null,
            'confidential' => true,
            'hr_category' => $category->value,
            'retention_until' => $this->retentionUntilFor($member, $category)?->toDateString(),
        ], $file);

        $document->audit('hrFile.created', [
            'member_user_id' => $member->id,
            'actor_user_id' => $actor->id,
            'hr_category' => $category->value,
        ]);

        return $document;
    }

    /**
     * Metadaten der Akte ändern (Titel, Kategorie, Gültigkeit, Beschreibung).
     * Kategorie-Wechsel berechnet das Aufbewahrungsende neu.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Document $document, User $actor, array $attributes): Document {
        $category = HrDocumentCategory::from((string) $attributes['hr_category']);
        $member = $document->documentable;

        return DB::transaction(function () use ($document, $actor, $attributes, $category, $member): Document {
            $this->documents->update($document, $actor, [
                'title' => $attributes['title'],
                'document_type' => $category->documentType()->value,
                'valid_from' => $attributes['valid_from'] ?? null,
                'valid_until' => $attributes['valid_until'] ?? null,
                'description' => $attributes['description'] ?? null,
                'confidential' => true,
            ]);

            $document->forceFill([
                'hr_category' => $category->value,
                'retention_until' => $member instanceof User
                    ? $this->retentionUntilFor($member, $category)?->toDateString()
                    : $document->retention_until?->toDateString(),
            ])->save();

            $document->audit('hrFile.updated', [
                'actor_user_id' => $actor->id,
                'hr_category' => $category->value,
            ]);

            return $document;
        });
    }

    /** Aufbewahrungsende: users.left_at + Kategorie-Jahre; null solange kein Austritt. */
    public function retentionUntilFor(User $member, HrDocumentCategory $category): ?CarbonImmutable {
        $leftAt = $member->left_at;
        if ($leftAt === null) {
            return null;
        }

        return CarbonImmutable::parse($leftAt->toDateString())->addYears($category->retentionYearsAfterExit());
    }

    /**
     * Beim Austritt (UserOffboardingService::execute): allen Akten-Dokumenten
     * des Mitglieds das Aufbewahrungsende setzen. Liefert die Anzahl.
     */
    public function applyRetentionOnExit(User $member): int {
        if ($member->left_at === null) {
            return 0;
        }

        $count = 0;
        foreach (Document::query()->withoutGlobalScopes()->whereNull('deleted_at')->personnelFilesOf($member)->get() as $document) {
            $category = $document->hr_category ?? HrDocumentCategory::Other;
            $document->forceFill([
                'retention_until' => $this->retentionUntilFor($member, $category)?->toDateString(),
            ])->save();
            $count++;
        }

        return $count;
    }

    /** Offene (nicht vernichtete) Akten-Dokumente eines Mitglieds. */
    public function openDocumentCount(User $member): int {
        return Document::query()->withoutGlobalScopes()->whereNull('deleted_at')->personnelFilesOf($member)->count();
    }

    /**
     * Vernichtung (manuell durch den Akten-Kreis oder bestätigter Retention-
     * Purge): Audit VOR dem Löschen, Dokument endgültig entfernen — die
     * append-only Versionen fallen über die DB-Kaskade (document_versions FK
     * CASCADE), nicht über Eloquent; danach die Dateien vom Storage. Bei
     * Personendaten gibt es bewusst keinen Papierkorb.
     */
    public function destroy(Document $document, User $actor, string $reason): void {
        /** @var list<array{0: string, 1: string}> $files */
        $files = $document->versions()->get()
            ->map(static fn($version): array => [(string) $version->disk, (string) $version->path])
            ->all();

        DB::transaction(function () use ($document, $actor, $reason): void {
            $document->audit('hrFile.deleted', [
                'reason' => $reason,
                'actor_user_id' => $actor->id,
                'member_user_id' => $document->documentable_id,
                'hr_category' => $document->hr_category?->value,
            ]);

            $document->forceFill(['current_version_id' => null])->save();
            $document->forceDelete();
        });

        foreach ($files as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }
    }
}
