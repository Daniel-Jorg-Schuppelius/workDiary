<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleDuplicateMatchProfile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration\Profiles;

use App\Models\{Article, Organization};
use App\Services\Integration\Match\FuzzyField;
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * Match-Profil für die **Dubletten-Suche unter lokalen Artikeln** (Audit
 * 2026-08, W2.9) — bewusst getrennt vom {@see ArticleMatchProfile}, der dem
 * Import-Matching dient (externe Kennung → lokaler Artikel).
 *
 * Grund für die Trennung: `articles` ist org-weit unique über `number` UND
 * über `gtin`. Die exakten Strategien des Import-Profils können unter lokalen
 * Artikeln deshalb **strukturell nie** einen Treffer liefern — echte
 * Dubletten haben zwangsläufig abweichende oder leere Kennungen. Tragfähig
 * ist hier nur die Namensähnlichkeit; die Sortenreinheit sichert der
 * Skip-Filter des Finders (gleiche Basiseinheit, gleiche Steuerklasse).
 */
class ArticleDuplicateMatchProfile extends ArticleMatchProfile {
    /** Etwas strenger als beim Kunden: Artikelnamen sind kürzer und ähnlicher. */
    public const FUZZY_THRESHOLD = 0.90;

    public function targetType(): string {
        return Article::class;
    }

    public function strategies(): array {
        return [
            new FuzzyField(['name'], self::FUZZY_THRESHOLD, 'name'),
        ];
    }

    protected function newCandidateQuery(): Builder {
        return Article::query();
    }

    /** Dieses Profil sucht nur — angelegt wird über das Import-Profil. */
    public function create(Organization $organization, array $mapped): Model {
        throw new \LogicException('Das Dubletten-Profil legt keine Artikel an.');
    }
}
