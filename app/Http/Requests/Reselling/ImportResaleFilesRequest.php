<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportResaleFilesRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Finance\Resale;

use App\Enums\Reselling\SubscriptionProvider;
use App\Http\Requests\BaseFormRequest;
use App\Models\Reselling\ResaleImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Anbieter-Exporte hochladen (Feature 152, Review C1/B7): der Anbieter der
 * generischen Liste ist nie „DomainReselling". Mindestens eine Datei ist
 * Pflicht (`required_without_all` je Dateifeld) — „keine Datei" ist ein
 * Feldfehler und landet als 422 im Dialog (Review 2026-09-11).
 */
class ImportResaleFilesRequest extends BaseFormRequest {
    private const MAX_FILE_KB = 10240;

    /** Formularfeld je Import-Art. */
    public const FIELDS = [
        ResaleImport::KIND_PURCHASES => 'telekom',
        ResaleImport::KIND_CONTRACTS => 'qualityhosting',
        ResaleImport::KIND_PRICELIST => 'pricelist',
        ResaleImport::KIND_GENERIC => 'generic',
    ];

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array {
        $providers = array_values(array_filter(
            array_map(static fn(SubscriptionProvider $p): string => $p->value, SubscriptionProvider::cases()),
            static fn(string $value): bool => $value !== SubscriptionProvider::DomainReselling->value,
        ));

        return [
            'telekom' => [self::atLeastOne('telekom'), 'nullable', 'file', 'max:' . self::MAX_FILE_KB, 'extensions:csv,txt'],
            'qualityhosting' => [self::atLeastOne('qualityhosting'), 'nullable', 'file', 'max:' . self::MAX_FILE_KB, 'extensions:xlsx,xlsm'],
            'pricelist' => [self::atLeastOne('pricelist'), 'nullable', 'file', 'max:' . self::MAX_FILE_KB, 'extensions:xlsx,xlsm'],
            'generic' => [self::atLeastOne('generic'), 'nullable', 'file', 'max:' . self::MAX_FILE_KB, 'extensions:csv,txt,xlsx,xlsm'],
            'generic_provider' => ['nullable', 'string', Rule::in($providers)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array {
        $messages = [];
        foreach (self::FIELDS as $field) {
            $messages[$field . '.required_without_all'] = (string) __('resale.import.flash.no_files');
        }

        return $messages;
    }

    /** `required_without_all` über die anderen Dateifelder. */
    private static function atLeastOne(string $field): string {
        return 'required_without_all:' . implode(',', array_values(array_filter(self::FIELDS, static fn(string $other): bool => $other !== $field)));
    }

    /**
     * Hochgeladene Dateien je Import-Art.
     *
     * @return array<string, UploadedFile>
     */
    public function uploads(): array {
        $files = [];
        foreach (self::FIELDS as $kind => $field) {
            $upload = $this->file($field);
            if ($upload instanceof UploadedFile) {
                $files[$kind] = $upload;
            }
        }

        return $files;
    }

    public function genericProvider(): SubscriptionProvider {
        return SubscriptionProvider::tryFrom((string) $this->validated('generic_provider', '')) ?? SubscriptionProvider::Other;
    }
}
