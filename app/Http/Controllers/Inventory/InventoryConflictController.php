<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryConflictController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Inventory;

use App\Enums\Integration\ExternalConflictStatus;
use App\Enums\User\Permission as P;
use App\Http\Controllers\Controller;
use App\Models\Integration\PendingExternalConflict;
use App\Models\Platform\User;
use App\Plugins\PluginManager;
use App\Services\Inventory\InventoryConflictResolver;
use App\Support\{ErrorText, Trans};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use RuntimeException;

/**
 * Konflikt-Inbox der Fremdsysteme (Feature 048, MVP-072).
 *
 * `inventory_outbox`: Schlägt die externe Spiegelung einer lokal gebuchten
 * Bewegung endgültig fehl, kann ein Berechtigter
 *  - keep-local:  den lokalen Stand bewusst beibehalten, oder
 *  - compensate:  die Bewegung per Gegenbuchung ausgleichen.
 *
 * `article`: Ein lokal geänderter Artikel weicht vom Stand im Fremdsystem ab
 * (heute: Lexware Office). Die Liste zeigt beide Stände aus dem gespeicherten
 * Schnappschuss; ein Berechtigter kann
 *  - keep-local:   den lokalen Stand behalten (geht beim nächsten Push ins Fremdsystem),
 *  - adopt-remote: den Stand des Fremdsystems übernehmen (über das meldende Plugin), oder
 *  - dismiss:      den Konflikt ohne Abgleich verwerfen.
 *
 * Rechte je Art (Entscheidung 2026-10-06): sehen mit inventory.viewAny ODER
 * article.viewAny — ohne Lagerrecht nur Artikelkonflikte, ohne Artikelrecht nur
 * Bestandskonflikte. Auflösen mit inventory.post (Bestand) bzw. article.manage (Artikel).
 */
class InventoryConflictController extends Controller {
    public function __construct(private readonly InventoryConflictResolver $resolver, private readonly PluginManager $plugins) {}

    public function index(Request $request): View {
        $user = $this->user();
        $types = PendingExternalConflict::typesVisibleTo($user);
        abort_if($types === [], 403);

        $status = (string) $request->input('status', ExternalConflictStatus::Open->value);
        $type = (string) $request->input('type', '');
        $type = in_array($type, $types, true) ? $type : '';

        $query = PendingExternalConflict::query()
            ->where('organization_id', $user->organization_id)
            ->whereIn('conflict_type', $type !== '' ? [$type] : $types)
            ->orderByDesc('id');

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $conflicts = $query->paginate(25)->withQueryString();

        return view('inventory.conflicts.index', [
            'conflicts' => $conflicts,
            'articleDiffs' => $conflicts->getCollection()
                ->where('conflict_type', PendingExternalConflict::TYPE_ARTICLE)
                ->mapWithKeys(fn (PendingExternalConflict $conflict): array => [$conflict->id => $this->articleDiff($conflict)])
                ->all(),
            // Anzeigename des meldenden Plugins — die Aktion heißt „Lexoffice-Stand übernehmen“, nicht „lexoffice“.
            'pluginLabels' => $conflicts->getCollection()->pluck('plugin_id')->unique()
                ->mapWithKeys(fn (string $id): array => [$id => $this->plugins->find($id)?->name() ?? $id])
                ->all(),
            'types' => $types,
            'filters' => ['status' => $status, 'type' => $type],
            'canResolveStock' => $user->can(P::InventoryPost->value),
            'canResolveArticle' => $user->can(P::ArticleManage->value),
        ]);
    }

    public function keepLocal(PendingExternalConflict $conflict): RedirectResponse {
        $this->authorizeResolve($conflict);

        try {
            $this->resolver->keepLocal($conflict, Auth::id() !== null ? (int) Auth::id() : null);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        $flash = $conflict->conflict_type === PendingExternalConflict::TYPE_ARTICLE ? 'kept_local_article' : 'kept_local';

        return back()->with('success', __('inventory.conflict.flash.' . $flash));
    }

    public function adoptRemote(PendingExternalConflict $conflict): RedirectResponse {
        $this->authorizeResolve($conflict);

        try {
            $this->resolver->adoptRemote($conflict, Auth::id() !== null ? (int) Auth::id() : null);
        } catch (RuntimeException $e) {
            return redirect()->toList('inventory.conflicts.index')->with('error', ErrorText::for($e));
        }

        return redirect()->toList('inventory.conflicts.index')->with('success', __('inventory.conflict.flash.adopted_remote'));
    }

    public function compensate(PendingExternalConflict $conflict): RedirectResponse {
        $this->authorizeResolve($conflict);

        try {
            $this->resolver->compensate($conflict, Auth::id() !== null ? (int) Auth::id() : null);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return back()->with('success', __('inventory.conflict.flash.compensated'));
    }

    public function dismiss(PendingExternalConflict $conflict): RedirectResponse {
        $this->authorizeResolve($conflict);

        try {
            $this->resolver->dismiss($conflict, Auth::id() !== null ? (int) Auth::id() : null);
        } catch (RuntimeException $e) {
            return redirect()->toList('inventory.conflicts.index')->with('error', ErrorText::for($e));
        }

        return redirect()->toList('inventory.conflicts.index')->with('success', __('inventory.conflict.flash.dismissed'));
    }

    /** Auflösen je Art: Bestand braucht das Buchungsrecht, Artikel das Artikelrecht. */
    private function authorizeResolve(PendingExternalConflict $conflict): void {
        $user = $this->user();
        $permission = $conflict->conflict_type === PendingExternalConflict::TYPE_ARTICLE ? P::ArticleManage : P::InventoryPost;
        Gate::authorize($permission->value);
        abort_unless($conflict->organization_id === $user->organization_id, 403);
    }

    /**
     * Abweichende Felder eines Artikelkonflikts mit beiden Ständen — nur aus
     * dem gespeicherten Schnappschuss, ohne Zugriff auf das Fremdsystem-Plugin.
     *
     * @return list<array{label: string, local: string, remote: string}>
     */
    private function articleDiff(PendingExternalConflict $conflict): array {
        $rows = [];
        foreach ($conflict->diff_fields ?? [] as $field) {
            $rows[] = [
                'label' => Trans::or('inventory.conflict.field.' . $field, $field),
                'local' => $this->snapshotValue($conflict->local_snapshot[$field] ?? null),
                'remote' => $this->snapshotValue($conflict->remote_snapshot[$field] ?? null),
            ];
        }

        return $rows;
    }

    /** Altbestand hält Preis und Steuersatz als serialisiertes Wertobjekt (amount/value) statt als Dezimalzeichenkette. */
    private function snapshotValue(mixed $value): string {
        if (is_array($value)) {
            $value = $value['amount'] ?? $value['value'] ?? null;
        }

        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : '—';
    }

    private function user(): User {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
