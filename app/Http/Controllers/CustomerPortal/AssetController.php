<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\CustomerPortal;

use App\Enums\Protocol\ProtocolVisibility;
use App\Http\Controllers\Controller;
use App\Models\{Asset, User};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Portal-Sicht der Objektakte (Feature 027, Rang 50): eigene Objekte des
 * Kunden mit strikt kundensichtbarem Schnitt — Stammdaten, Prüf-/
 * Wartungstermine, abgeschlossene Wartungen und kundensichtbare Protokolle.
 * Interne Defekt-Details bleiben bewusst draußen.
 */
class AssetController extends Controller {
    public function index(): View {
        $user = $this->portalUser();

        $assets = Asset::query()
            ->where('customer_id', $user->customer_id)
            ->orderBy('name')
            ->paginate(25);

        return view('customer.assets.index', ['assets' => $assets]);
    }

    public function show(Asset $asset): View {
        $user = $this->portalUser();
        abort_unless((int) $asset->customer_id === (int) $user->customer_id, 403);

        $asset->load([
            'maintenancePlans' => fn ($q) => $q->orderBy('next_due_on'),
            'protocols' => fn ($q) => $q->where('visibility', ProtocolVisibility::Customer->value)->orderByDesc('occurred_at'),
        ]);

        // Kundensichtbare Ereignisse: abgeschlossene Wartungen (aus der
        // Objekt-Timeline), keine internen Defekte/Zuweisungen.
        $timeline = array_values(array_filter(
            app(\App\Services\Asset\AssetTimelineService::class)->build($asset, 200),
            static fn (array $event): bool => $event['kind'] === 'maintenance.completed',
        ));

        return view('customer.assets.show', [
            'asset' => $asset,
            'timeline' => $timeline,
        ]);
    }

    private function portalUser(): User {
        /** @var User $user */
        $user = Auth::guard('customer')->user();
        abort_if($user->customer_id === null, 403);

        return $user;
    }
}
