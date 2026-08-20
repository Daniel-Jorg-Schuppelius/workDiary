<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleDuplicateFinder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services;

use App\Models\{Article, ArticleMergeDismissal, Organization};
use App\Services\Integration\Match\{EntityMatcher, MatchProfile};
use App\Services\Integration\Profiles\ArticleDuplicateMatchProfile;
use Illuminate\Database\Eloquent\{Collection as EloquentCollection, Model};

/**
 * Findet Dubletten-Kandidaten unter den Artikeln einer Organisation (Audit
 * 2026-08, W2.9). Artikel entstehen über vier unabhängige Pfade (manuell bzw.
 * Katalog-Adoption, Integrations-Inbox, CSV-Import, Lexoffice-Sync).
 *
 * Nutzt ein **eigenes** {@see ArticleDuplicateMatchProfile}: Das
 * Import-Profil matcht auf `number`/`gtin`, die org-weit unique sind — unter
 * lokalen Artikeln kann das strukturell nie greifen. Tragfaehig ist die
 * Namensaehnlichkeit; Fehltreffer filtert {@see skipPair()} ueber
 * Basiseinheit und Steuerklasse (ein Merge waere dort ohnehin gesperrt).
 *
 * @extends AbstractDuplicateFinder<Article>
 */
class ArticleDuplicateFinder extends AbstractDuplicateFinder {
    public function __construct(
        EntityMatcher $matcher,
        private readonly ArticleDuplicateMatchProfile $profile,
    ) {
        parent::__construct($matcher);
    }

    protected function profile(): MatchProfile {
        return $this->profile;
    }

    protected function fetchCandidates(Organization $organization): EloquentCollection {
        return $this->profile->candidates($organization)->withCount('variants')->get();
    }

    /**
     * Ziel-Heuristik: gepflegte Artikelnummer > mehr Varianten > kleinere
     * (ältere) ID. Der Datensatz mit der ausgebauten Variantenstruktur
     * gewinnt — an ihr hängt die Bestandshistorie.
     */
    protected function score(Model $model): array {
        $hasNumber = trim((string) $model->number) !== '' ? 1 : 0;

        return [$hasNumber, (int) ($model->variants_count ?? 0), -((int) $model->id)];
    }

    /**
     * Paare ausschliessen, die der {@see \App\Services\ArticleMergeService}
     * ohnehin ablehnen wuerde - ein Vorschlag, der beim Klick scheitert,
     * waere schlechter als keiner.
     */
    protected function skipPair(Model $a, Model $b): bool {
        return (string) $a->base_unit !== (string) $b->base_unit
            || (string) $a->tax_class !== (string) $b->tax_class;
    }

    protected function dismissalModel(): string {
        return ArticleMergeDismissal::class;
    }

    protected function dismissalKeyColumns(): array {
        return ['article_low_id', 'article_high_id'];
    }
}
