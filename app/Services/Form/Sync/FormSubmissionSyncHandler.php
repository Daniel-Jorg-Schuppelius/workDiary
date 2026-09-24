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
use App\Services\Sync\Contracts\SyncCommandHandler;
use App\Support\Sqid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{Gate, Validator};
use Illuminate\Validation\Rule;
use RuntimeException;

/** Formular offline ausfüllen (Phase 3, MVP-367) inkl. angekündigter Foto-/Dateifelder (W4.1). */
final class FormSubmissionSyncHandler implements SyncCommandHandler {
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

        $submission = $this->forms->submit(
            $template,
            $subject,
            (array) ($data['values'] ?? []),
            $user,
            deferredKeys: array_values(array_filter((array) ($data['pending_files'] ?? []), 'is_string')),
        );

        return 'form_submissions:' . $submission->id;
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
