<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenMasterdataConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement\OpenMasterdata;

use App\Models\Supplier\SupplierCatalogSource;

/**
 * Zugang einer Katalogquelle zum Open-Masterdata-Webservice (MVP-1072). Die
 * Werte liegen verschlüsselt in `supplier_catalog_sources.omd_config`; Token-
 * und Produkt-Adresse vergibt der Großhändler, sie stehen nicht im Standard.
 */
final readonly class OpenMasterdataConfig {
    public const GRANT_PASSWORD = 'password';

    public const GRANT_CLIENT_CREDENTIALS = 'client_credentials';

    /** Datenpakete in einem Parameter (`a|b`) oder je Paket ein Parameter. */
    public const PACKAGES_PIPE = 'pipe';

    public const PACKAGES_EXPLODED = 'exploded';

    public const DEFAULT_SCOPE = 'openMasterdata';

    public function __construct(
        public string $tokenUrl,
        public string $baseUrl,
        public string $grantType = self::GRANT_PASSWORD,
        public string $clientId = '',
        public string $clientSecret = '',
        public string $username = '',
        public string $password = '',
        public string $customerNumber = '',
        public bool $customerNumberInLogin = false,
        public string $scope = self::DEFAULT_SCOPE,
        public string $packageMode = self::PACKAGES_PIPE,
        public string $customerId = '',
    ) {}

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values): self {
        $string = static fn (string $key, string $default = ''): string => trim((string) ($values[$key] ?? $default));

        return new self(
            tokenUrl: $string('token_url'),
            baseUrl: rtrim($string('base_url'), '/'),
            grantType: $string('grant_type') === self::GRANT_CLIENT_CREDENTIALS ? self::GRANT_CLIENT_CREDENTIALS : self::GRANT_PASSWORD,
            clientId: $string('client_id'),
            clientSecret: $string('client_secret'),
            username: $string('username'),
            password: $string('password'),
            customerNumber: $string('customer_number'),
            customerNumberInLogin: filter_var($values['customer_number_in_login'] ?? false, FILTER_VALIDATE_BOOL),
            scope: $string('scope', self::DEFAULT_SCOPE),
            packageMode: $string('package_mode') === self::PACKAGES_EXPLODED ? self::PACKAGES_EXPLODED : self::PACKAGES_PIPE,
            customerId: $string('customer_id'),
        );
    }

    public static function fromSource(SupplierCatalogSource $source): ?self {
        $config = $source->omd_config;
        if (! is_array($config)) {
            return null;
        }
        $instance = self::fromArray($config);

        return $instance->isComplete() ? $instance : null;
    }

    public function isComplete(): bool {
        return $this->tokenUrl !== '' && $this->baseUrl !== '' && $this->clientId !== '' && $this->loginName() !== '';
    }

    /**
     * Anmeldename laut Konzept 5.1: Benutzername, Kundennummer oder beides —
     * dann durch einen Tabulator getrennt.
     */
    public function loginName(): string {
        if ($this->username !== '' && $this->customerNumber !== '' && $this->customerNumberInLogin) {
            return $this->username . "\t" . $this->customerNumber;
        }
        if ($this->username === '' && $this->customerNumber !== '') {
            return $this->customerNumber;
        }

        return $this->username;
    }

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'token_url' => $this->tokenUrl,
            'base_url' => $this->baseUrl,
            'grant_type' => $this->grantType,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'username' => $this->username,
            'password' => $this->password,
            'customer_number' => $this->customerNumber,
            'customer_number_in_login' => $this->customerNumberInLogin,
            'scope' => $this->scope,
            'package_mode' => $this->packageMode,
            'customer_id' => $this->customerId,
        ];
    }
}
