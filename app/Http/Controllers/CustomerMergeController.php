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

use App\Models\{Customer, CustomerMergeDismissal, Organization, User};
use App\Services\{CustomerDuplicateFinder, CustomerMergeService};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Kunden-Abgleich: stellt Dubletten-Kandidaten gegenüber und führt sie nach
 * Bestätigung zusammen (analog zur Lexoffice-Konflikt-Inbox, aber für lokale
 * Doppel-Datensätze, z. B. nach dem Toggl-Import).
 */
class CustomerMergeController extends Controller {
    public function index(Request $request, CustomerDuplicateFinder $finder): View {
        $user = $this->authorizeBilling();

        $confidence = (string) $request->input('confidence', 'all');
        $only = in_array($confidence, [
            CustomerDuplicateFinder::CONF_EXACT,
            CustomerDuplicateFinder::CONF_LIKELY,
            CustomerDuplicateFinder::CONF_FUZZY,
        ], true) ? $confidence : null;

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
        $this->authorizeBilling();

        [$source, $target] = $this->resolvePair($request);
        abort_if($source->getKey() === $target->getKey(), 422);

        return view('customers.merge-compare', [
            'source' => $source,
            'target' => $target,
        ]);
    }

    public function merge(Request $request, CustomerMergeService $merger): RedirectResponse {
        $this->authorizeBilling();

        [$source, $target] = $this->resolvePair($request);
        abort_if($source->getKey() === $target->getKey(), 422);

        // Optionale Feldauswahl: angehakte Felder werden aus der Quelle übernommen.
        $overrides = [];
        foreach ((array) $request->input('prefer_source', []) as $field) {
            $overrides[(string) $field] = $source->getAttribute((string) $field);
        }

        $merger->merge($source, $target, $overrides);

        return redirect()
            ->route('customers.duplicates.index')
            ->with('success', __('Kunde „:source" wurde in „:target" zusammengeführt.', [
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
    public function bulkMerge(Request $request, CustomerMergeService $merger): RedirectResponse {
        $this->authorizeBilling();

        $data = $request->validate([
            'pairs' => ['required', 'array', 'min:1'],
            'pairs.*' => ['string'],
        ]);

        $binder = new Customer;
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
            if (! $source instanceof Customer || ! $target instanceof Customer || $source->getKey() === $target->getKey()) {
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
            ->route('customers.duplicates.index')
            ->with('success', __(':merged Paar(e) zusammengeführt, :skipped übersprungen.', [
                'merged' => $merged,
                'skipped' => $skipped,
            ]));
    }

    public function dismiss(Request $request): RedirectResponse {
        $user = $this->authorizeBilling();

        [$source, $target] = $this->resolvePair($request);
        abort_if($source->getKey() === $target->getKey(), 422);

        CustomerMergeDismissal::query()->updateOrCreate(
            CustomerMergeDismissal::pairKey((int) $source->getKey(), (int) $target->getKey()),
            [
                'organization_id' => $user->organization_id,
                'dismissed_by' => $user->id,
            ],
        );

        return redirect()
            ->route('customers.duplicates.index')
            ->with('success', __('Paar als „kein Duplikat" gemerkt.'));
    }

    /**
     * Löst die beiden Kunden aus den Sqid-Eingaben auf (mandanten-gescopt über
     * den Global Scope des Route-Bindings).
     *
     * @return array{0: Customer, 1: Customer}  [Quelle, Ziel]
     */
    private function resolvePair(Request $request): array {
        $request->validate([
            'source' => ['required', 'string'],
            'target' => ['required', 'string'],
        ]);

        $binder = new Customer;
        $source = $binder->resolveRouteBinding((string) $request->input('source'));
        $target = $binder->resolveRouteBinding((string) $request->input('target'));

        abort_unless($source instanceof Customer && $target instanceof Customer, 404);

        return [$source, $target];
    }

    private function authorizeBilling(): User {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->canManageBilling(), 403);

        return $user;
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
