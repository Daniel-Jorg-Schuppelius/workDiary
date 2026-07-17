<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProblemReportSeverity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Support;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Vom Melder eingeschätzter Schweregrad (Feature 041, MVP-053). */
enum ProblemReportSeverity: string implements HasLabel {
    use HasOptions;

    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Blocking = 'blocking';

    public function label(): string {
        return __('problemreport.severity.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Low => 'neutral',
            self::Normal => 'info',
            self::High => 'warning',
            self::Blocking => 'error',
        };
    }
}
