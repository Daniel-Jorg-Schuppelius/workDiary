<?php
/*
 * Created on   : Tue Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectMergeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\{Organization, Project, ProjectMergeDismissal, User};
use App\Services\{ProjectDuplicateFinder, ProjectMergeService};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Projekt-Abgleich: stellt Dubletten-Kandidaten gegenüber und führt sie nach
 * Bestätigung zusammen (z. B. mehrfach angelegte „Wartung"-Projekte nach dem
 * Toggl-Import). Analog zum {@see CustomerMergeController}.
 */
class ProjectMergeController extends Controller {
    public function index(Request $request, ProjectDuplicateFinder $finder): View {
        $user = $this->authorizeBilling();

        $confidence = (string) $request->input('confidence', 'all');
        $only = in_array($confidence, [
            ProjectDuplicateFinder::CONF_EXACT,
            ProjectDuplicateFinder::CONF_LIKELY,
            ProjectDuplicateFinder::CONF_FUZZY,
        ], true) ? $confidence : null;

        $organization = $user->organization;
        abort_unless($organization instanceof Organization, 403);

        $candidates = $finder->candidates($organization, $only);

        return view('projects.duplicates', [
            'candidates' => $candidates,
            'confidence' => $only ?? 'all',
            'projects' => $this->projectOptions(),
        ]);
    }

    /**
     * Gegenüberstellung zweier frei gewählter Projekte vor dem Zusammenführen,
     * inkl. Feld-für-Feld-Auswahl. Speist den manuellen Modus und den
     * „Felder wählen…"-Pfad der Auto-Vorschläge.
     */
    public function compare(Request $request): View {
        $this->authorizeBilling();

        [$source, $target] = $this->resolvePair($request);
        abort_if($source->getKey() === $target->getKey(), 422);

        return view('projects.merge-compare', [
            'source' => $source,
            'target' => $target,
        ]);
    }

    public function merge(Request $request, ProjectMergeService $merger): RedirectResponse {
        $this->authorizeBilling();

        [$source, $target] = $this->resolvePair($request);
        abort_if($source->getKey() === $target->getKey(), 422);

        // Optionale Feldauswahl: angehakte Felder werden aus der Quelle übernommen.
        $overrides = [];
        foreach ((array) $request->input('prefer_source', []) as $field) {
            $overrides[(string) $field] = $source->getAttribute((string) $field);
        }

        try {
            $merger->merge($source, $target, $overrides);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['source' => $e->getMessage()]);
        }

        return redirect()
            ->route('projects.duplicates.index')
            ->with('success', __('Projekt „:source" wurde in „:target" zusammengeführt.', [
                'source' => $source->name,
                'target' => $target->name,
            ]));
    }

    /**
     * Bulk-Merge mehrerer Auto-Vorschläge in einem Rutsch. Jedes Paar kommt als
     * „quelle:ziel"-Sqid-Paar; die Richtung entspricht dem Vorschlag. Paare, deren
     * Quelle/Ziel durch einen vorherigen Merge derselben Aktion bereits weg ist
     * (überlappende Vorschläge) oder die der Service ablehnt, werden übersprungen.
     */
    public function bulkMerge(Request $request, ProjectMergeService $merger): RedirectResponse {
        $this->authorizeBilling();

        $data = $request->validate([
            'pairs' => ['required', 'array', 'min:1'],
            'pairs.*' => ['string'],
        ]);

        $binder = new Project;
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
            if (! $source instanceof Project || ! $target instanceof Project || $source->getKey() === $target->getKey()) {
                $skipped++;
                continue;
            }

            try {
                $merger->merge($source, $target);
                $merged++;
            } catch (\InvalidArgumentException) {
                $skipped++;
            }
        }

        return redirect()
            ->route('projects.duplicates.index')
            ->with('success', __(':merged Paar(e) zusammengeführt, :skipped übersprungen.', [
                'merged' => $merged,
                'skipped' => $skipped,
            ]));
    }

    public function dismiss(Request $request): RedirectResponse {
        $user = $this->authorizeBilling();

        [$source, $target] = $this->resolvePair($request);
        abort_if($source->getKey() === $target->getKey(), 422);

        ProjectMergeDismissal::query()->updateOrCreate(
            ProjectMergeDismissal::pairKey((int) $source->getKey(), (int) $target->getKey()),
            [
                'organization_id' => $user->organization_id,
                'dismissed_by' => $user->id,
            ],
        );

        return redirect()
            ->route('projects.duplicates.index')
            ->with('success', __('Paar als „kein Duplikat" gemerkt.'));
    }

    /**
     * Löst die beiden Projekte aus den Sqid-Eingaben auf (mandanten-gescopt über
     * den Global Scope des Route-Bindings).
     *
     * @return array{0: Project, 1: Project}  [Quelle, Ziel]
     */
    private function resolvePair(Request $request): array {
        $request->validate([
            'source' => ['required', 'string'],
            'target' => ['required', 'string'],
        ]);

        $binder = new Project;
        $source = $binder->resolveRouteBinding((string) $request->input('source'));
        $target = $binder->resolveRouteBinding((string) $request->input('target'));

        abort_unless($source instanceof Project && $target instanceof Project, 404);

        return [$source, $target];
    }

    /**
     * Aktive Projekte des Mandanten für die manuelle Ziel-/Quell-Auswahl, mit
     * Kunde zum Gruppieren und Fremdkunde (Endkunde) zur Unterscheidung
     * gleichnamiger Projekte.
     *
     * @return \Illuminate\Support\Collection<int, Project>
     */
    private function projectOptions(): \Illuminate\Support\Collection {
        return Project::query()
            ->whereNull('archived_at')
            ->with(['customer:id,name', 'foreignCustomer:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'number', 'customer_id', 'foreign_customer_id']);
    }

    private function authorizeBilling(): User {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->canManageBilling(), 403);

        return $user;
    }
}
