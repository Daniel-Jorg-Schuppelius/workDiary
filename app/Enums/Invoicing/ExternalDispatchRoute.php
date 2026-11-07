<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalDispatchRoute.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Invoicing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Wer stellt eine extern ausgestellte Rechnung zu (Feature 196)? Rechnungshoheit
 * und Versandzuständigkeit sind getrennt: das Fakturasystem führt die Rechnung,
 * WorkDiary kann sie mit Nachweisen zustellen.
 */
enum ExternalDispatchRoute: string implements HasLabel {
    use HasOptions;

    case Provider = 'provider';
    case WorkDiary = 'workdiary';

    public function label(): string {
        return (string) __('enums.invoicing.external_dispatch_route.' . $this->value);
    }
}
