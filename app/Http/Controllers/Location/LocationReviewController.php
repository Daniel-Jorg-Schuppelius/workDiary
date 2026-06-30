<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocationReviewController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
use App\Models\Location\LocationPendingEntry;
use App\Services\Location\VisitMaterializer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Review-Inbox: aus Geofence-Besuchen abgeleitete Zeitvorschläge bestätigen
 * oder verwerfen. Jeder Nutzer sieht ausschließlich seine eigene Bewegungsspur
 * (Datenschutz) – auch Admins greifen hier nicht auf fremde Vorschläge zu.
 */
class LocationReviewController extends Controller {
    public function __construct(private readonly VisitMaterializer $materializer) {}

    public function index(Request $request): View {
        $entries = LocationPendingEntry::query()
            ->where('user_id', $this->authUser()->id)
            ->where('status', LocationPendingEntry::STATUS_OPEN)
            ->with(['customer', 'project'])
            ->orderBy('started_at')
            ->paginate(50);

        return view('location.review', ['entries' => $entries]);
    }

    public function confirm(Request $request, LocationPendingEntry $entry): RedirectResponse {
        $this->authorizeOwnership($request, $entry);

        $this->materializer->confirm($entry, $this->authUser());

        return redirect()->route('location.review.index')
            ->with('success', __('Zeitbuchung erstellt.'));
    }

    public function dismiss(Request $request, LocationPendingEntry $entry): RedirectResponse {
        $this->authorizeOwnership($request, $entry);

        $this->materializer->dismiss($entry, $this->authUser());

        return redirect()->route('location.review.index')
            ->with('success', __('Vorschlag verworfen.'));
    }

    private function authorizeOwnership(Request $request, LocationPendingEntry $entry): void {
        abort_unless($entry->user_id === $this->authUser()->id, Response::HTTP_FORBIDDEN);
    }
}
