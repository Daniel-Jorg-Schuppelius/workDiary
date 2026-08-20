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

use App\Enums\Form\{FormFieldType, FormTemplateStatus};
use App\Models\{FormSubmission, FormTemplate, User};
use App\Services\Attachments\FileAttacher;
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
    /**
     * Legt eine Vorlage an (Status default draft). Die Felddefinition wird
     * strukturell validiert und normalisiert (FormFieldDefinition).
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException bei Strukturfehlern in der Felddefinition
     */
    public function createTemplate(User $creator, array $attributes): FormTemplate {
        $fields = FormFieldDefinition::normalize((array) ($attributes['fields'] ?? []));

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
            $id = \App\Support\Sqid::decodeOrNumeric(\App\Models\EntryType::class, (string) $attributes['target_entry_type']);
            if ($id !== null) {
                $target['entry_type_id'] = $id;
            }
        }
        if (($attributes['target_customer'] ?? '') !== '') {
            $id = \App\Support\Sqid::decodeOrNumeric(\App\Models\Customer::class, (string) $attributes['target_customer']);
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
            $payload['fields'] = FormFieldDefinition::normalize((array) $attributes['fields']);
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
     *
     * @throws ValidationException bei inaktiver Vorlage oder ungültigen Werten
     */
    public function submit(FormTemplate $template, ?Model $subject, array $values, User $user, array $files = [], array $signatures = [], array $deferredKeys = []): FormSubmission {
        if ($template->status !== FormTemplateStatus::Active) {
            throw ValidationException::withMessages([
                'form_template_id' => (string) __('form.validation.template_not_active'),
            ]);
        }

        $fields = (array) $template->fields;

        // Bedingungslogik (Rang 33): nur aktuell sichtbare Felder werden
        // validiert — unsichtbare Pflichtfelder blockieren nicht und ihre
        // Werte/Dateien werden nicht gespeichert.
        $visibleFields = FormFieldDefinition::visibleFields($fields, $values);

        $validated = Validator::make(
            ['values' => $values],
            FormFieldDefinition::rules($visibleFields),
            [],
            FormFieldDefinition::attributeNames($visibleFields),
        )->validate();

        // Pflicht-Prüfung für Attachment-Felder (Rang 32): required + kein Inhalt.
        $this->assertRequiredAttachments($visibleFields, $files, $signatures, $deferredKeys);

        $submission = DB::transaction(function () use ($template, $subject, $fields, $visibleFields, $validated, $user, $files, $signatures, $deferredKeys): FormSubmission {
            $submission = FormSubmission::query()->create([
                'organization_id' => $user->organization_id,
                'form_template_id' => $template->id,
                'fields_snapshot' => $fields,
                'values' => FormFieldDefinition::normalizeValues($visibleFields, (array) ($validated['values'] ?? [])),
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'submitted_by_user_id' => $user->id,
                'submitted_at' => now(),
            ]);

            $this->storeFieldAttachments($submission, $visibleFields, $files, $signatures, $user, $deferredKeys);

            $template->audit('form.submitted', [
                'actor_user_id' => $user->id,
                'form_submission_id' => $submission->id,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
            ]);

            return $submission;
        });

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

        return $submission;
    }

    private function normalizeDescription(mixed $description): ?string {
        $description = trim((string) $description);

        return $description === '' ? null : $description;
    }

    /**
     * Erzwingt Inhalt für Pflicht-Attachment-Felder (Foto/Datei/Unterschrift).
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, UploadedFile>  $files
     * @param  array<string, string>  $signatures
     * @param  list<string>  $deferredKeys
     *
     * @throws ValidationException
     */
    private function assertRequiredAttachments(array $fields, array $files, array $signatures, array $deferredKeys = []): void {
        $errors = [];
        foreach ($fields as $field) {
            $type = FormFieldType::from((string) $field['type']);
            if (! $type->storesAttachment() || ! ($field['required'] ?? false)) {
                continue;
            }
            $key = (string) $field['key'];
            $present = $type->isSignature()
                ? (isset($signatures[$key]) && trim($signatures[$key]) !== '')
                : (($files[$key] ?? null) instanceof UploadedFile || in_array($key, $deferredKeys, true));
            if (! $present) {
                $errors['values.' . $key] = (string) __('validation.required', ['attribute' => (string) $field['label']]);
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Legt Foto-/Datei-/Unterschrift-Inhalte als Attachment (meta_type
     * `field:<key>`) am Submission ab und schreibt einen Anzeige-Marker in
     * `values` (Dateiname bzw. „signed").
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, UploadedFile>  $files
     * @param  array<string, string>  $signatures
     * @param  list<string>  $deferredKeys
     */
    private function storeFieldAttachments(FormSubmission $submission, array $fields, array $files, array $signatures, User $user, array $deferredKeys = []): void {
        $markers = [];
        foreach ($fields as $field) {
            $key = (string) $field['key'];
            $type = FormFieldType::from((string) $field['type']);

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
        $field = null;
        foreach ((array) $submission->fields_snapshot as $candidate) {
            if ((string) $candidate['key'] === $fieldKey) {
                $field = $candidate;
                break;
            }
        }

        if ($field === null || ! FormFieldType::from((string) $field['type'])->isUpload()) {
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
        $binary = $this->decodePng($base64);
        if ($binary === null) {
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

    /** Dekodiert und prüft ein Base64-PNG (Magic-Bytes, Größenlimit 1 MB). */
    private function decodePng(string $base64): ?string {
        $base64 = preg_replace('#^data:image/png;base64,#', '', trim($base64)) ?? '';
        $binary = base64_decode($base64, true);
        if ($binary === false || strlen($binary) > 1_000_000 || ! str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            return null;
        }

        return $binary;
    }
}
