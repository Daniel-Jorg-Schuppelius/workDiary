<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoginPolicyReason.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Auth;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Grund, aus dem die Anmelderichtlinie eine Anmeldung oder Anfrage abweist
 * (oder im Modus „nur protokollieren“ abgewiesen hätte). Der Wert steht im
 * Sicherheitsereignis — nur ergänzen, nie umbenennen.
 */
enum LoginPolicyReason: string implements HasLabel {
    use HasOptions;

    /** Notaus der Organisation (MVP-1657). */
    case Lockdown = 'lockdown';
    /** Außerhalb des Anmeldezeitfensters (MVP-1654). */
    case Hours = 'hours';
    /** Außerhalb der Büronetze ohne Recht `auth.remote` (MVP-1655). */
    case Network = 'network';
    /** Außerhalb der Büronetze von einem nicht freigegebenen Gerät (MVP-1655). */
    case Device = 'device';
    /** Außerhalb der Büronetze von einem Gerät, dessen Freigabe abgelehnt ist (MVP-1655). */
    case DeviceRejected = 'device_rejected';
    /** Rolle mit Passkey-Pflicht meldet sich ohne Passkey an (MVP-1656). */
    case Passkey = 'passkey';

    public function label(): string {
        return (string) __('enums.auth.login_policy_reason.' . $this->value);
    }
}
