<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqChangeOrderInitiator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Gaeb;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Wer den Nachtrag ausgelöst hat (GAEB `COInit`). Für die spätere Bewertung
 * relevant: ein vom Auftraggeber angeforderter Nachtrag ist etwas anderes als
 * einer, den der Auftragnehmer von sich aus anmeldet.
 */
enum BoqChangeOrderInitiator: string implements HasLabel {
    use HasOptions;

    case Owner = 'Owner';
    case Contractor = 'Contractor';

    public function label(): string {
        return __('gaeb.change_order.initiator.' . $this->value);
    }
}
