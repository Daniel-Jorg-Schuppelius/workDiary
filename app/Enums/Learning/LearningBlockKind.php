<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningBlockKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Learning;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Inhaltsblock einer Lerneinheit (Feature 149, MVP-736).
 *
 * Bewusst klein gehalten: jeder Typ hat ein festes, validiertes Feldbild.
 * Freies HTML gibt es nicht — Text bleibt Text (SafeHtml-Grenze), und
 * Einbettungen laufen über die `frame-src`-Allowlist der Organisation.
 */
enum LearningBlockKind: string implements HasLabel {
    use HasOptions;

    case Heading = 'heading';
    case Text = 'text';
    case Callout = 'callout';
    case Checklist = 'checklist';
    case Image = 'image';
    case File = 'file';
    case Video = 'video';
    case Embed = 'embed';
    case Knowledge = 'knowledge';

    public function label(): string {
        return (string) __('enums.learning.block-kind.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Heading, self::Text => 'ghost',
            self::Callout => 'warning',
            self::Checklist => 'info',
            self::Image, self::File, self::Video => 'success',
            self::Embed => 'error',
            self::Knowledge => 'neutral',
        };
    }

    /** Blocktypen mit externer Quelle brauchen die Host-Allowlist. */
    public function needsHostAllowlist(): bool {
        return $this === self::Embed || $this === self::Video;
    }

    /** Material-Symbol für die Editor-Liste. */
    public function icon(): string {
        return match ($this) {
            self::Heading => 'title',
            self::Text => 'notes',
            self::Callout => 'campaign',
            self::Checklist => 'checklist',
            self::Image => 'image',
            self::File => 'attach_file',
            self::Video => 'movie',
            self::Embed => 'frame_source',
            self::Knowledge => 'menu_book',
        };
    }
}
