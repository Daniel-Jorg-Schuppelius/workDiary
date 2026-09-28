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
use App\Enums\Hr\{HrDocumentCategory, PersonnelFileSubmissionStatus};
use App\Enums\Notification\NotificationEvent;
use App\Models\Document\Document;
use App\Models\Hr\{PersonnelFileAcknowledgement, PersonnelFileSubmission};
use App\Models\Platform\User;
use App\Services\Attachments\FileAttacher;
use App\Services\Concerns\AssertsValidatedTransition;
use App\Services\Document\DocumentService;
use App\Services\Notification\NotificationDispatcher;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\{Rule, ValidationException};

/**
 * Digitale Personalakte (Feature 141, MVP-708): Dokumente mit documentable =
 * User. Geschäftsregeln, die das allgemeine DMS nicht kennt:
 *  - IMMER vertraulich (erzwungen, nicht abwählbar),
 *  - HR-Kategorie mit Aufbewahrung ab Austritt (users.left_at + Jahre),
 *  - eigene Audit-Events (hrFile.*) inkl. Download,
 *  - Löschen ist Vernichtung (Dateien + Versionen), kein Papierkorb.
 */
class PersonnelFileService {
    use AssertsValidatedTransition;

    public function __construct(private readonly DocumentService $documents, private readonly NotificationDispatcher $notifier) {}

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
            'is_ack_required' => ['nullable', 'boolean'],
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
        return $this->filed($member, $actor, $attributes, fn (array $documentAttributes): Document => $this->documents->create($member, $actor, $documentAttributes, $file));
    }

    /**
     * Wie {@see create()}, mit Dateiinhalt statt Upload (etwa der unterschriebene
     * Arbeitsvertrag, MVP-939).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createFromContents(User $member, User $actor, array $attributes, string $contents, string $originalName, ?string $mime = null): Document {
        return $this->filed($member, $actor, $attributes, fn (array $documentAttributes): Document => $this->documents->createFromContents($member, $actor, $documentAttributes, $contents, $originalName, $mime));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  callable(array<string, mixed>): Document  $store
     */
    private function filed(User $member, User $actor, array $attributes, callable $store): Document {
        $category = HrDocumentCategory::from((string) $attributes['hr_category']);

        $document = $store([
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
        ]);
        if ((bool) ($attributes['is_ack_required'] ?? false)) {
            $document->forceFill(['is_ack_required' => true])->save();
            $this->requestAcknowledgement($document);
        }

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
        $wasAckRequired = (bool) $document->is_ack_required;

        $document = DB::transaction(function () use ($document, $actor, $attributes, $category, $member): Document {
            $this->documents->update($document, $actor, [
                'title' => $attributes['title'],
                'document_type' => $category->documentType()->value,
                'valid_from' => $attributes['valid_from'] ?? null,
                'valid_until' => $attributes['valid_until'] ?? null,
                'description' => $attributes['description'] ?? null,
                'confidential' => true,
            ]);

            $document->forceFill([
                'is_ack_required' => (bool) ($attributes['is_ack_required'] ?? false),
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
        if (! $wasAckRequired) {
            $this->requestAcknowledgement($document);
        }

        return $document;
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

    /**
     * Lesebestätigung der betroffenen Person für die aktuelle Version (MVP-987);
     * eine erneute Bestätigung derselben Version ändert nichts.
     */
    public function acknowledge(Document $document, User $member): PersonnelFileAcknowledgement {
        $version = $document->currentVersion;
        if (! $document->isPersonnelFile() || (int) $document->documentable_id !== (int) $member->id || ! $document->is_ack_required || $version === null) {
            throw ValidationException::withMessages(['document' => (string) __('hr.personnel_file.error.ack_not_requested')]);
        }

        $acknowledgement = PersonnelFileAcknowledgement::query()->firstOrCreate(
            ['document_id' => $document->id, 'document_version_id' => $version->id],
            ['organization_id' => $document->organization_id, 'user_id' => $member->id, 'acknowledged_at' => now()],
        );
        if ($acknowledgement->wasRecentlyCreated) {
            $document->audit('hrFile.acknowledged', ['member_user_id' => $member->id, 'version_no' => $version->version_no]);
        }

        return $acknowledgement;
    }

    /** Hinweis an die betroffene Person, solange für die aktuelle Version eine Lesebestätigung angefordert ist. */
    public function requestAcknowledgement(Document $document): void {
        $member = $document->documentable;
        if (! $member instanceof User || ! $document->isPersonnelFile() || ! $document->is_ack_required) {
            return;
        }

        $this->notify(NotificationEvent::HrFileAckRequested, $document, $member, 'ack_requested_title', ['title' => $document->title], 'ack_requested_message', []);
    }

    /**
     * Bestätigte aktuelle Versionen der Dokumente, nach Dokument-ID.
     *
     * @param  iterable<Document>  $documents
     * @return array<int, PersonnelFileAcknowledgement>
     */
    public function acknowledgementsFor(iterable $documents): array {
        $versionIds = [];
        foreach ($documents as $document) {
            if ($document->current_version_id !== null) {
                $versionIds[] = (int) $document->current_version_id;
            }
        }

        return PersonnelFileAcknowledgement::query()->whereIn('document_version_id', $versionIds)->get()
            ->keyBy('document_id')->all();
    }

    /**
     * Unterlage der betroffenen Person einreichen (MVP-987). Die Datei liegt bis
     * zur Entscheidung außerhalb der Akte auf dem lokalen Laufwerk.
     *
     * @param  array{title: string, hr_category: string, note?: string|null}  $data
     */
    public function submit(User $member, array $data, UploadedFile $file): PersonnelFileSubmission {
        $this->documents->assertAllowedFile($file);
        $path = $file->storeAs('hr-submissions/' . $member->organization_id, Str::uuid()->toString(), 'local');

        $submission = PersonnelFileSubmission::query()->create([
            'organization_id' => $member->organization_id,
            'user_id' => $member->id,
            'title' => $data['title'],
            'hr_category' => $data['hr_category'],
            'note' => $data['note'] ?? null,
            'disk' => 'local',
            'path' => $path === false ? null : $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'status' => PersonnelFileSubmissionStatus::Submitted,
        ]);
        $submission->audit('hrFile.submitted', ['member_user_id' => $member->id, 'hr_category' => $data['hr_category']]);
        // Ohne Namen und Titel: Empfänger einer Regel können Personen außerhalb des Kreises sein.
        foreach ($this->circle((int) $member->organization_id) as $recipient) {
            if ((int) $recipient->id !== (int) $member->id) {
                $this->notify(NotificationEvent::HrFileSubmissionReceived, $submission, $recipient, 'submission_received_title', [], 'submission_received_message', [], route('personnel-file.submissions.index'));
            }
        }

        return $submission;
    }

    /**
     * Einreichung in die Akte übernehmen: Titel, Kategorie und Gültigkeit legt die
     * Personalabteilung fest, die Datei wird ein Dokument der Akte.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function accept(PersonnelFileSubmission $submission, User $actor, array $attributes): Document {
        $this->assertValidatedTransition($submission->status, PersonnelFileSubmissionStatus::Accepted, 'hr.personnel_file.error.submission_decided');
        $member = $submission->user()->firstOrFail();
        $contents = $submission->path === null ? null : Storage::disk($submission->disk)->get($submission->path);
        if ($contents === null) {
            throw ValidationException::withMessages(['submission' => (string) __('hr.personnel_file.error.submission_file_missing')]);
        }

        $document = DB::transaction(function () use ($submission, $actor, $attributes, $member, $contents): Document {
            $document = $this->createFromContents($member, $actor, $attributes, $contents, $submission->original_name, $submission->mime);
            $submission->forceFill([
                'status' => PersonnelFileSubmissionStatus::Accepted,
                'reviewer_user_id' => $actor->id,
                'reviewed_at' => now(),
                'document_id' => $document->id,
            ])->save();
            $submission->audit('hrFile.accepted', ['actor_user_id' => $actor->id, 'document_id' => $document->id]);

            return $document;
        });
        $this->discardFile($submission);
        $this->notify(NotificationEvent::HrFileSubmissionDecided, $submission, $member, 'submission_accepted_title', ['title' => $submission->title]);

        return $document;
    }

    public function reject(PersonnelFileSubmission $submission, User $actor, string $reason): PersonnelFileSubmission {
        $this->assertValidatedTransition($submission->status, PersonnelFileSubmissionStatus::Rejected, 'hr.personnel_file.error.submission_decided');
        $submission->forceFill([
            'status' => PersonnelFileSubmissionStatus::Rejected,
            'reviewer_user_id' => $actor->id,
            'reviewed_at' => now(),
            'review_note' => $reason,
        ])->save();
        $submission->audit('hrFile.rejected', ['actor_user_id' => $actor->id]);
        $this->discardFile($submission);
        $member = $submission->user()->first();
        if ($member !== null) {
            $this->notify(NotificationEvent::HrFileSubmissionDecided, $submission, $member, 'submission_rejected_title', ['title' => $submission->title], 'submission_rejected_message', ['reason' => $reason]);
        }

        return $submission;
    }

    /**
     * Kreis der Akte: wirksames `hrFile.viewAny` über Rolle, Direktvergabe oder Gruppe.
     *
     * @return Collection<int, User>
     */
    private function circle(int $organizationId): Collection {
        return User::query()->where('organization_id', $organizationId)
            ->with(['roles.permissions', 'permissions', 'userGroups.permissions', 'userGroups.roles.permissions'])
            ->get()
            ->filter(static fn (User $user): bool => $user->hasEffectivePermission(PersonnelFilePermissions::VIEW_ANY))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $titleParams
     * @param  array<string, mixed>  $messageParams
     */
    private function notify(NotificationEvent $event, Model $subject, User $recipient, string $title, array $titleParams, ?string $message = null, array $messageParams = [], ?string $url = null): void {
        $this->notifier->notify($event, $subject, $recipient, [
            'title' => (string) __('hr.personnel_file.notification.' . $title, $titleParams),
            'title_key' => 'hr.personnel_file.notification.' . $title,
            'title_params' => $titleParams,
            'message' => $message === null ? null : (string) __('hr.personnel_file.notification.' . $message, $messageParams),
            'message_key' => $message === null ? null : 'hr.personnel_file.notification.' . $message,
            'message_params' => $messageParams,
            'url' => $url ?? route('account.personnel-file'),
        ]);
    }

    private function discardFile(PersonnelFileSubmission $submission): void {
        if ($submission->path !== null) {
            Storage::disk($submission->disk)->delete($submission->path);
            $submission->forceFill(['path' => null])->save();
        }
    }
}
