<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RenderProfileVersionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\DocumentDesign;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Stand einer Renderprofil-Version (MVP-300): Entwurf, eingefroren aktiv, abgelöst. */
enum RenderProfileVersionStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Draft = 'draft';
    case Active = 'active';
    case Superseded = 'superseded';

    public function label(): string {
        return match ($this) {
            self::Draft => __('enums.document_design.render_profile_version_status.draft'),
            self::Active => __('enums.document_design.render_profile_version_status.active'),
            self::Superseded => __('enums.document_design.render_profile_version_status.superseded'),
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::Active],
            self::Active => [self::Superseded],
            self::Superseded => [],
        };
    }
}
