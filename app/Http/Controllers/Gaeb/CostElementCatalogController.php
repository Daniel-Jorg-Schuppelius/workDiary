<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostElementCatalogController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Gaeb;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Costing\CostElementCatalog;
use App\Models\User;
use App\Services\Gaeb\CostElementCatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\{Auth, Gate};
use InvalidArgumentException;

/**
 * Baukostenkataloge (Feature 109, MVP-645).
 *
 * Ein Katalog ist ein Nachschlagewerk mit Kennwerten — er gehört der
 * Organisation und wird nicht je Vorhaben geführt.
 */
class CostElementCatalogController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly CostElementCatalogService $catalogs) {}

    public function index(): View {
        Gate::authorize(P::ProjectViewAny->value);

        return view('gaeb.cost-catalogs.index', [
            'catalogs' => CostElementCatalog::query()->withCount('elements')->orderBy('name')->get(),
            'canManage' => Gate::allows(P::ProjectImport->value),
        ]);
    }

    public function show(CostElementCatalog $catalog): View {
        Gate::authorize(P::ProjectViewAny->value);
        $this->guard($catalog);

        return view('gaeb.cost-catalogs.show', [
            'catalog' => $catalog,
            'elements' => $catalog->elements()->with('article')->paginate(100),
            'articles' => \App\Models\Article::query()->orderBy('name')->limit(500)->get(['id', 'name', 'number']),
            'canManage' => Gate::allows(P::ProjectImport->value),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(P::ProjectImport->value);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'name' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $catalog = $this->catalogs->import(
                (string) file_get_contents($request->file('file')->getRealPath()),
                $this->currentOrganization()->id,
                $this->actor(),
                $data['name'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cost-catalogs.show', $catalog)
            ->with('success', __('Baukostenkatalog „:name" übernommen (:count Elemente).', [
                'name' => $catalog->name,
                'count' => $catalog->elements()->count(),
            ]));
    }

    /**
     * Ein Kostenelement mit einem eigenen Artikel verknüpfen (MVP-645).
     *
     * Damit sieht man beim Artikel, wo sein Preis im Marktvergleich liegt —
     * **übernommen wird nichts**: Der Kennwert ist ein Anhaltspunkt aus
     * fremder Quelle, der eigene Preis bleibt der eigene.
     */
    public function linkArticle(Request $request, CostElementCatalog $catalog, \App\Models\Costing\CostElement $element): RedirectResponse {
        Gate::authorize(P::ProjectImport->value);
        $this->guard($catalog);
        abort_unless($element->cost_element_catalog_id === $catalog->id, 404);

        $raw = $request->input('article');
        $articleId = is_string($raw) && $raw !== ''
            ? app(\App\Services\SqidEncoder::class)->decode(\App\Models\Article::class, $raw)
            : null;

        // Der Org-Scope liegt am Modell; ein fremder Sqid findet nichts.
        if ($articleId !== null && ! \App\Models\Article::query()->whereKey($articleId)->exists()) {
            return back()->with('error', __('Artikel nicht gefunden.'));
        }

        $element->update(['article_id' => $articleId]);

        return back()->with('success', $articleId === null
            ? __('Verknüpfung entfernt.')
            : __('Kennwert mit Artikel verknüpft.'));
    }

    /** Ausgabe in der Bauform, in der der Katalog hereinkam. */
    public function export(CostElementCatalog $catalog): Response {
        Gate::authorize(P::ProjectViewAny->value);
        $this->guard($catalog);

        return response($this->catalogs->export($catalog), 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="Baukostenkatalog-' . $catalog->sqid . '.x50"',
        ]);
    }

    public function destroy(CostElementCatalog $catalog): RedirectResponse {
        Gate::authorize(P::ProjectImport->value);
        $this->guard($catalog);

        $catalog->delete();

        return redirect()->route('cost-catalogs.index')->with('success', __('Baukostenkatalog gelöscht.'));
    }

    private function guard(CostElementCatalog $catalog): void {
        abort_unless($catalog->organization_id === $this->currentOrganization()->id, 404);
    }

    private function actor(): User {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
