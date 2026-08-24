<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerMergeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\{MergesDuplicates, ResolvesCurrentOrganization};
use App\Models\{Customer, CustomerMergeDismissal, Organization};
use App\Services\{CustomerDuplicateFinder, CustomerMergeService};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

/**
 * Kunden-Abgleich: stellt Dubletten-Kandidaten gegenüber und führt sie nach
 * Bestätigung zusammen (analog zur Lexoffice-Konflikt-Inbox, aber für lokale
 * Doppel-Datensätze, z. B. nach dem Toggl-Import).
 */
class CustomerMergeController extends Controller {
    /** @use MergesDuplicates<Customer> */
    use MergesDuplicates;
    use ResolvesCurrentOrganization;

    public function index(Request $request, CustomerDuplicateFinder $finder): View {
        $user = $this->authorizeMerging();

        $only = $this->resolveConfidenceFilter($request, [
            CustomerDuplicateFinder::CONF_EXACT,
            CustomerDuplicateFinder::CONF_LIKELY,
            CustomerDuplicateFinder::CONF_FUZZY,
        ]);

        $organization = $user->organization;
        abort_unless($organization instanceof Organization, 403);

        $candidates = $finder->candidates($organization, $only);

        return view('customers.duplicates', [
            'candidates' => $candidates,
            'confidence' => $only ?? 'all',
            'customers' => $this->customerOptions(),
        ]);
    }

    /**
     * Gegenüberstellung zweier frei gewählter Kunden vor dem Zusammenführen.
     * Erlaubt eine Feld-für-Feld-Auswahl (welcher Wert gewinnt), bevor der
     * eigentliche Merge ausgelöst wird. Speist sowohl den manuellen Modus als
     * auch den „Felder wählen…"-Pfad der Auto-Vorschläge.
     */
    public function compare(Request $request): View {
        $this->authorizeMerging();

        [$source, $target] = $this->resolveDistinctMergePair($request);

        return view('customers.merge-compare', [
            'source' => $source,
            'target' => $target,
        ]);
    }

    public function merge(Request $request, CustomerMergeService $merger): RedirectResponse {
        return $this->performMerge(
            $request,
            static function (Customer $source, Customer $target, array $overrides) use ($merger): void {
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
    public function bulkMerge(Request $request, CustomerMergeService $merger): RedirectResponse {
        return $this->performBulkMerge(
            $request,
            static function (Customer $source, Customer $target) use ($merger): void {
                $merger->merge($source, $target);
            },
        );
    }

    public function dismiss(Request $request): RedirectResponse {
        $user = $this->authorizeMerging();

        [$source, $target] = $this->resolveDistinctMergePair($request);

        CustomerMergeDismissal::query()->updateOrCreate(
            CustomerMergeDismissal::pairKey((int) $source->getKey(), (int) $target->getKey()),
            [
                'organization_id' => $this->currentOrganization()->id,
                'dismissed_by' => $user->id,
            ],
        );

        return redirect()
            ->route('customers.duplicates.index')
            ->with('success', __('Paar als „kein Duplikat" gemerkt.'));
    }

    protected function mergeModelClass(): string {
        return Customer::class;
    }

    protected function mergeIndexRoute(): string {
        return 'customers.duplicates.index';
    }

    protected function mergedMessage(Model $source, Model $target): string {
        return (string) __('Kunde „:source" wurde in „:target" zusammengeführt.', [
            'source' => $source->name,
            'target' => $target->name,
        ]);
    }

    /**
     * Kunden des Mandanten für die manuelle Ziel-/Quell-Auswahl.
     *
     * @return \Illuminate\Support\Collection<int, Customer>
     */
    private function customerOptions(): \Illuminate\Support\Collection {
        return Customer::query()
            ->orderBy('name')
            ->get(['id', 'name', 'number', 'company']);
    }
}
