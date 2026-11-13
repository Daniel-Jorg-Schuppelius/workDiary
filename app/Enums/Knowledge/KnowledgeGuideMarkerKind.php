<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeGuideMarkerKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Knowledge;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Markierung auf dem Bild eines Anleitungsschritts (Feature 191, MVP-1668).
 * Markierungen liegen als Überlagerung über dem Bild und bleiben änderbar;
 * Schwärzen ist keine Markierung, sondern verändert das Bild selbst.
 */
enum KnowledgeGuideMarkerKind: string implements HasLabel {
    use HasOptions;

    case Arrow = 'arrow';
    case Number = 'number';
    case Frame = 'frame';
    case Text = 'text';

    public function label(): string {
        return (string) __('enums.knowledge.marker_kind.' . $this->value);
    }

    /** Rechteck (Rahmen) statt Punkt bzw. Strecke. */
    public function isArea(): bool {
        return $this === self::Frame;
    }
}
