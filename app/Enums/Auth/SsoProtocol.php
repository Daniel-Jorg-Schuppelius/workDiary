<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SsoProtocol.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Auth;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** SSO-Protokoll einer Organisations-Anbindung (Feature 057, MVP-120/121). */
enum SsoProtocol: string implements HasLabel {
    use HasOptions;

    case Oidc = 'oidc';
    case Saml = 'saml';

    public function label(): string {
        return match ($this) {
            self::Oidc => __('sso.protocol.oidc'),
            self::Saml => __('sso.protocol.saml'),
        };
    }
}
