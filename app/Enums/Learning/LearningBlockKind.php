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
    // MVP-806: die acht Typen aus der Spezifikation, die bis dahin fehlten.
    case Gallery = 'gallery';
    case Audio = 'audio';
    case Code = 'code';
    case Accordion = 'accordion';
    case Table = 'table';
    case Procedure = 'procedure';
    case Question = 'question';
    case Divider = 'divider';

    public function label(): string {
        return (string) __('enums.learning.block-kind.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Heading, self::Text, self::Divider => 'ghost',
            self::Callout => 'warning',
            self::Checklist, self::Accordion, self::Table, self::Question => 'info',
            self::Image, self::File, self::Video, self::Gallery, self::Audio => 'success',
            self::Embed => 'error',
            self::Knowledge, self::Code, self::Procedure => 'neutral',
        };
    }

    /** Blocktypen mit externer Quelle brauchen die Host-Allowlist. */
    public function needsHostAllowlist(): bool {
        return $this === self::Embed || $this === self::Video;
    }

    /**
     * MIME-Hauptart, die ein Upload für diesen Block haben muss — sonst läge
     * etwa ein PDF in einem Bildblock und bliebe im Kurs eine leere Fläche.
     */
    public function mediaType(): ?string {
        return match ($this) {
            self::Image, self::Gallery => 'image',
            self::Audio => 'audio',
            self::Video => 'video',
            default => null,
        };
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
            self::Gallery => 'photo_library',
            self::Audio => 'headphones',
            self::Code => 'code',
            self::Accordion => 'unfold_more',
            self::Table => 'table',
            self::Procedure => 'fact_check',
            self::Question => 'quiz',
            self::Divider => 'horizontal_rule',
        };
    }
}
