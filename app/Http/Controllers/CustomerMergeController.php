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

use App\Models\{Customer, CustomerMergeDismissal, User};
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

        $candidates = $finder->candidates($user->organization, $only);

        return view('customers.duplicates', [
            'candidates' => $candidates,
            'confidence' => $only ?? 'all',
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
}
