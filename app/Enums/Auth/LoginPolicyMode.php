<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoginPolicyMode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Auth;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Wirkung einer Regel der Anmelderichtlinie (Feature 205, MVP-1654/1655):
 * aus, nur protokollieren (zeigt, wen die Regel ausgesperrt hätte) oder
 * durchsetzen. „Nur protokollieren“ kommt vor dem Durchsetzen, damit falsch
 * gepflegte Netze oder Zeiten kein Büro aussperren.
 */
enum LoginPolicyMode: string implements HasLabel {
    use HasOptions;

    case Off = 'off';
    case Log = 'log';
    case Enforce = 'enforce';

    public function label(): string {
        return (string) __('enums.auth.login_policy_mode.' . $this->value);
    }

    /** Wird die Regel überhaupt ausgewertet? */
    public function isActive(): bool {
        return $this !== self::Off;
    }

    public function enforces(): bool {
        return $this === self::Enforce;
    }
}
