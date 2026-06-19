<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Article;

/**
 * Lebenszyklus von Artikeln und Varianten (Feature 048, MVP-060).
 * Referenzierte Artikel werden STILLGELEGT (retired), nicht gelöscht; nur
 * referenzlose Entwürfe dürfen entfernt werden.
 */
enum ArticleStatus: string {
    case Draft = 'draft';
    case Active = 'active';
    case Retired = 'retired';

    public function label(): string {
        return match ($this) {
            self::Draft => __('article.status.draft'),
            self::Active => __('article.status.active'),
            self::Retired => __('article.status.retired'),
        };
    }

    public function isUsable(): bool {
        return $this === self::Active;
    }
}
