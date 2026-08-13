<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SavedReportViewController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\{SavedReportView, User};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Route as RouteFacade};
use Illuminate\View\View;

/**
 * Gespeicherte Report-Ansichten (MVP-529, Q1 „Auswertungs-Modelle"):
 * benannte Kombination aus Report-URL (interne reports.*-Route) und
 * Filter-Parametern — persönlich oder org-weit geteilt. Angelegt wird per
 * eingefügter Report-URL; die Route wird gegen die Routen-Tabelle
 * aufgelöst und auf Auswertungs-Routen begrenzt (kein offener Redirect,
 * keine Fremd-URLs).
 */
class SavedReportViewController extends Controller {
    public function index(): View {
        /** @var User $user */
        $user = Auth::user();

        $views = SavedReportView::query()
            ->where(function ($q) use ($user): void {
                $q->where('created_by', $user->getKey())->orWhere('is_shared', true);
            })
            ->with('creator:id,name')
            ->orderBy('name')
            ->get();

        return view('report-views.index', [
            'views' => $views,
            'viewerId' => (int) $user->getKey(),
            'isAdmin' => $user->isAdmin(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:2000'],
            'is_shared' => ['nullable', 'boolean'],
        ]);

        $resolved = $this->resolveReportUrl((string) $data['url']);
        if ($resolved === null) {
            return back()->withInput()->with('error', __('Die URL ist keine interne Auswertungs-Seite.'));
        }

        $view = SavedReportView::query()->create([
            'organization_id' => $user->organization_id,
            'created_by' => $user->getKey(),
            'name' => $data['name'],
            'route_name' => $resolved['route'],
            'params' => $resolved['params'],
            'is_shared' => (bool) ($data['is_shared'] ?? false),
        ]);
        $view->audit('reportView.created', ['name' => $view->name, 'route' => $view->route_name, 'shared' => $view->is_shared]);

        return back()->with('status', __('Ansicht gespeichert.'));
    }

    public function toggleShare(SavedReportView $view): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();
        abort_unless((int) $view->created_by === (int) $user->getKey() || $user->isAdmin(), 403);

        $view->update(['is_shared' => ! $view->is_shared]);
        $view->audit($view->is_shared ? 'reportView.shared' : 'reportView.unshared', ['name' => $view->name]);

        return back()->with('status', __('Ansicht aktualisiert.'));
    }

    public function destroy(SavedReportView $view): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();
        abort_unless((int) $view->created_by === (int) $user->getKey() || $user->isAdmin(), 403);

        $view->audit('reportView.deleted', ['name' => $view->name]);
        $view->delete();

        return back()->with('status', __('Ansicht gelöscht.'));
    }

    /**
     * Interne Auswertungs-URL auf (Routen-Name, Query-Params) auflösen.
     * Erlaubt sind reports.*-Routen sowie die Konten-/Flex-Sichten.
     *
     * @return array{route: string, params: array<string, mixed>}|null
     */
    private function resolveReportUrl(string $url): ?array {
        $parts = parse_url(trim($url));
        if ($parts === false || empty($parts['path'])) {
            return null;
        }
        // Fremd-Hosts ablehnen (relative URLs sind erlaubt).
        if (isset($parts['host']) && $parts['host'] !== parse_url((string) config('app.url'), PHP_URL_HOST)) {
            return null;
        }

        $params = [];
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $params);
        }
        unset($params['export'], $params['page']);

        try {
            $matched = RouteFacade::getRoutes()->match(
                Request::create($parts['path'], 'GET'),
            );
        } catch (\Throwable) {
            return null;
        }

        $routeName = (string) $matched->getName();
        $allowed = str_starts_with($routeName, 'reports.')
            || in_array($routeName, ['flex.index', 'time-accounts.index'], true);
        if (! $allowed) {
            return null;
        }

        // Pfad-Parameter (z. B. Sqids) mit in die Params übernehmen.
        foreach ($matched->parameters() as $key => $value) {
            if (is_scalar($value)) {
                $params[$key] = $value;
            }
        }

        return ['route' => $routeName, 'params' => $params];
    }
}
