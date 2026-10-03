<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveSupplierCatalogSourceRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\Supplier;

use App\Enums\Procurement\PunchoutProtocol;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Procurement\OpenMasterdata\OpenMasterdataConfig;
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
        'supplier' => \App\Models\Supplier\Supplier::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        $externalUrl = static function (string $attribute, mixed $value, \Closure $fail): void {
            if (is_string($value) && trim($value) !== '' && ! UrlSafety::isAcceptableExternalHttpUrl($value)) {
                $fail((string) __('procurement.catalog.error.host_not_allowed'));
            }
        };

        return [
            'supplier' => ['required', 'integer', new ExistsInCurrentOrganization('suppliers')],
            'name' => ['required', 'string', 'max:191'],
            'format' => ['required', Rule::in(['csv', 'xlsx', 'datanorm', 'bmecat', 'omd'])],
            'source_type' => ['nullable', Rule::in(['upload', 'http', 'ftp', 'sftp'])],
            // Dateieinstellungen gelten nicht für den Webservice Open Masterdata (MVP-1072).
            'delimiter' => ['required_unless:format,omd', 'nullable', 'string', 'min:1', 'max:4'],
            'sheet_name' => ['nullable', 'string', 'max:64'],
            'decimal_separator' => ['required_unless:format,omd', 'nullable', Rule::in([',', '.'])],
            'encoding' => ['required_unless:format,omd', 'nullable', 'string', 'max:32'],
            'expected_customer_no' => ['nullable', 'string', 'max:32'],
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
            // Pflicht beim SFTP-Abruf: ohne Host-Key meldet sich die
            // Anwendung bei jedem Server an, der antwortet (Sicherheitsscan
            // 2026-08-23, S-22).
            'remote_host_fingerprint' => ['nullable', 'string', 'max:190'],
            'fetch_interval_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            // OCI-Punchout-Absprung (MVP-096): Browser-Redirect, aber derselbe
            // Guard gegen interne Ziele wie beim Remote-Abruf.
            'punchout_url' => ['nullable', 'string', 'url', 'max:1024', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && trim($value) !== '' && ! UrlSafety::isAcceptableExternalHttpUrl($value)) {
                    $fail((string) __('procurement.catalog.error.host_not_allowed'));
                }
            }],
            'punchout_username' => ['nullable', 'string', 'max:191'],
            'punchout_password' => ['nullable', 'string', 'max:512'],
            'punchout_protocol' => ['nullable', Rule::enum(PunchoutProtocol::class)],
            // IDS-Connect meldet sich mit der Kundennummer beim Großhändler an (MVP-1071).
            'punchout_customer_number' => [
                Rule::requiredIf(fn (): bool => $this->input('punchout_protocol') === PunchoutProtocol::Ids->value && trim((string) $this->input('punchout_url')) !== ''),
                'nullable', 'string', 'max:50',
            ],
            // Open Masterdata (MVP-1072): Token- und Produktadresse vergibt der Großhändler.
            'omd' => ['nullable', 'array'],
            'omd.token_url' => ['required_if:format,omd', 'nullable', 'string', 'url:https', 'max:1024', $externalUrl],
            'omd.base_url' => ['required_if:format,omd', 'nullable', 'string', 'url:https', 'max:1024', $externalUrl],
            'omd.grant_type' => ['nullable', Rule::in([OpenMasterdataConfig::GRANT_PASSWORD, OpenMasterdataConfig::GRANT_CLIENT_CREDENTIALS])],
            'omd.client_id' => ['required_if:format,omd', 'nullable', 'string', 'max:191'],
            'omd.client_secret' => ['nullable', 'string', 'max:512'],
            'omd.username' => [
                Rule::requiredIf(fn (): bool => $this->input('format') === 'omd' && trim((string) $this->input('omd.customer_number')) === ''),
                'nullable', 'string', 'max:191',
            ],
            'omd.password' => ['nullable', 'string', 'max:512'],
            'omd.customer_number' => ['nullable', 'string', 'max:50'],
            'omd.customer_number_in_login' => ['nullable', 'boolean'],
            'omd.scope' => ['nullable', 'string', 'max:191'],
            'omd.package_mode' => ['nullable', Rule::in([OpenMasterdataConfig::PACKAGES_PIPE, OpenMasterdataConfig::PACKAGES_EXPLODED])],
            'omd.customer_id' => ['nullable', 'string', 'max:50'],
        ];
    }
}
