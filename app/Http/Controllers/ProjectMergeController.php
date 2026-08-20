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

use App\Http\Controllers\Concerns\MergesDuplicates;
use App\Models\{Organization, Project, ProjectMergeDismissal};
use App\Services\{ProjectDuplicateFinder, ProjectMergeService};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

/**
 * Projekt-Abgleich: stellt Dubletten-Kandidaten gegenüber und führt sie nach
 * Bestätigung zusammen (z. B. mehrfach angelegte „Wartung"-Projekte nach dem
 * Toggl-Import). Analog zum {@see CustomerMergeController}.
 */
class ProjectMergeController extends Controller {
    /** @use MergesDuplicates<Project> */
    use MergesDuplicates;

    public function index(Request $request, ProjectDuplicateFinder $finder): View {
        $user = $this->authorizeMerging();

        $only = $this->resolveConfidenceFilter($request, [
            ProjectDuplicateFinder::CONF_EXACT,
            ProjectDuplicateFinder::CONF_LIKELY,
            ProjectDuplicateFinder::CONF_FUZZY,
        ]);

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
        $this->authorizeMerging();

        [$source, $target] = $this->resolveDistinctMergePair($request);

        return view('projects.merge-compare', [
            'source' => $source,
            'target' => $target,
        ]);
    }

    public function merge(Request $request, ProjectMergeService $merger): RedirectResponse {
        return $this->performMerge(
            $request,
            static function (Project $source, Project $target, array $overrides) use ($merger): void {
                $merger->merge($source, $target, $overrides);
            },
        );
    }

    /**
     * Bulk-Merge mehrerer Auto-Vorschläge in einem Rutsch. Jedes Paar kommt als
     * „quelle:ziel"-Sqid-Paar; die Richtung entspricht dem Vorschlag. Paare, deren
     * Quelle/Ziel durch einen vorherigen Merge derselben Aktion bereits weg ist
     * (überlappende Vorschläge) oder die der Service ablehnt, werden übersprungen.
     */
    public function bulkMerge(Request $request, ProjectMergeService $merger): RedirectResponse {
        return $this->performBulkMerge(
            $request,
            static function (Project $source, Project $target) use ($merger): void {
                $merger->merge($source, $target);
            },
        );
    }

    public function dismiss(Request $request): RedirectResponse {
        $user = $this->authorizeMerging();

        [$source, $target] = $this->resolveDistinctMergePair($request);

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

    protected function mergeModelClass(): string {
        return Project::class;
    }

    protected function mergeIndexRoute(): string {
        return 'projects.duplicates.index';
    }

    protected function mergedMessage(Model $source, Model $target): string {
        return (string) __('Projekt „:source" wurde in „:target" zusammengeführt.', [
            'source' => $source->name,
            'target' => $target->name,
        ]);
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

}
