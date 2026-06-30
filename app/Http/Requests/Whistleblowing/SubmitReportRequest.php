<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SubmitReportRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Whistleblowing;

use App\Enums\Whistleblowing\{CaseCategory, ReporterMode};
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * Validiert eine oeffentliche Meldung. Pflichtfelder bewusst gering gehalten
 * (Abschnitt 7.1). Die autoritative MIME-/Inhaltspruefung der Anhaenge erfolgt
 * serverseitig im WhistleblowingAttachmentService.
 */
class SubmitReportRequest extends BaseFormRequest {
    /**
     * @return array<string, mixed>
     */
    public function rules(): array {
        /** @var array<int, string> $allowedMimes */
        $allowedMimes = (array) config('whistleblowing.uploads.allowed_mimes', []);
        $maxKb = (int) ceil(((int) config('whistleblowing.uploads.max_bytes', 26214400)) / 1024);
        $maxFiles = (int) config('whistleblowing.uploads.max_per_case', 10);

        return [
            'reporter_mode' => ['required', Rule::in(array_column(ReporterMode::cases(), 'value'))],
            'category' => ['required', Rule::in(array_column(CaseCategory::cases(), 'value'))],
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:20000'],
            'occurred_from' => ['nullable', 'date'],
            'occurred_to' => ['nullable', 'date', 'after_or_equal:occurred_from'],
            'contact' => ['nullable', 'string', 'max:500'],
            'consent' => ['accepted'],
            'attachments' => ['nullable', 'array', 'max:' . $maxFiles],
            'attachments.*' => ['file', File::types($allowedMimes)->max($maxKb)],
        ];
    }
}
