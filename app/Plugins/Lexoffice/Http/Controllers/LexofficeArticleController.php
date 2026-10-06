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
use App\Models\Platform\User;
use App\Plugins\Lexoffice\Enums\LexofficeMatchPolicy;
use App\Plugins\Lexoffice\LexofficeConfig;
use App\Plugins\Lexoffice\Models\LexofficeArticle;
use App\Plugins\Lexoffice\Services\LexofficeArticleSync;
use App\Support\{ErrorText, SortableQuery};
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Cache};
use Illuminate\View\View;
use Throwable;

/**
 * Verwaltungs-UI für die lokal gecachten Lexoffice-Artikel (Produkte & Leistungen).
 *
 * Nur lesend plus manueller Sync-Anstoß — die eigentliche Pflege erfolgt in
 * Lexoffice, der Pull-Sync ({@see LexofficeArticleSync}) hält den Cache aktuell.
 */
class LexofficeArticleController extends Controller {
    private const ALLOWED_SORTS = ['name', 'article_number', 'type', 'unit_name', 'net_unit_price', 'vat_rate', 'archived_at'];

    public function index(Request $request): View {
        $user = $this->user();
        abort_unless($user->can(Permission::ArticleViewAny->value), 403);

        $search = trim((string) $request->input('q', ''));
        $type = (string) $request->input('type', '');
        $status = (string) $request->input('status', 'active');

        [$sort, $dir] = SortableQuery::resolve($request, self::ALLOWED_SORTS, 'name', 'asc');

        $query = LexofficeArticle::query()
            ->where('organization_id', $user->organization_id)
            ->orderBy($sort, $dir);

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->whereLikeEscaped('name', $search)
                    ->orWhereLikeEscaped('article_number', $search)
                    ->orWhereLikeEscaped('description', $search);
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
            'sort' => $sort,
            'dir' => $dir,
            'canSync' => $user->can(Permission::ArticleLexofficeSync->value),
        ]);
    }

    /**
     * Rendert den Dialog-Inhalt (eingebettete Modal-Partial) mit den
     * Stammdaten eines Artikels. Wird per data-entry-modal-trigger nachgeladen.
     */
    public function details(LexofficeArticle $article): View {
        $user = $this->user();
        abort_unless($user->can(Permission::ArticleViewAny->value), 403);
        abort_unless($article->organization_id === $user->organization_id, 403);

        return view('lexoffice::articles._details', [
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

        // Die Konflikt-Strategie der Plugin-Einstellung gilt auch für Artikel (Entscheidung 2026-10-06).
        $policy = LexofficeMatchPolicy::fromSetting((string) $config['match_policy']);

        try {
            $result = Cache::lock(LexofficeConfig::apiLockKey((int) $organization->id), 1800)
                ->block(LexofficeConfig::API_LOCK_WAIT_SHORT, static fn (): array => (new LexofficeArticleSync($config['api_key'], $config['base_url'], $config['request_interval']))
                    ->withPolicy($policy)
                    ->sync($organization));

            $summary = __('Sync abgeschlossen: :created neu, :updated aktualisiert, :archived archiviert.', [
                'created' => $result['created'],
                'updated' => $result['updated'],
                'archived' => $result['archived'],
            ]);
            if ($result['conflicts'] > 0) {
                $summary .= ' ' . __('Konflikte zur manuellen Prüfung: :count.', ['count' => $result['conflicts']]);
            }

            return back()->with('success', $summary);
        } catch (LockTimeoutException) {
            return back()->with('error', __('Lexoffice wird gerade von einem anderen Lauf abgeglichen. Bitte versuchen Sie es in einigen Minuten erneut.'));
        } catch (Throwable $e) {
            return back()->with('error', __('Sync fehlgeschlagen: :msg', ['msg' => ErrorText::for($e)]));
        }
    }

    private function user(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
