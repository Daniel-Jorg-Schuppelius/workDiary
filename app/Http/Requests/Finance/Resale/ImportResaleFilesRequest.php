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
 * generischen Liste ist nie „DomainReselling". „Keine Datei" meldet der
 * Controller als Flash (kein Feldfehler).
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
            'telekom' => ['nullable', 'file', 'max:' . self::MAX_FILE_KB, 'extensions:csv,txt'],
            'qualityhosting' => ['nullable', 'file', 'max:' . self::MAX_FILE_KB, 'extensions:xlsx,xlsm'],
            'pricelist' => ['nullable', 'file', 'max:' . self::MAX_FILE_KB, 'extensions:xlsx,xlsm'],
            'generic' => ['nullable', 'file', 'max:' . self::MAX_FILE_KB, 'extensions:csv,txt,xlsx,xlsm'],
            'generic_provider' => ['nullable', 'string', Rule::in($providers)],
        ];
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
