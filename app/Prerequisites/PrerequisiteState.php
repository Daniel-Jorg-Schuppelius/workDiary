<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrerequisiteState.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Prerequisites;

/**
 * Erklärbarer Zustand einer konfigurationsabhängigen Aktion
 * (Feature 067, MVP-181). Ersetzt KEINE Policies und keine
 * serverseitige Validierung — es geht um verständliche
 * Blocked-/Empty-States statt stiller No-ops.
 */
enum PrerequisiteState: string {
    case Ready = 'ready';
    case MissingRequired = 'missing_required';
    case MissingOptional = 'missing_optional';
    case NotLicensed = 'not_licensed';
    case NotAllowed = 'not_allowed';
    case ProviderUnsupported = 'provider_unsupported';

    public function blocks(): bool {
        return in_array($this, [self::MissingRequired, self::NotLicensed, self::NotAllowed, self::ProviderUnsupported], true);
    }

    public function tone(): string {
        return match ($this) {
            self::Ready => 'success',
            self::MissingOptional => 'info',
            self::MissingRequired => 'warning',
            self::NotLicensed, self::NotAllowed, self::ProviderUnsupported => 'error',
        };
    }
}
