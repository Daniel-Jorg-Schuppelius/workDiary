<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveSupplierCatalogSourceRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Rules\ExistsInCurrentOrganization;
use App\Support\UrlSafety;
use Illuminate\Validation\Rule;

/**
 * Validierung für eine Lieferanten-Katalogquelle (Feature 050, MVP-091).
 * Lieferant kommt als Sqid und wird mandantensicher geprüft; die Berechtigung
 * trägt der Controller. Aktiv ist zunächst nur das CSV-Format/Upload.
 */
class SaveSupplierCatalogSourceRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'supplier' => \App\Models\Supplier::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'supplier' => ['required', 'integer', new ExistsInCurrentOrganization('suppliers')],
            'name' => ['required', 'string', 'max:191'],
            'format' => ['required', Rule::in(['csv', 'datanorm', 'bmecat'])],
            'source_type' => ['nullable', Rule::in(['upload', 'http', 'ftp', 'sftp'])],
            'delimiter' => ['required', 'string', 'min:1', 'max:4'],
            'decimal_separator' => ['required', Rule::in([',', '.'])],
            'encoding' => ['required', 'string', 'max:32'],
            'has_header' => ['nullable', 'boolean'],
            // SSRF-Konfigurationszeit-Guard: keine internen/privaten Ziele als
            // Katalogquelle (verbindliche DNS-sichere Prüfung erneut zur
            // Laufzeit im CatalogFetchService).
            'remote_url' => ['nullable', 'string', 'url', 'max:1024', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && trim($value) !== '' && ! UrlSafety::isAcceptableExternalHttpUrl($value)) {
                    $fail((string) __('procurement.catalog.error.host_not_allowed'));
                }
            }],
            'remote_host' => ['nullable', 'string', 'max:191', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && trim($value) !== '' && ! UrlSafety::isAcceptableExternalHost($value)) {
                    $fail((string) __('procurement.catalog.error.host_not_allowed'));
                }
            }],
            'remote_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'remote_path' => ['nullable', 'string', 'max:1024'],
            'remote_username' => ['nullable', 'string', 'max:191'],
            'remote_password' => ['nullable', 'string', 'max:512'],
            'fetch_interval_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
