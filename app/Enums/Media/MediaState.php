<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MediaState.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Media;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Verarbeitungsstand eines Videos (Feature 150).
 *
 * Sichtbar, weil ein Kurs, dessen Video noch rechnet, das sagen muss —
 * statt eine leere Fläche zu zeigen und den Eindruck zu erwecken, der
 * Inhalt fehle.
 */
enum MediaState: string implements HasLabel {
    use HasOptions;

    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string {
        return (string) __('enums.media.state.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Pending => 'ghost',
            self::Processing => 'info',
            self::Ready => 'success',
            self::Failed => 'error',
        };
    }

    /** Ist das Video abspielbar? */
    public function isPlayable(): bool {
        return $this === self::Ready;
    }
}
