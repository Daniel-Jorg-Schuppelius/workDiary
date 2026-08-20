<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MergesDuplicates.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Gemeinsamer Ablauf der Dubletten-Zusammenführung (Audit 2026-08, W2.2).
 *
 * Customer- und ProjectMergeController waren nach Entitätsnamen-Normalisierung
 * zu ~75 % identisch (Paar-Auflösung, Feldauswahl, Bulk-Merge über
 * „quelle:ziel"-Sqid-Paare, Dismissal-Upsert, Berechtigung). Das Service-Layer
 * hatte mit {@see AbstractEntityMergeService}/{@see \App\Services\AbstractDuplicateFinder}
 * längst seine Abstraktion — dem Controller-Layer fehlte sie. Das Concern
 * schließt die Lücke, bevor mit Supplier (W2.3) die dritte Kopie entstünde.
 *
 * Entitäts-spezifisch bleiben nur die {@see mergeModelClass()}-Bindung, die
 * Rücksprungroute und die Erfolgsmeldung.
 *
 * @template TModel of Model
 */
trait MergesDuplicates {
    /**
     * Modellklasse der zusammenzuführenden Entität (für Route-Binding).
     *
     * @return class-string<TModel>
     */
    abstract protected function mergeModelClass(): string;

    /** Route der Dubletten-Übersicht (Rücksprung nach jeder Aktion). */
    abstract protected function mergeIndexRoute(): string;

    /**
     * Erfolgsmeldung nach einem Einzel-Merge.
     *
     * @param  TModel  $source
     * @param  TModel  $target
     */
    abstract protected function mergedMessage(Model $source, Model $target): string;

    /**
     * Löst Quelle und Ziel aus den Sqid-Eingaben auf (mandanten-gescopt über
     * den Global Scope des Route-Bindings).
     *
     * @return array{0: TModel, 1: TModel}  [Quelle, Ziel]
     */
    protected function resolveMergePair(Request $request): array {
        $request->validate([
            'source' => ['required', 'string'],
            'target' => ['required', 'string'],
        ]);

        $class = $this->mergeModelClass();
        $binder = new $class;
        $source = $binder->resolveRouteBinding((string) $request->input('source'));
        $target = $binder->resolveRouteBinding((string) $request->input('target'));

        abort_unless($source instanceof $class && $target instanceof $class, 404);

        return [$source, $target];
    }

    /**
     * Paar auflösen und Selbst-Merge ausschließen (422).
     *
     * @return array{0: TModel, 1: TModel}  [Quelle, Ziel]
     */
    protected function resolveDistinctMergePair(Request $request): array {
        [$source, $target] = $this->resolveMergePair($request);
        abort_if($source->getKey() === $target->getKey(), 422);

        return [$source, $target];
    }

    /**
     * Einzel-Merge mit optionaler Feldauswahl: angehakte Felder werden aus der
     * Quelle übernommen. Ein fachlicher Ablehnungsgrund des Services wird als
     * Formularfehler gemeldet (422) statt als Serverfehler.
     *
     * Der eigentliche Merge kommt als Callable herein, weil die konkreten
     * Services ihre `merge()` typisiert deklarieren (Customer/Project/…) —
     * die Basisklasse kennt sie nicht.
     *
     * @param  callable(TModel, TModel, array<string, mixed>): void  $merge
     */
    protected function performMerge(Request $request, callable $merge): RedirectResponse {
        $this->authorizeMerging();

        [$source, $target] = $this->resolveDistinctMergePair($request);

        $overrides = [];
        foreach ((array) $request->input('prefer_source', []) as $field) {
            $overrides[(string) $field] = $source->getAttribute((string) $field);
        }

        try {
            $merge($source, $target, $overrides);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['source' => $e->getMessage()]);
        }

        return redirect()
            ->route($this->mergeIndexRoute())
            ->with('success', $this->mergedMessage($source, $target));
    }

    /**
     * Bulk-Merge mehrerer Auto-Vorschläge in einem Rutsch. Jedes Paar kommt als
     * „quelle:ziel"-Sqid-Paar; die Richtung entspricht dem Vorschlag. Paare,
     * deren Quelle/Ziel durch einen vorherigen Merge derselben Aktion bereits
     * weg ist (überlappende Vorschläge) oder die der Service ablehnt, werden
     * übersprungen.
     *
     * @param  callable(TModel, TModel): void  $merge
     */
    protected function performBulkMerge(Request $request, callable $merge): RedirectResponse {
        $this->authorizeMerging();

        $data = $request->validate([
            'pairs' => ['required', 'array', 'min:1'],
            'pairs.*' => ['string'],
        ]);

        $class = $this->mergeModelClass();
        $binder = new $class;
        $merged = 0;
        $skipped = 0;

        foreach ($data['pairs'] as $raw) {
            [$sourceSqid, $targetSqid] = array_pad(explode(':', (string) $raw, 2), 2, null);
            if ((string) $sourceSqid === '' || (string) $targetSqid === '') {
                $skipped++;

                continue;
            }

            $source = $binder->resolveRouteBinding((string) $sourceSqid);
            $target = $binder->resolveRouteBinding((string) $targetSqid);
            if (! $source instanceof $class || ! $target instanceof $class || $source->getKey() === $target->getKey()) {
                $skipped++;

                continue;
            }

            try {
                $merge($source, $target);
                $merged++;
            } catch (\InvalidArgumentException) {
                $skipped++;
            }
        }

        return redirect()
            ->route($this->mergeIndexRoute())
            ->with('success', __(':merged Paar(e) zusammengeführt, :skipped übersprungen.', [
                'merged' => $merged,
                'skipped' => $skipped,
            ]));
    }

    /** Nur Abrechnungsberechtigte dürfen Stammdaten zusammenführen. */
    protected function authorizeMerging(): User {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->canManageBilling(), 403);

        return $user;
    }

    /**
     * Gültigkeitsbereich des Confidence-Filters der Übersicht; unbekannte
     * Werte bedeuten „alle".
     *
     * @param  list<string>  $allowed
     */
    protected function resolveConfidenceFilter(Request $request, array $allowed): ?string {
        $confidence = (string) $request->input('confidence', 'all');

        return in_array($confidence, $allowed, true) ? $confidence : null;
    }
}
