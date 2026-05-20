<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Licensing;

final class LicenseResult {
    public function __construct(
        public readonly LicenseStatus $status,
        public readonly ?LicensePayload $payload = null,
        public readonly ?string $message = null,
    ) {
    }

    public static function ok(LicenseStatus $status, LicensePayload $payload, ?string $message = null): self {
        return new self($status, $payload, $message);
    }

    public static function fail(LicenseStatus $status, ?string $message = null): self {
        return new self($status, null, $message);
    }

    public function isUsable(): bool {
        return $this->status->isUsable();
    }
}
