<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeliveryFacturationStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Manufacturing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Faktura-Status einer Auslieferung (Feature 047, MVP-074). BEWUSST getrennt
 * vom Lagerstatus: eine fehlgeschlagene Fakturaübertragung darf die bereits
 * erfolgte Lagerbuchung nicht verbergen.
 */
enum DeliveryFacturationStatus: string implements HasLabel {
    use HasOptions;

    case Pending = 'pending';
    case HandedOver = 'handed_over';
    case Invoiced = 'invoiced';
    case Failed = 'failed';
    case NotRequired = 'not_required';

    public function label(): string {
        return __('manufacturing.facturation_status.' . $this->value);
    }
}
