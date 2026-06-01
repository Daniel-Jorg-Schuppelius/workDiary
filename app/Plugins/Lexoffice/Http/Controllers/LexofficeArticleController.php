<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeArticleController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Http\Controllers;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\{LexofficeArticle, User};
use App\Plugins\Lexoffice\{LexofficeArticleSync, LexofficeConfig};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

/**
 * Verwaltungs-UI für die lokal gecachten Lexoffice-Artikel (Produkte & Leistungen).
 *
 * Nur lesend plus manueller Sync-Anstoß — die eigentliche Pflege erfolgt in
 * Lexoffice, der Pull-Sync ({@see LexofficeArticleSync}) hält den Cache aktuell.
 */
class LexofficeArticleController extends Controller {
    public function index(Request $request): View {
        $user = $this->user();
        abort_unless($user->can(Permission::ArticleViewAny->value), 403);

        $search = trim((string) $request->input('q', ''));
        $type = (string) $request->input('type', '');
        $status = (string) $request->input('status', 'active');

        $query = LexofficeArticle::query()
            ->where('organization_id', $user->organization_id)
            ->orderBy('name');

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('article_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($type !== '' && in_array($type, ['PRODUCT', 'SERVICE'], true)) {
            $query->where('type', $type);
        }

        if ($status === 'archived') {
            $query->whereNotNull('archived_at');
        } elseif ($status !== 'all') {
            $query->whereNull('archived_at');
        }

        return view('lexoffice::articles.index', [
            'articles' => $query->paginate(25)->withQueryString(),
            'filters' => ['q' => $search, 'type' => $type, 'status' => $status],
            'canSync' => $user->can(Permission::ArticleLexofficeSync->value),
        ]);
    }

    public function show(LexofficeArticle $article): View {
        $user = $this->user();
        abort_unless($user->can(Permission::ArticleViewAny->value), 403);
        abort_unless($article->organization_id === $user->organization_id, 403);

        return view('lexoffice::articles.show', [
            'article' => $article,
        ]);
    }

    public function sync(): RedirectResponse {
        $user = $this->user();
        abort_unless($user->can(Permission::ArticleLexofficeSync->value), 403);

        $config = LexofficeConfig::resolve($user->organization_id);
        if (! is_string($config['api_key']) || $config['api_key'] === '') {
            return back()->with('error', __('Lexoffice ist für diese Organisation nicht konfiguriert.'));
        }

        $organization = $user->organization;
        if ($organization === null) {
            return back()->with('error', __('Keine Organisation zugeordnet.'));
        }

        try {
            $result = (new LexofficeArticleSync($config['api_key'], $config['base_url']))->sync($organization);

            return back()->with('success', __('Sync abgeschlossen: :created neu, :updated aktualisiert, :archived archiviert.', [
                'created' => $result['created'],
                'updated' => $result['updated'],
                'archived' => $result['archived'],
            ]));
        } catch (Throwable $e) {
            return back()->with('error', __('Sync fehlgeschlagen: :msg', ['msg' => $e->getMessage()]));
        }
    }

    private function user(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
