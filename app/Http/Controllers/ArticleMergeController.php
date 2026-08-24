<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleMergeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\{MergesDuplicates, ResolvesCurrentOrganization};
use App\Models\{Article, ArticleMergeDismissal, Organization};
use App\Services\{ArticleDuplicateFinder, ArticleMergeService};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

/**
 * Artikel-Abgleich (Audit 2026-08, W2.9): stellt Dubletten-Kandidaten
 * gegenüber und führt sie nach Bestätigung zusammen.
 *
 * Anders als bei Kunde/Projekt/Lieferant hängt am Artikel eine append-only
 * geführte Bestandshistorie — der {@see ArticleMergeService} hängt deshalb
 * Varianten als Ganzes um und verweigert Merges, die Bestände vermischen
 * würden. Fachliche Absagen erscheinen als Formularfehler (422), nicht als
 * Serverfehler (Ablauf-Kern: {@see MergesDuplicates}).
 */
class ArticleMergeController extends Controller {
    /** @use MergesDuplicates<Article> */
    use MergesDuplicates;
    use ResolvesCurrentOrganization;

    public function index(Request $request, ArticleDuplicateFinder $finder): View {
        $user = $this->authorizeMerging();

        $only = $this->resolveConfidenceFilter($request, [
            ArticleDuplicateFinder::CONF_EXACT,
            ArticleDuplicateFinder::CONF_LIKELY,
            ArticleDuplicateFinder::CONF_FUZZY,
        ]);

        $organization = $user->organization;
        abort_unless($organization instanceof Organization, 403);

        return view('articles.duplicates', [
            'candidates' => $finder->candidates($organization, $only),
            'confidence' => $only ?? 'all',
            'articles' => $this->articleOptions(),
        ]);
    }

    public function compare(Request $request): View {
        $this->authorizeMerging();

        [$source, $target] = $this->resolveDistinctMergePair($request);

        return view('articles.merge-compare', [
            'source' => $source,
            'target' => $target,
        ]);
    }

    public function merge(Request $request, ArticleMergeService $merger): RedirectResponse {
        return $this->performMerge(
            $request,
            static function (Article $source, Article $target, array $overrides) use ($merger): void {
                $merger->merge($source, $target, $overrides);
            },
        );
    }

    public function bulkMerge(Request $request, ArticleMergeService $merger): RedirectResponse {
        return $this->performBulkMerge(
            $request,
            static function (Article $source, Article $target) use ($merger): void {
                $merger->merge($source, $target);
            },
        );
    }

    public function dismiss(Request $request): RedirectResponse {
        $user = $this->authorizeMerging();

        [$source, $target] = $this->resolveDistinctMergePair($request);

        ArticleMergeDismissal::query()->updateOrCreate(
            ArticleMergeDismissal::pairKey((int) $source->getKey(), (int) $target->getKey()),
            [
                'organization_id' => $this->currentOrganization()->id,
                'dismissed_by' => $user->id,
            ],
        );

        return redirect()
            ->route('articles.duplicates.index')
            ->with('success', __('Paar als „kein Duplikat" gemerkt.'));
    }

    protected function mergeModelClass(): string {
        return Article::class;
    }

    protected function mergeIndexRoute(): string {
        return 'articles.duplicates.index';
    }

    protected function mergedMessage(Model $source, Model $target): string {
        return (string) __('Artikel „:source" wurde in „:target" zusammengeführt.', [
            'source' => $source->name,
            'target' => $target->name,
        ]);
    }

    /**
     * Artikel des Mandanten für die manuelle Ziel-/Quell-Auswahl.
     *
     * @return \Illuminate\Support\Collection<int, Article>
     */
    private function articleOptions(): \Illuminate\Support\Collection {
        return Article::query()
            ->orderBy('name')
            ->get(['id', 'name', 'number', 'gtin']);
    }
}
