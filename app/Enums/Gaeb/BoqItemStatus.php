<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqItemStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Gaeb;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Lebenszyklus einer LV-Position (Feature 049, MVP-082).
 *
 * Eine Position mit Ausführungs-/Abrechnungsbezug (ab InProgress) darf bei
 * einem Reimport nicht still überschrieben werden — siehe BillOfQuantityImporter.
 */
enum BoqItemStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Imported = 'imported';
    case Quoted = 'quoted';
    case Ordered = 'ordered';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Replaced = 'replaced';
    case Cancelled = 'cancelled';

    public function label(): string {
        return __('gaeb.item.status.' . $this->value);
    }

    /**
     * Hat die Position bereits Ausführungs-/Abrechnungsbezug und ist damit
     * gegen stilles Überschreiben durch einen Reimport geschützt?
     */
    public function hasExecutionReference(): bool {
        return match ($this) {
            self::Ordered, self::InProgress, self::Completed => true,
            default => false,
        };
    }
}
