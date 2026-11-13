<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePageKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Knowledge;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Art einer Wissensseite (Feature 191): Textseite oder Schritt-für-Schritt-Anleitung aus Bildschirmfotos (MVP-1668). */
enum KnowledgePageKind: string implements HasLabel {
    use HasOptions;

    case Page = 'page';
    case Guide = 'guide';

    public function label(): string {
        return (string) __('enums.knowledge.page_kind.' . $this->value);
    }

    public function icon(): string {
        return match ($this) {
            self::Page => 'article',
            self::Guide => 'photo_library',
        };
    }
}
