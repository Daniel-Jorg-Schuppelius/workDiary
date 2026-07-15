<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveCloudRouteRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\CloudIntake;

use App\Enums\CloudIntake\CloudIntakeRouteTarget;
use App\Enums\Document\DocumentType;
use App\Http\Requests\BaseFormRequest;
use App\Services\CloudIntake\RoutePatternValidator;
use Illuminate\Validation\{Rule, Validator};

/**
 * Ordnerregel anlegen/bearbeiten (Feature 080, MVP-358): Pfadmuster-Grammatik
 * wird über den {@see RoutePatternValidator} geprüft (unbekannte Variablen,
 * Dubletten, `***` blockieren das Speichern). Berechtigung trägt der
 * Controller (`cloudIntake.route.manage`).
 */
class SaveCloudRouteRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'path_pattern' => ['required', 'string', 'max:512'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'target' => ['required', Rule::enum(CloudIntakeRouteTarget::class)],
            'document_type' => ['nullable', 'required_if:target,document', Rule::enum(DocumentType::class)],
            'allowed_extensions' => ['nullable', 'string', 'max:300'],
            'max_file_size' => ['nullable', 'integer', 'min:1'],
            'auto_version' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $validator): void {
            $errors = app(RoutePatternValidator::class)->validatePattern((string) $this->input('path_pattern', ''));
            foreach ($errors as $error) {
                $validator->errors()->add('path_pattern', $error);
            }
        });
    }

    /**
     * Normalisierte Attribute fürs Modell (Endungs-Liste aus Freitext).
     *
     * @return array<string, mixed>
     */
    public function routeAttributes(): array {
        $extensions = array_values(array_filter(array_map(
            static fn (string $e): string => strtolower(trim($e, ". \t")),
            preg_split('/[\s,;]+/', (string) $this->validated('allowed_extensions', '')) ?: [],
        ), static fn (string $e): bool => $e !== ''));

        return [
            'path_pattern' => trim((string) $this->validated('path_pattern'), "/ \t"),
            'priority' => (int) $this->validated('priority'),
            'target' => $this->validated('target'),
            'document_type' => $this->validated('document_type'),
            'allowed_extensions' => $extensions === [] ? null : $extensions,
            'max_file_size' => $this->validated('max_file_size'),
            'auto_version' => (bool) $this->validated('auto_version', false),
            'active' => (bool) $this->validated('active', true),
        ];
    }
}
