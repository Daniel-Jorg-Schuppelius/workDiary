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

use App\Enums\Location\LocationPendingEntryStatus;
use App\Http\Controllers\Controller;
use App\Models\Diary\DiaryEntry;
use App\Models\Location\LocationPendingEntry;
use App\Services\Location\VisitMaterializer;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\DB;
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
        $user = $this->authUser();
        $entries = LocationPendingEntry::query()
            ->where('user_id', $user->id)
            ->where('status', LocationPendingEntryStatus::Open)
            ->with(['customer', 'project', 'diaryEntry:id,title'])
            ->orderBy('started_at')
            ->paginate(50);

        // Aufträge des Nutzers an den Besuchstagen — zum Wechsel des Auftrags am Vorschlag (MVP-1182).
        $dates = $entries->getCollection()->map(static fn(LocationPendingEntry $e): string => $e->suggested_date->toDateString())->unique()->values()->all();
        $ordersByDate = $dates === [] ? collect() : DiaryEntry::query()
            ->where(fn($q) => $q->where('assigned_user_id', $user->id)->orWhere('user_id', $user->id))
            ->whereIn(DB::raw('DATE(scheduled_for)'), $dates)
            ->orderBy('tour_position')
            ->orderBy('id')
            ->get(['id', 'title', 'scheduled_for', 'customer_id'])
            ->groupBy(static fn(DiaryEntry $e): string => (string) $e->scheduled_for?->toDateString());

        return view('location.review', ['entries' => $entries, 'ordersByDate' => $ordersByDate]);
    }

    public function confirm(Request $request, LocationPendingEntry $entry): RedirectResponse {
        $this->authorizeOwnership($request, $entry);
        $overrides = [];
        if ($request->has('diary_entry_id')) {
            // Nur eigene Aufträge (zugewiesen oder angelegt); leer = ohne Auftrag buchen (MVP-1182).
            $orderId = Sqid::decodeOrNumeric(DiaryEntry::class, (string) $request->input('diary_entry_id'));
            $order = $orderId !== null
                ? DiaryEntry::query()->whereKey($orderId)->where(fn($q) => $q->where('assigned_user_id', $entry->user_id)->orWhere('user_id', $entry->user_id))->first()
                : null;
            $overrides['diary_entry_id'] = $order?->id;
            if ($order instanceof DiaryEntry && $order->project_id !== null) {
                $overrides['project_id'] = $order->project_id;
            }
        }

        $this->materializer->confirm($entry, $this->authUser(), $overrides);

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
