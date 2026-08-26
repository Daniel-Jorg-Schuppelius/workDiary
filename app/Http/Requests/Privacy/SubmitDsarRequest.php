<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SubmitDsarRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Privacy;

use App\Enums\Privacy\DataSubjectRequestType;
use App\Http\Controllers\AttachmentController;
use App\Http\Requests\BaseFormRequest;
use App\Services\Attachments\FileAttacher;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\{Rule, Validator};

/**
 * Validiert eine oeffentlich gestellte Betroffenenanfrage (G11, MVP-728).
 *
 * Pflichtangaben bewusst minimal (Datenminimierung): Art, Name, Rueckadresse
 * und das Anliegen. Ein optionales Aktenzeichen (Kunden-/Personalnummer) hilft
 * bei der Zuordnung, ist aber nie erzwungen — die Identitaet prueft die
 * Datenschutzstelle spaeter im Fall.
 *
 * Anhaenge folgen dem Portal-Ticket-Muster: hoechstens fuenf Dateien, Groesse
 * aus {@see FileAttacher::maxKb()}, Endung UND server-ermittelter MIME-Typ
 * gegen die zentrale Allowlist des {@see AttachmentController}.
 */
class SubmitDsarRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'type' => ['required', Rule::enum(DataSubjectRequestType::class)],
            'full_name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'reference' => ['nullable', 'string', 'max:200'],
            'message' => ['required', 'string', 'min:10', 'max:20000'],
            'privacy_ack' => ['accepted'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:' . FileAttacher::maxKb()],
        ];
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $validator): void {
            foreach ($this->uploadedAttachments() as $file) {
                $ext = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?? ''));
                if (! in_array($ext, AttachmentController::ALLOWED_EXTENSIONS, true)
                    || ! in_array($file->getMimeType() ?? '', AttachmentController::ALLOWED_MIMES, true)) {
                    $validator->errors()->add('attachments', (string) __('Dateityp nicht erlaubt.'));

                    return;
                }
            }
        });
    }

    /** @return list<UploadedFile> */
    public function uploadedAttachments(): array {
        /** @var array<int, mixed> $files */
        $files = (array) $this->file('attachments', []);

        return array_values(array_filter($files, static fn ($f): bool => $f instanceof UploadedFile));
    }
}
