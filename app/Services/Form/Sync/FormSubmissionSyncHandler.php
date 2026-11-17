<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormSubmissionSyncHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Form\Sync;

use App\Http\Controllers\Form\FormSubmissionController;
use App\Models\Form\{FormSubmission, FormTemplate};
use App\Models\Platform\User;
use App\Services\Form\FormService;
use App\Services\Sync\Contracts\{SyncAttachmentTarget, SyncCommandHandler};
use App\Support\Sqid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Gate, Validator};
use Illuminate\Validation\Rule;
use RuntimeException;

/** Formular offline ausfüllen (Phase 3, MVP-367) inkl. angekündigter Foto-/Dateifelder (W4.1). */
final class FormSubmissionSyncHandler implements SyncAttachmentTarget, SyncCommandHandler {
    public function __construct(private readonly FormService $forms) {}

    /** @return list<string> */
    public function types(): array {
        return ['form.submission'];
    }

    public function handle(User $user, string $type, array $payload): string {
        return match ($type) {
            'form.submission' => $this->formSubmission($user, $payload),
            default => throw new RuntimeException('Unbekannter Sync-Befehlstyp: ' . $type),
        };
    }

    /** Nachgereichte Datei eines angekündigten Feldes (W4.1). */
    public function attach(User $user, string $type, string $resultRef, string $field, UploadedFile $file): bool {
        $submission = FormSubmission::query()->find((int) str_replace('form_submissions:', '', $resultRef));
        if ($submission === null) {
            return false;
        }
        $this->forms->attachDeferred($submission, $field, $file, $user);

        return true;
    }

    /**
     * Formular offline ausfüllen (Phase 3, MVP-367): Werte plus — seit dem
     * Audit 2026-08 (W4.1) — angekündigte Foto-/Datei-Felder (`pending_files`).
     * Die Abgabe entsteht sofort und trägt einen Nachreich-Marker; die Inhalte
     * lädt der Client danach über `api.internal.sync.attachments` hoch. Ohne
     * diese Ankündigung würde ein Pflicht-Fotofeld die ganze Abgabe abweisen —
     * genau der Fall, der die Foto-Queue nötig machte.
     *
     * Unterschriften bleiben dem Online-Weg vorbehalten (Konzept §5).
     *
     * @param  array<string, mixed>  $payload
     */
    private function formSubmission(User $user, array $payload): string {
        if (! Gate::forUser($user)->allows('create', FormSubmission::class)) {
            throw new RuntimeException((string) __('Keine Berechtigung für Formulare.'));
        }

        $data = Validator::make($payload, [
            'template' => ['required', 'string'],
            'subject_kind' => ['nullable', 'string', Rule::in(array_keys(FormSubmissionController::SUBJECT_MAP))],
            'subject_id' => ['nullable', 'string', 'required_with:subject_kind'],
            'values' => ['nullable', 'array'],
            // Lange Formulare (Feature 203): Ergänzungen und der Entwurf, der beim
            // Abgleich abgesendet wird, statt eine zweite Einsendung anzulegen.
            'notes' => ['nullable', 'array'],
            'notes.*' => ['nullable', 'string', 'max:2000'],
            'draft' => ['nullable', 'string', 'max:64'],
            // Offline erfasste Foto-/Datei-Felder: der Inhalt kommt separat
            // über `api.internal.sync.attachments` nach (Audit 2026-08, W4.1).
            'pending_files' => ['nullable', 'array'],
            'pending_files.*' => ['string', 'max:64'],
        ])->validate();

        $templateId = Sqid::decodeOrNumeric(FormTemplate::class, $data['template']);
        /** @var FormTemplate|null $template */
        $template = ($templateId !== null && $templateId > 0)
            ? FormTemplate::query()->active()->find($templateId)
            : null;

        if ($template === null) {
            throw new RuntimeException((string) __('Formularvorlage nicht gefunden.'));
        }

        $subject = null;
        if (filled($data['subject_kind'] ?? null)) {
            $subject = $this->resolveFormSubject((string) $data['subject_kind'], (string) ($data['subject_id'] ?? ''));
        }

        $deferredKeys = array_values(array_filter((array) ($data['pending_files'] ?? []), 'is_string'));
        /** @var array<string, mixed> $notes */
        $notes = (array) ($data['notes'] ?? []);
        $draft = $this->ownDraft($user, $template, (string) ($data['draft'] ?? ''));
        if ($draft !== null && ! $draft->isDraft()) {
            return 'form_submissions:' . $draft->id;
        }

        $submission = $draft !== null
            ? $this->forms->submitDraft($draft, (array) ($data['values'] ?? []), $user, deferredKeys: $deferredKeys, notes: $notes)
            : $this->forms->submit(
                $template,
                $subject,
                (array) ($data['values'] ?? []),
                $user,
                deferredKeys: $deferredKeys,
                notes: $notes,
            );

        return 'form_submissions:' . $submission->id;
    }

    /**
     * Eigene Einsendung zum Entwurfsschlüssel und zur Vorlage — sonst null
     * (fremder oder unbekannter Entwurf: es entsteht eine neue Einsendung).
     */
    private function ownDraft(User $user, FormTemplate $template, string $rawId): ?FormSubmission {
        $id = $rawId === '' ? null : Sqid::decodeOrNumeric(FormSubmission::class, $rawId);
        /** @var FormSubmission|null $draft */
        $draft = $id !== null && $id > 0 ? FormSubmission::query()->find($id) : null;
        if ($draft === null || (int) $draft->submitted_by_user_id !== (int) $user->id || (int) $draft->form_template_id !== (int) $template->id) {
            return null;
        }

        return $draft;
    }

    /** Subjekt-Auflösung über die Whitelist des Online-Wegs (org-gescopt). */
    private function resolveFormSubject(string $kind, string $rawId): Model {
        $class = FormSubmissionController::SUBJECT_MAP[$kind] ?? null;
        $id = $class !== null ? Sqid::decodeOrNumeric($class, $rawId) : null;

        /** @var Model|null $subject */
        $subject = ($class !== null && $id !== null && $id > 0)
            ? $class::query()->find($id)
            : null;

        if ($subject === null) {
            throw new RuntimeException((string) __('Bezugsobjekt nicht gefunden.'));
        }

        return $subject;
    }
}
