<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicensePayload.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Licensing;

use Carbon\CarbonImmutable;

final class LicensePayload {
    public function __construct(
        public readonly string $licensee,
        public readonly ?string $email,
        public readonly CarbonImmutable $issuedAt,
        public readonly ?CarbonImmutable $expiresAt,
        public readonly ?string $domain,
        public readonly ?int $maxUsers,
        /** @var array<int,string> */
        public readonly array $features,
        public readonly string $licenseId,
    ) {}

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self {
        return new self(
            licensee: (string) ($data['licensee'] ?? ''),
            email: isset($data['email']) ? (string) $data['email'] : null,
            issuedAt: CarbonImmutable::parse($data['issued_at'] ?? 'now'),
            expiresAt: isset($data['expires_at']) ? CarbonImmutable::parse($data['expires_at']) : null,
            domain: isset($data['domain']) ? (string) $data['domain'] : null,
            maxUsers: isset($data['max_users']) ? (int) $data['max_users'] : null,
            features: array_values(array_map('strval', (array) ($data['features'] ?? []))),
            licenseId: (string) ($data['license_id'] ?? ''),
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array {
        return [
            'license_id' => $this->licenseId,
            'licensee' => $this->licensee,
            'email' => $this->email,
            'issued_at' => $this->issuedAt->toIso8601String(),
            'expires_at' => $this->expiresAt?->toIso8601String(),
            'domain' => $this->domain,
            'max_users' => $this->maxUsers,
            'features' => $this->features,
        ];
    }
}
