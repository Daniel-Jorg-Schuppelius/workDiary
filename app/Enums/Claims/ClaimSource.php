<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Claims;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Eingangskanal einer Reklamation (MVP-248). */
enum ClaimSource: string implements HasLabel {
    use HasOptions;

    case Portal = 'portal';
    case Email = 'email';
    case Phone = 'phone';
    case Helpdesk = 'helpdesk';
    case Order = 'order';
    case Protocol = 'protocol';
    case Asset = 'asset';
    case Invoice = 'invoice';
    case Api = 'api';
    case Internal = 'internal';
    case Manual = 'manual';

    public function label(): string {
        return match ($this) {
            self::Portal => (string) __('enums.claims.claim_source.portal'),
            self::Email => (string) __('enums.claims.claim_source.email'),
            self::Phone => (string) __('enums.claims.claim_source.phone'),
            self::Helpdesk => (string) __('enums.claims.claim_source.helpdesk'),
            self::Order => (string) __('enums.claims.claim_source.order'),
            self::Protocol => (string) __('enums.claims.claim_source.protocol'),
            self::Asset => (string) __('enums.claims.claim_source.asset'),
            self::Invoice => (string) __('enums.claims.claim_source.invoice'),
            self::Api => (string) __('enums.claims.claim_source.api'),
            self::Internal => (string) __('enums.claims.claim_source.internal'),
            self::Manual => (string) __('enums.claims.claim_source.manual'),
        };
    }
}
