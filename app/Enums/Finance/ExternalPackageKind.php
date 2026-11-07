<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalPackageKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art einer Versandfassung (Feature 196): Erstversand oder Nachsendung später
 * erstellter Nachweise mit Bezug auf die bereits zugestellte Rechnung.
 */
enum ExternalPackageKind: string implements HasLabel {
    use HasOptions;

    case Initial = 'initial';
    case Resend = 'resend';

    public function label(): string {
        return (string) __('enums.finance.external-package-kind.' . $this->value);
    }
}
