<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Form;

use App\Enums\Form\{FormSubmissionStatus, FormTemplateStatus};
use App\Exceptions\FieldDocumentConflictException;
use App\Models\Form\{FormSubmission, FormTemplate};
use App\Models\Platform\User;
use App\Services\Attachments\FileAttacher;
use App\Services\Concerns\AssertsValidatedTransition;
use App\Services\Fields\{FieldExtensionRegistry, FieldSchema, FieldValidator, FieldValues};
use CommonToolkit\Helper\Data\DataUrlHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage, Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Domain-Service Vorlagen- & Formularsystem (Feature 032).
 *
 * Vorlagen-Lebenszyklus: draft → active → archived (Reaktivierung aus
 * archived ist bewusst erlaubt — kein Datenverlust möglich, Submissions
 * hängen am Snapshot). Audit läuft über den Auditable-Trait
 * (created/updated/deleted) plus gezielte audit()-Events für
 * activate/archive/submit. Eine eigene Event-Tabelle gibt es bewusst
 * nicht — der Lebenszyklus ist trivial.
 */
class FormService {
    use AssertsValidatedTransition;

    public function __construct(
        private readonly FieldValidator $fieldValidator,
        private readonly FieldExtensionRegistry $extensions,
    ) {}

    /**
     * Legt eine Vorlage an (Status default draft). Die Felddefinition wird
     * strukturell validiert und normalisiert (FieldSchema::fromRows).
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException bei Strukturfehlern in der Felddefinition
     */
    public function createTemplate(User $creator, array $attributes): FormTemplate {
        $fields = FieldSchema::fromRows((array) ($attributes['fields'] ?? []))->toArray();

        return DB::transaction(fn(): FormTemplate => FormTemplate::query()->create([
            'organization_id' => $creator->organization_id,
            'name' => $attributes['name'],
            'description' => $this->normalizeDescription($attributes['description'] ?? null),
            'status' => FormTemplateStatus::Draft->value,
            'fields' => $fields,
            // Gültigkeit + Zuordnung (Feature 032 MVP; Vollaudit 2026-07, M11).
            'valid_from' => $attributes['valid_from'] ?? null,
            'valid_until' => $attributes['valid_until'] ?? null,
            'target' => $this->normalizeTarget($attributes),
            'created_by_user_id' => $creator->id,
        ]));
    }

    /**
     * Zuordnung aus Formular-Sqids → JSON-Target (M11); leere Auswahl = null.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{entry_type_id?: int, customer_id?: int}|null
     */
    private function normalizeTarget(array $attributes): ?array {
        $target = [];
        if (($attributes['target_entry_type'] ?? '') !== '') {
            $id = \App\Support\Sqid::decodeOrNumeric(\App\Models\Classification\EntryType::class, (string) $attributes['target_entry_type']);
            if ($id !== null) {
                $target['entry_type_id'] = $id;
            }
        }
        if (($attributes['target_customer'] ?? '') !== '') {
            $id = \App\Support\Sqid::decodeOrNumeric(\App\Models\Customer\Customer::class, (string) $attributes['target_customer']);
            if ($id !== null) {
                $target['customer_id'] = $id;
            }
        }

        return $target === [] ? null : $target;
    }

    /**
     * Aktualisiert Name/Beschreibung/Felder. Bestehende Submissions sind
     * durch fields_snapshot versionssicher — eine Änderung der Definition
     * wirkt nur auf ZUKÜNFTIGE Submissions.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException bei Strukturfehlern in der Felddefinition
     */
    public function updateTemplate(FormTemplate $template, User $actor, array $attributes): FormTemplate {
        $payload = [
            'name' => $attributes['name'] ?? $template->name,
        ];
        if (array_key_exists('description', $attributes)) {
            $payload['description'] = $this->normalizeDescription($attributes['description']);
        }
        if (array_key_exists('fields', $attributes)) {
            $payload['fields'] = FieldSchema::fromRows((array) $attributes['fields'])->toArray();
        }
        // Gültigkeit + Zuordnung (M11): nur ändern, wenn die Felder mitkommen.
        if (array_key_exists('valid_from', $attributes) || array_key_exists('valid_until', $attributes)) {
            $payload['valid_from'] = $attributes['valid_from'] ?? null;
            $payload['valid_until'] = $attributes['valid_until'] ?? null;
        }
        if (array_key_exists('target_entry_type', $attributes) || array_key_exists('target_customer', $attributes)) {
            $payload['target'] = $this->normalizeTarget($attributes);
        }

        return DB::transaction(function () use ($template, $actor, $payload): FormTemplate {
            unset($actor);
            $template->update($payload);

            return $template;
        });
    }

    /** Aktiviert die Vorlage (ausfüllbar). */
    public function activate(FormTemplate $template, User $actor): FormTemplate {
        if ($template->status === FormTemplateStatus::Active) {
            return $template;
        }

        return DB::transaction(function () use ($template, $actor): FormTemplate {
            $template->update(['status' => FormTemplateStatus::Active->value]);
            $template->audit('form.template.activated', ['actor_user_id' => $actor->id]);

            return $template;
        });
    }

    /** Archiviert (fällt aus der Ausfüll-Auswahl, Submissions bleiben). */
    public function archive(FormTemplate $template, User $actor): FormTemplate {
        if ($template->status === FormTemplateStatus::Archived) {
            return $template;
        }

        return DB::transaction(function () use ($template, $actor): FormTemplate {
            $template->update(['status' => FormTemplateStatus::Archived->value]);
            $template->audit('form.template.archived', ['actor_user_id' => $actor->id]);

            return $template;
        });
    }

    /** Soft-Delete der Vorlage (Submissions bleiben über Snapshot lesbar). */
    public function deleteTemplate(FormTemplate $template, User $actor): void {
        DB::transaction(function () use ($template, $actor): void {
            // Fachliches Event VOR dem Delete, damit es gemeinsam mit dem
            // Auditable-`deleted` in der Hash-Kette landet.
            $template->audit('form.template.deleted', ['actor_user_id' => $actor->id]);
            $template->delete();
        });
    }

    /**
     * Füllt eine aktive Vorlage aus: validiert die Werte gegen die
     * Felddefinition und speichert sie zusammen mit dem fields_snapshot
     * (Definition zum Ausfüllzeitpunkt — Versionssicherheit).
     *
     * @param  Model|null  $subject  optionaler Bezug (DiaryEntry/Customer/Asset/Project)
     * @param  array<string, mixed>  $values  Eingaben, keyed by Feld-Key
     * @param  array<string, UploadedFile>  $files  Datei-/Foto-Uploads je Feld-Key (Rang 32)
     * @param  array<string, string>  $signatures  Base64-PNG je Unterschriftsfeld (Rang 32)
     * @param  list<string>  $deferredKeys  Offline erfasste Upload-Felder, deren Inhalt nachgereicht wird (Audit 2026-08, W4.1)
     * @param  array<string, mixed>  $notes  Ergänzungen je Feld-Key (Feature 203, MVP-1651)
     *
     * @throws ValidationException bei inaktiver Vorlage oder ungültigen Werten
     */
    public function submit(FormTemplate $template, ?Model $subject, array $values, User $user, array $files = [], array $signatures = [], array $deferredKeys = [], array $notes = []): FormSubmission {
        $this->assertActive($template);

        $fields = (array) $template->fields;
        [$visible, $normalized] = $this->validatedValues(FieldSchema::fromArray($fields), $values, $files, $signatures, $deferredKeys, $notes);

        $submission = DB::transaction(function () use ($template, $subject, $fields, $visible, $normalized, $user, $files, $signatures, $deferredKeys): FormSubmission {
            $submission = FormSubmission::query()->create([
                'organization_id' => $user->organization_id,
                'form_template_id' => $template->id,
                'fields_snapshot' => $fields,
                'values' => $normalized->toArray(),
                'status' => FormSubmissionStatus::Submitted->value,
                'version' => 1,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'submitted_by_user_id' => $user->id,
                'submitted_at' => now(),
            ]);

            $this->storeFieldAttachments($submission, $visible, $files, $signatures, $user, $deferredKeys);
            $this->auditSubmitted($template, $submission, $user);

            return $submission;
        });

        $this->afterSubmit($submission, $user);

        return $submission;
    }

    /**
     * Entwurf einer Einsendung (Feature 203, MVP-1650): entsteht mit dem ersten
     * gespeicherten Feld. Gibt es zu Vorlage und Bezug schon einen eigenen
     * Entwurf, wird dieser fortgesetzt statt ein zweiter angelegt. Der Entwurf
     * friert die Felddefinition ein wie das Absenden.
     *
     * @throws ValidationException bei inaktiver Vorlage
     */
    public function startDraft(FormTemplate $template, ?Model $subject, User $user): FormSubmission {
        $this->assertActive($template);

        return DB::transaction(function () use ($template, $subject, $user): FormSubmission {
            $existing = $this->ownDraft($template, $subject, $user);
            if ($existing !== null) {
                return $existing;
            }

            return FormSubmission::query()->create([
                'organization_id' => $template->organization_id,
                'form_template_id' => $template->id,
                'fields_snapshot' => (array) $template->fields,
                'values' => [],
                'status' => FormSubmissionStatus::Draft->value,
                'version' => 1,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'submitted_by_user_id' => $user->id,
                'submitted_at' => null,
            ]);
        });
    }

    /** Eigener offener Entwurf zu Vorlage und Bezug (der jüngste). */
    public function ownDraft(FormTemplate $template, ?Model $subject, User $user): ?FormSubmission {
        return FormSubmission::query()
            ->drafts()
            ->where('form_template_id', $template->id)
            ->where('submitted_by_user_id', $user->id)
            ->where('subject_type', $subject?->getMorphClass())
            ->where('subject_id', $subject?->getKey())
            ->latest('id')
            ->first();
    }

    /**
     * Entwurf auf die aktuelle Vorlage heben, wenn sie sich seit dem Anlegen
     * geändert hat (MVP-1651): Antworten, deren Antwortart nicht mehr passt,
     * bleiben als Ergänzung an der Frage erhalten. Liefert true, wenn sich
     * der Entwurf geändert hat.
     */
    public function rebaseDraft(FormSubmission $draft): bool {
        $template = $draft->template;
        if (! $draft->isDraft() || $template === null || $template->status !== FormTemplateStatus::Active) {
            return false;
        }
        $fields = (array) $template->fields;
        if ($fields === (array) $draft->fields_snapshot) {
            return false;
        }

        $values = FieldValues::fromArray($draft->values)
            ->rebase(FieldSchema::fromArray($draft->fields_snapshot), FieldSchema::fromArray($fields), $this->extensions);
        $draft->forceFill([
            'fields_snapshot' => $fields,
            'values' => $values->toArray(),
            'version' => $draft->version + 1,
        ])->saveQuietly();

        return true;
    }

    /**
     * Entwurf absenden (MVP-1650): prüft jetzt alle Felder samt Pflicht gegen
     * den Stand des Entwurfs und schließt ihn ab. Mit `$expectedVersion`
     * verweigert ein veralteter Stand das Absenden (Hinweis statt stillem
     * Überschreiben).
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, UploadedFile>  $files
     * @param  array<string, string>  $signatures
     * @param  list<string>  $deferredKeys
     * @param  array<string, mixed>  $notes
     *
     * @throws ValidationException bei ungültigen Werten oder wenn kein Entwurf vorliegt
     * @throws FieldDocumentConflictException bei veralteter Version
     */
    public function submitDraft(FormSubmission $draft, array $values, User $user, array $files = [], array $signatures = [], array $deferredKeys = [], array $notes = [], ?int $expectedVersion = null): FormSubmission {
        $submission = DB::transaction(function () use ($draft, $values, $user, $files, $signatures, $deferredKeys, $notes, $expectedVersion): FormSubmission {
            /** @var FormSubmission $locked */
            $locked = FormSubmission::query()->whereKey($draft->getKey())->lockForUpdate()->firstOrFail();
            $this->assertValidatedTransition($locked->status, FormSubmissionStatus::Submitted, 'form.validation.not_draft');
            if ($expectedVersion !== null && $locked->version !== $expectedVersion) {
                throw new FieldDocumentConflictException(FieldDocumentConflictException::STALE, $locked->version);
            }

            [$visible, $normalized] = $this->validatedValues(
                FieldSchema::fromArray($locked->fields_snapshot),
                $values,
                $files,
                $signatures,
                $deferredKeys,
                $notes,
                FieldValues::fromArray($locked->values),
            );

            $locked->update([
                'values' => $normalized->toArray(),
                'status' => FormSubmissionStatus::Submitted->value,
                'version' => $locked->version + 1,
                'submitted_at' => now(),
            ]);

            $this->storeFieldAttachments($locked, $visible, $files, $signatures, $user, $deferredKeys);
            if ($locked->template !== null) {
                $this->auditSubmitted($locked->template, $locked, $user);
            }

            return $locked;
        });

        $this->afterSubmit($submission, $user);

        return $submission;
    }

    /** Entwurf verwerfen (nur Entwürfe; abgesendete Formulare bleiben Nachweis). */
    public function discardDraft(FormSubmission $draft): void {
        if (! $draft->isDraft()) {
            throw ValidationException::withMessages(['status' => (string) __('form.validation.not_draft')]);
        }
        $draft->delete();
    }

    /** @throws ValidationException */
    private function assertActive(FormTemplate $template): void {
        if ($template->status !== FormTemplateStatus::Active) {
            throw ValidationException::withMessages([
                'form_template_id' => (string) __('form.validation.template_not_active'),
            ]);
        }
    }

    /**
     * Gemeinsame Prüfung von Absenden und Entwurf-Absenden: nur aktuell
     * sichtbare Felder (Bedingungslogik, Rang 33), Pflicht samt Anhängen,
     * typtreue Werte; Ergänzungen des Entwurfs bleiben, Eingaben überschreiben sie.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, UploadedFile>  $files
     * @param  array<string, string>  $signatures
     * @param  list<string>  $deferredKeys
     * @param  array<string, mixed>  $notes
     * @return array{0: FieldSchema, 1: FieldValues}
     *
     * @throws ValidationException
     */
    private function validatedValues(FieldSchema $schema, array $values, array $files, array $signatures, array $deferredKeys, array $notes, ?FieldValues $draft = null): array {
        $visible = $schema->visibleFor($values);

        $validated = Validator::make(
            ['values' => $values],
            $this->fieldValidator->rules($visible),
            [],
            $visible->attributeNames(),
        )->validate();

        // Pflicht-Prüfung für Attachment-Felder (Rang 32): required + kein Inhalt.
        $this->assertRequiredAttachments($visible, $files, $signatures, $deferredKeys);

        $normalized = FieldValues::normalize($visible, (array) ($validated['values'] ?? []), $this->extensions);
        foreach ($draft?->notes() ?? [] as $key => $note) {
            if ($visible->has($key)) {
                $normalized = $normalized->withNote($key, $note);
            }
        }

        return [$visible, $normalized->withNotesFrom($visible, $notes)];
    }

    private function auditSubmitted(FormTemplate $template, FormSubmission $submission, User $user): void {
        $template->audit('form.submitted', [
            'actor_user_id' => $user->id,
            'form_submission_id' => $submission->id,
            'subject_type' => $submission->subject_type,
            'subject_id' => $submission->subject_id,
        ]);
    }

    private function afterSubmit(FormSubmission $submission, User $user): void {
        // MVP-650: Einreichungen sind ab Abgabe unveränderlich — der
        // Designstand wird mit eingefroren (idempotent).
        if ($submission->organization !== null) {
            app(\App\Services\DocumentDesign\DocumentDesignRenderer::class)->snapshot(
                $submission,
                \App\Enums\DocumentDesign\RenderDocumentKind::Form,
                $submission->organization,
                user: $user,
            );
        }

        // Telemetry-Light (Feature 036): aggregierter Org-Tageszähler, fire-and-forget.
        app(\App\Services\Metrics\OperationsMetricsService::class)->increment('forms.submitted', (int) $submission->organization_id);
    }

    private function normalizeDescription(mixed $description): ?string {
        $description = trim((string) $description);

        return $description === '' ? null : $description;
    }

    /**
     * Erzwingt Inhalt für Pflicht-Attachment-Felder (Foto/Datei/Unterschrift).
     *
     * @param  array<string, UploadedFile>  $files
     * @param  array<string, string>  $signatures
     * @param  list<string>  $deferredKeys
     *
     * @throws ValidationException
     */
    private function assertRequiredAttachments(FieldSchema $schema, array $files, array $signatures, array $deferredKeys = []): void {
        $errors = $this->fieldValidator->missingAttachments($schema, $files, $signatures, $deferredKeys);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Legt Foto-/Datei-/Unterschrift-Inhalte als Attachment (meta_type
     * `field:<key>`) am Submission ab und schreibt einen Anzeige-Marker in
     * `values` (Dateiname bzw. „signed").
     *
     * @param  array<string, UploadedFile>  $files
     * @param  array<string, string>  $signatures
     * @param  list<string>  $deferredKeys
     */
    private function storeFieldAttachments(FormSubmission $submission, FieldSchema $schema, array $files, array $signatures, User $user, array $deferredKeys = []): void {
        $markers = [];
        foreach ($schema as $field) {
            $key = $field->key;
            $type = $field->type;

            if ($type->isUpload() && ($files[$key] ?? null) instanceof UploadedFile) {
                $attachment = app(FileAttacher::class)->store($submission, $files[$key], (int) $user->id);
                $attachment->forceFill(['meta_type' => 'field:' . $key])->save();
                $markers[$key] = $files[$key]->getClientOriginalName();
            } elseif ($type->isSignature() && isset($signatures[$key]) && trim($signatures[$key]) !== '') {
                if ($this->storeSignature($submission, $key, $signatures[$key], $user)) {
                    $markers[$key] = 'signed';
                }
            } elseif ($type->isUpload() && in_array($key, $deferredKeys, true)) {
                // Offline erfasst, Inhalt kommt nach (Audit 2026-08, W4.1):
                // sichtbarer Marker statt Leere — sonst sähe das Formular
                // aus, als hätte niemand ein Foto gemacht.
                $markers[$key] = (string) __('form.attachment.pending');
            }
        }

        if ($markers !== []) {
            $submission->update(['values' => array_merge((array) $submission->values, $markers)]);
        }
    }

    /**
     * Nachgereichten Offline-Anhang ablegen (Feature 035 Phase 3; Audit
     * 2026-08, W4.1). Der Weg ist derselbe wie online — `FileAttacher` plus
     * `meta_type = field:<key>`; nur der Zeitpunkt unterscheidet sich.
     *
     * Wirft, wenn der Feld-Key kein Upload-Feld des Formulars ist: sonst
     * könnte ein Client beliebige Dateien unter erfundenen Feldnamen an eine
     * fremde Abgabe hängen.
     */
    public function attachDeferred(FormSubmission $submission, string $fieldKey, UploadedFile $file, User $user): void {
        $field = FieldSchema::fromArray($submission->fields_snapshot)->get($fieldKey);
        if ($field === null || ! $field->type->isUpload()) {
            throw ValidationException::withMessages([
                'field' => (string) __('form.validation.no_upload_field'),
            ]);
        }

        $attachment = app(FileAttacher::class)->store($submission, $file, (int) $user->id);
        $attachment->forceFill(['meta_type' => 'field:' . $fieldKey])->save();

        $submission->update([
            'values' => array_merge((array) $submission->values, [$fieldKey => $file->getClientOriginalName()]),
        ]);
    }

    /** Base64-PNG einer Unterschrift → Storage + Attachment (meta_type field:<key>). */
    private function storeSignature(FormSubmission $submission, string $key, string $base64, User $user): bool {
        $binary = DataUrlHelper::decode($base64, ['image/png'], 1_000_000);
        if ($binary === false) {
            return false;
        }

        $path = 'form-signatures/' . now()->format('Y/m') . '/' . Str::uuid()->toString() . '.png';
        Storage::disk('local')->put($path, $binary);

        $submission->attachments()->create([
            'user_id' => $user->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'signature.png',
            'mime' => 'image/png',
            'size' => strlen($binary),
            'meta_type' => 'field:' . $key,
        ]);

        return true;
    }
}
