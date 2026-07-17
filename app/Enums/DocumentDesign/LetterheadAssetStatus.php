<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LetterheadAssetStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\DocumentDesign;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum LetterheadAssetStatus: string implements HasLabel {
    use HasOptions;

    case ReviewRequired = 'review_required';
    case Ready = 'ready';
    case Archived = 'archived';

    public function label(): string {
        return match ($this) {
            self::ReviewRequired => __('Prüfung erforderlich'),
            self::Ready => __('Einsatzbereit'),
            self::Archived => __('Archiviert'),
        };
    }

    public function tone(): string {
        return match ($this) {
            self::ReviewRequired => 'warning',
            self::Ready => 'success',
            self::Archived => 'muted',
        };
    }
}
