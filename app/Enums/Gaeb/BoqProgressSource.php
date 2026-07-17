<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqProgressSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Gaeb;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Herkunft einer Aufmaß-/Fortschrittsmeldung zu einer LV-Position
 * (Feature 049, MVP-083).
 *
 * - Manual:      manuell erfasst
 * - Measurement: Aufmaß (Mengenermittlung vor Ort)
 * - Protocol:    aus einem Protokoll/Bautagesbericht abgeleitet
 * - Material:    aus dokumentiertem Materialverbrauch abgeleitet
 */
enum BoqProgressSource: string implements HasLabel {
    use HasOptions;

    case Manual = 'manual';
    case Measurement = 'measurement';
    case Protocol = 'protocol';
    case Material = 'material';

    public function label(): string {
        return __('gaeb.progress.source.' . $this->value);
    }
}
