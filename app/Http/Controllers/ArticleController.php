<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Article\{ArticleStatus, ArticleType, ArticleUnitKind};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Requests\SaveArticleRequest;
use App\Models\{Article, ArticleOptionDefinition, ArticleVariant};
use App\Services\Article\{ArticleService, VariantResolver};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use RuntimeException;

/**
 * Admin-UI des kanonischen Artikelstamms (Feature 048, MVP-060): Artikel-CRUD
 * als Modal-Dialog sowie Verwaltung von Optionen, Optionswerten, Einheiten und
 * Varianten auf der Detailseite. Modul-Gating über `articles.*` → module.lager.
 */
class ArticleController extends Controller {
    use ResolvesCurrentOrganization;

    private const ALLOWED_SORTS = ['name', 'number', 'created_at'];

    public function __construct(
        private readonly ArticleService $articles,
        private readonly VariantResolver $variants,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', Article::class);

        $status = $request->string('status')->toString() ?: 'active';
        $search = $request->string('q')->toString();
        $sort = in_array($request->string('sort')->toString(), self::ALLOWED_SORTS, true)
            ? $request->string('sort')->toString()
            : 'name';
        $dir = $request->string('dir')->toString() === 'desc' ? 'desc' : 'asc';

        $articles = Article::query()
            ->withCount('variants')
            ->when($search !== '', fn($q) => $q->where(fn($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('number', 'like', "%{$search}%")))
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->orderBy($sort, $dir)
            ->paginate(25)
            ->withQueryString();

        return view('articles.index', [
            'articles' => $articles,
            'status' => $status,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
            'types' => ArticleType::cases(),
            'statuses' => ArticleStatus::cases(),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', Article::class);

        return $this->form(null);
    }

    public function store(SaveArticleRequest $request): RedirectResponse {
        Gate::authorize('create', Article::class);

        $data = $request->validated();
        $data['created_by'] = Auth::id();
        $article = $this->articles->createArticle($this->currentOrganization(), $data);

        return redirect()->route('articles.show', $article)
            ->with('success', __('article.flash.created'));
    }

    public function show(Article $article): View {
        Gate::authorize('view', $article);

        $article->load(['optionDefinitions.values', 'variants.optionValues', 'units', 'externalMappings']);

        return view('articles.show', [
            'article' => $article,
            'unitKinds' => ArticleUnitKind::cases(),
        ]);
    }

    public function edit(Article $article): View {
        Gate::authorize('update', $article);

        return $this->form($article);
    }

    public function update(SaveArticleRequest $request, Article $article): RedirectResponse {
        Gate::authorize('update', $article);

        $article->update($request->validated());

        return redirect()->route('articles.show', $article)
            ->with('success', __('article.flash.updated'));
    }

    public function destroy(Article $article): RedirectResponse {
        Gate::authorize('delete', $article);

        if (! $this->articles->canDelete($article)) {
            return redirect()->route('articles.show', $article)
                ->with('error', __('article.flash.delete_blocked'));
        }

        $article->delete();

        return redirect()->route('articles.index')
            ->with('success', __('article.flash.deleted'));
    }

    public function retire(Article $article): RedirectResponse {
        Gate::authorize('update', $article);

        $this->articles->retire($article);

        return redirect()->route('articles.show', $article)
            ->with('success', __('article.flash.retired'));
    }

    // ── Verschachtelte Stammdaten ───────────────────────────────────────

    public function storeOption(Request $request, Article $article): RedirectResponse {
        Gate::authorize('update', $article);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $article->optionDefinitions()->create($data + ['active' => true]);

        return back()->with('success', __('article.flash.option_added'));
    }

    public function storeOptionValue(Request $request, Article $article, ArticleOptionDefinition $option): RedirectResponse {
        Gate::authorize('update', $article);
        abort_unless((int) $option->article_id === (int) $article->id, 404);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'label' => ['required', 'string', 'max:255'],
        ]);

        $option->values()->create($data + ['active' => true]);

        return back()->with('success', __('article.flash.value_added'));
    }

    public function storeUnit(Request $request, Article $article): RedirectResponse {
        Gate::authorize('update', $article);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'label' => ['nullable', 'string', 'max:255'],
            'kind' => ['required', \Illuminate\Validation\Rule::enum(ArticleUnitKind::class)],
            'factor_to_base' => ['required', 'numeric', 'gt:0'],
        ]);

        $article->units()->create($data + ['active' => true]);

        return back()->with('success', __('article.flash.unit_added'));
    }

    public function storeVariant(Request $request, Article $article): RedirectResponse {
        Gate::authorize('update', $article);
        $data = $request->validate([
            'option_value_ids' => ['required', 'array', 'min:1'],
            'option_value_ids.*' => ['integer'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $variant = $this->variants->createVariant(
                $article,
                array_values(array_map('intval', $data['option_value_ids'])),
                ['created_by' => Auth::id(), 'sale_price' => $data['sale_price'] ?? null],
            );
            $this->articles->assignVariantSku($variant);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('article.flash.variant_added'));
    }

    public function retireVariant(Article $article, ArticleVariant $variant): RedirectResponse {
        Gate::authorize('update', $article);
        abort_unless((int) $variant->article_id === (int) $article->id, 404);

        $variant->update(['status' => ArticleStatus::Retired->value]);

        return back()->with('success', __('article.flash.variant_retired'));
    }

    private function form(?Article $article): View {
        return view('articles._form_dialog', [
            'article' => $article,
            'isDialog' => true,
            'types' => ArticleType::cases(),
            'statuses' => ArticleStatus::cases(),
        ]);
    }
}
