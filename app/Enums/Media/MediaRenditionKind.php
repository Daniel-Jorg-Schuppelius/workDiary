<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MediaRenditionKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Media;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Art einer abgeleiteten Mediendatei (Feature 150). */
enum MediaRenditionKind: string implements HasLabel {
    use HasOptions;

    case Video = 'video';
    case Poster = 'poster';
    case Subtitle = 'subtitle';

    public function label(): string {
        return (string) __('enums.media.rendition-kind.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Video => 'info',
            self::Poster => 'ghost',
            self::Subtitle => 'success',
        };
    }
}
