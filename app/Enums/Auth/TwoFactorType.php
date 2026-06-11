<?php
/*
 * Created on   : Tue Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TwoFactorType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Auth;

/** Zweiter-Faktor-Methode (RFC 6238 TOTP, E-Mail-Einmalcode, FIDO2/WebAuthn). */
enum TwoFactorType: string {
    case Totp = 'totp';
    case Email = 'email';
    case Webauthn = 'webauthn';

    public function label(): string {
        return match ($this) {
            self::Totp => __('Authenticator-App'),
            self::Email => __('E-Mail-Code'),
            self::Webauthn => __('Sicherheitsschlüssel / Passkey'),
        };
    }

    public function icon(): string {
        return match ($this) {
            self::Totp => 'smartphone',
            self::Email => 'mail',
            self::Webauthn => 'security_key',
        };
    }
}
