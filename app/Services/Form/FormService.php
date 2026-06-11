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

use App\Enums\Form\FormTemplateStatus;
use App\Models\{FormSubmission, FormTemplate, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{DB, Validator};
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
            'created_by_user_id' => $creator->id,
        ]));
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
     *
     * @throws ValidationException bei inaktiver Vorlage oder ungültigen Werten
     */
    public function submit(FormTemplate $template, ?Model $subject, array $values, User $user): FormSubmission {
        if ($template->status !== FormTemplateStatus::Active) {
            throw ValidationException::withMessages([
                'form_template_id' => (string) __('form.validation.template_not_active'),
            ]);
        }

        $fields = (array) $template->fields;

        $validated = Validator::make(
            ['values' => $values],
            FormFieldDefinition::rules($fields),
            [],
            FormFieldDefinition::attributeNames($fields),
        )->validate();

        $submission = DB::transaction(function () use ($template, $subject, $fields, $validated, $user): FormSubmission {
            $submission = FormSubmission::query()->create([
                'organization_id' => $user->organization_id,
                'form_template_id' => $template->id,
                'fields_snapshot' => $fields,
                'values' => FormFieldDefinition::normalizeValues($fields, (array) ($validated['values'] ?? [])),
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'submitted_by_user_id' => $user->id,
                'submitted_at' => now(),
            ]);

            $template->audit('form.submitted', [
                'actor_user_id' => $user->id,
                'form_submission_id' => $submission->id,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
            ]);

            return $submission;
        });

        // Telemetry-Light (Feature 036): aggregierter Org-Tageszähler, fire-and-forget.
        app(\App\Services\Metrics\OperationsMetricsService::class)->increment('forms.submitted', (int) $submission->organization_id);

        return $submission;
    }

    private function normalizeDescription(mixed $description): ?string {
        $description = trim((string) $description);

        return $description === '' ? null : $description;
    }
}
