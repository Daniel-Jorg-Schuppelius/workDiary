<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleCalculationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Article;

use App\Enums\Article\CostKind;
use App\Http\Controllers\Controller;
use App\Models\Article\{Article, ArticleCostApproach, WageGroup};
use App\Services\Article\ServiceCalculationService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Kalkulation eines Leistungsartikels (MVP-1055): Kostenansätze pflegen, das
 * Ergebnis je Kostenart sehen und den kalkulierten Preis als Verkaufspreis
 * übernehmen (der Preisverlauf schreibt der Beobachter des Artikels).
 */
class ArticleCalculationController extends Controller {
    public function __construct(private readonly ServiceCalculationService $calculation) {}

    public function show(Article $article): View {
        Gate::authorize('view', $article);
        $article->load(['costApproaches.componentArticle', 'costApproaches.wageGroup']);

        return view('articles.calculation.show', [
            'article' => $article,
            'result' => $this->calculation->calculate($article),
            'canEdit' => Gate::allows('update', $article),
        ]);
    }

    public function approachForm(Request $request, Article $article, ?ArticleCostApproach $approach = null): View {
        Gate::authorize('update', $article);
        $kind = CostKind::tryFrom((string) $request->query('kind')) ?? CostKind::Labour;

        return view('articles.calculation._approach_dialog', [
            'article' => $article,
            'approach' => $approach ?? new ArticleCostApproach(['cost_kind' => $kind->value]),
            'kinds' => CostKind::cases(),
            'wageGroups' => WageGroup::query()->where('is_active', true)->orderBy('position')->orderBy('name')->get(),
            'components' => Article::query()->whereKeyNot($article->id)->where('purchasable', true)->orderBy('name')->limit(500)->get(['id', 'number', 'name', 'base_unit']),
        ]);
    }

    public function storeApproach(Request $request, Article $article): RedirectResponse {
        Gate::authorize('update', $article);
        $article->costApproaches()->create([
            ...$this->validateApproach($request),
            'organization_id' => $article->organization_id,
        ]);

        return redirect()->route('articles.calculation', $article)->with('status', __('article.calculation.flash.approach_saved'));
    }

    public function updateApproach(Request $request, Article $article, ArticleCostApproach $approach): RedirectResponse {
        Gate::authorize('update', $article);
        abort_unless($approach->article_id === $article->id, 404);
        $approach->update($this->validateApproach($request));

        return redirect()->route('articles.calculation', $article)->with('status', __('article.calculation.flash.approach_saved'));
    }

    public function destroyApproach(Article $article, ArticleCostApproach $approach): RedirectResponse {
        Gate::authorize('update', $article);
        abort_unless($approach->article_id === $article->id, 404);
        $approach->delete();

        return redirect()->route('articles.calculation', $article)->with('status', __('article.calculation.flash.approach_deleted'));
    }

    public function adoptPrice(Article $article): RedirectResponse {
        Gate::authorize('update', $article);
        $result = $this->calculation->calculate($article);
        if ($result === null || $result->price->isZero()) {
            return back()->with('error', __('article.calculation.flash.nothing_to_adopt'));
        }
        $article->update(['default_sale_price' => $result->price->getAmount()]);

        return redirect()->route('articles.calculation', $article)->with('status', __('article.calculation.flash.price_adopted'));
    }

    /** @return array<string, mixed> */
    private function validateApproach(Request $request): array {
        $request->merge([
            'component_article_id' => Sqid::decodeOrNumeric(Article::class, $request->input('component_article_id')),
            'wage_group_id' => Sqid::decodeOrNumeric(WageGroup::class, $request->input('wage_group_id')),
        ]);
        $data = $request->validate([
            'cost_kind' => ['required', Rule::enum(CostKind::class)],
            'description' => ['nullable', 'string', 'max:255'],
            'component_article_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('articles')],
            'wage_group_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('wage_groups')],
            'quantity' => ['required', 'numeric', 'min:0', 'max:999999'],
            'unit' => ['nullable', 'string', 'max:32'],
            'minutes' => ['nullable', 'required_if:cost_kind,labour', 'numeric', 'min:0', 'max:99999'],
            'unit_cost_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        $isLabour = $data['cost_kind'] === CostKind::Labour->value;

        return [
            'cost_kind' => $data['cost_kind'],
            'description' => $data['description'] ?? null,
            'component_article_id' => $data['cost_kind'] === CostKind::Material->value ? ($data['component_article_id'] ?? null) : null,
            'wage_group_id' => $isLabour ? ($data['wage_group_id'] ?? null) : null,
            'quantity' => (string) $data['quantity'],
            'unit' => $data['unit'] ?? null,
            'minutes' => $isLabour ? (string) $data['minutes'] : null,
            'unit_cost_amount' => $isLabour || ! isset($data['unit_cost_amount']) ? null : (string) $data['unit_cost_amount'],
            'position' => (int) ($data['position'] ?? 0),
        ];
    }
}
