<?php
/*
 * Created on   : Tue Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleMatchProfile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration\Profiles;

use App\Models\{Article, Organization};
use App\Services\Integration\Match\{AbstractMatchProfile, ExactField, MatchStrategy};
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * Abgleich-Profil für Artikel: ausschließlich exakte Schlüssel (Artikelnummer,
 * GTIN) — beide je Mandant eindeutig. Bewusst KEIN unscharfer Namensabgleich
 * (zu fehleranfällig für Stammartikel).
 *
 * @extends AbstractMatchProfile<Article>
 */
class ArticleMatchProfile extends AbstractMatchProfile {
    public function targetType(): string {
        return Article::class;
    }

    public function strategies(): array {
        return [
            new ExactField('number', MatchStrategy::EXACT, 'number'),
            new ExactField('gtin', MatchStrategy::EXACT, 'gtin'),
        ];
    }

    // Article kennt kein archived_at — die Basis lässt den Archiv-Filter dann weg.
    protected function newCandidateQuery(): Builder {
        return Article::query();
    }

    public function display(array $mapped): array {
        $title = (string) ($mapped['name'] ?? $mapped['number'] ?? '');
        $subtitle = (string) ($mapped['number'] ?? $mapped['gtin'] ?? '');

        return [
            'title' => $title !== '' ? $title : (string) __('(ohne Namen)'),
            'subtitle' => $subtitle !== '' && $subtitle !== $title ? $subtitle : null,
        ];
    }

    public function create(Organization $organization, array $mapped): Model {
        $attributes = array_intersect_key($mapped, array_flip((new Article)->getFillable()));
        $attributes['organization_id'] = $organization->id;

        return Article::create($attributes);
    }
}
