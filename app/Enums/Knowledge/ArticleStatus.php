<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Knowledge;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Status eines Wissensartikels (Feature 011). Entwürfe sind nur für
 * Erfasser + Redaktion (knowledge.publish) sichtbar/bearbeitbar,
 * veröffentlichte Artikel erscheinen in Suche und Vorschlägen.
 */
enum ArticleStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string {
        return (string) __('enums.knowledge.status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Draft => 'ghost',
            self::Published => 'success',
            self::Archived => 'ghost',
        };
    }
}
