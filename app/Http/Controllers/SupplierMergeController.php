<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierMergeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\{MergesDuplicates, ResolvesCurrentOrganization};
use App\Models\{Organization, Supplier, SupplierMergeDismissal};
use App\Services\{SupplierDuplicateFinder, SupplierMergeService};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

/**
 * Lieferanten-Abgleich (Audit 2026-08, W2.3): stellt Dubletten-Kandidaten
 * gegenüber und führt sie nach Bestätigung zusammen. Lieferanten entstehen
 * über vier unabhängige Pfade (manuell, Integrations-Inbox, Lexoffice-Sync,
 * CSV-Import) — dasselbe Dubletten-Risiko wie beim Kunden.
 *
 * Ablauf-Kern siehe {@see MergesDuplicates}.
 */
class SupplierMergeController extends Controller {
    /** @use MergesDuplicates<Supplier> */
    use MergesDuplicates;
    use ResolvesCurrentOrganization;

    public function index(Request $request, SupplierDuplicateFinder $finder): View {
        $user = $this->authorizeMerging();

        $only = $this->resolveConfidenceFilter($request, [
            SupplierDuplicateFinder::CONF_EXACT,
            SupplierDuplicateFinder::CONF_LIKELY,
            SupplierDuplicateFinder::CONF_FUZZY,
        ]);

        $organization = $user->organization;
        abort_unless($organization instanceof Organization, 403);

        return view('suppliers.duplicates', [
            'candidates' => $finder->candidates($organization, $only),
            'confidence' => $only ?? 'all',
            'suppliers' => $this->supplierOptions(),
        ]);
    }

    /**
     * Gegenüberstellung zweier frei gewählter Lieferanten vor dem
     * Zusammenführen, inkl. Feld-für-Feld-Auswahl. Speist den manuellen Modus
     * und den „Felder wählen…"-Pfad der Auto-Vorschläge.
     */
    public function compare(Request $request): View {
        $this->authorizeMerging();

        [$source, $target] = $this->resolveDistinctMergePair($request);

        return view('suppliers.merge-compare', [
            'source' => $source,
            'target' => $target,
        ]);
    }

    public function merge(Request $request, SupplierMergeService $merger): RedirectResponse {
        return $this->performMerge(
            $request,
            static function (Supplier $source, Supplier $target, array $overrides) use ($merger): void {
                $merger->merge($source, $target, $overrides);
            },
        );
    }

    public function bulkMerge(Request $request, SupplierMergeService $merger): RedirectResponse {
        return $this->performBulkMerge(
            $request,
            static function (Supplier $source, Supplier $target) use ($merger): void {
                $merger->merge($source, $target);
            },
        );
    }

    public function dismiss(Request $request): RedirectResponse {
        $user = $this->authorizeMerging();

        [$source, $target] = $this->resolveDistinctMergePair($request);

        SupplierMergeDismissal::query()->updateOrCreate(
            SupplierMergeDismissal::pairKey((int) $source->getKey(), (int) $target->getKey()),
            [
                'organization_id' => $this->currentOrganization()->id,
                'dismissed_by' => $user->id,
            ],
        );

        return redirect()
            ->route('suppliers.duplicates.index')
            ->with('success', __('Paar als „kein Duplikat" gemerkt.'));
    }

    protected function mergeModelClass(): string {
        return Supplier::class;
    }

    protected function mergeIndexRoute(): string {
        return 'suppliers.duplicates.index';
    }

    protected function mergedMessage(Model $source, Model $target): string {
        return (string) __('Lieferant „:source" wurde in „:target" zusammengeführt.', [
            'source' => $source->name,
            'target' => $target->name,
        ]);
    }

    /**
     * Aktive Lieferanten des Mandanten für die manuelle Ziel-/Quell-Auswahl.
     *
     * @return \Illuminate\Support\Collection<int, Supplier>
     */
    private function supplierOptions(): \Illuminate\Support\Collection {
        return Supplier::query()
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get(['id', 'name', 'number', 'company', 'vendor_number']);
    }
}
