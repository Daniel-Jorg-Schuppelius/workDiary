<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RenderProfileStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\DocumentDesign;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum RenderProfileStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string {
        return match ($this) {
            self::Draft => __('enums.document_design.render_profile_status.draft'),
            self::Active => __('enums.document_design.render_profile_status.active'),
            self::Archived => __('enums.document_design.render_profile_status.archived'),
        };
    }

    public function tone(): string {
        return match ($this) {
            self::Draft => 'muted',
            self::Active => 'success',
            self::Archived => 'muted',
        };
    }
}
