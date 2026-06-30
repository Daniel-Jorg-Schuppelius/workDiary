<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocationDeviceController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Controller;
use App\Models\Location\LocationDeviceToken;
use App\Services\Location\GoogleTimelineImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Self-Service für die eigene Standorterfassung: Einwilligung (Opt-in) sowie
 * Geräte-Tokens (OwnTracks/Traccar) ausstellen und widerrufen. Strikt auf den
 * angemeldeten Nutzer beschränkt.
 */
class LocationDeviceController extends Controller {
    public function index(Request $request): View {
        $tokens = LocationDeviceToken::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return view('location.devices', [
            'tokens' => $tokens,
            'optedIn' => (bool) $request->user()->getPreference(LocationController::OPT_IN_PREFERENCE, false),
        ]);
    }

    public function consent(Request $request): RedirectResponse {
        $enabled = $request->boolean('enabled');
        $request->user()->setPreference(LocationController::OPT_IN_PREFERENCE, $enabled);

        return redirect()->route('location.devices.index')->with(
            'success',
            $enabled ? __('Standorterfassung aktiviert.') : __('Standorterfassung deaktiviert.'),
        );
    }

    public function store(Request $request): RedirectResponse {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
        ]);

        // Token ausstellen impliziert die Einwilligung.
        $user = $request->user();
        if (! $user->getPreference(LocationController::OPT_IN_PREFERENCE, false)) {
            $user->setPreference(LocationController::OPT_IN_PREFERENCE, true);
        }

        [, $plain] = LocationDeviceToken::issue($user, $data['label']);

        // Klartext-URL nur einmalig zurückgeben (danach nicht rekonstruierbar).
        return redirect()->route('location.devices.index')
            ->with('success', __('Gerät hinzugefügt.'))
            ->with('location_device_url', url("/api/location/ingest/{$plain}"));
    }

    /**
     * Rückwirkender Import einer Google-Timeline-Export-Datei (JSON).
     */
    public function importGoogle(Request $request, GoogleTimelineImporter $importer): RedirectResponse {
        $request->validate([
            'file' => ['required', 'file', 'max:51200'],
        ]);

        $user = $request->user();
        if (! $user->getPreference(LocationController::OPT_IN_PREFERENCE, false)) {
            $user->setPreference(LocationController::OPT_IN_PREFERENCE, true);
        }

        $json = (string) file_get_contents($request->file('file')->getRealPath());
        $count = $importer->import($user, $json);

        return redirect()->route('location.devices.index')
            ->with('success', __(':count Standortpunkte importiert.', ['count' => $count]));
    }

    public function destroy(Request $request, LocationDeviceToken $device): RedirectResponse {
        abort_unless($device->user_id === $request->user()->id, Response::HTTP_FORBIDDEN);

        $device->forceFill(['revoked_at' => Carbon::now()])->save();

        return redirect()->route('location.devices.index')->with('success', __('Gerät widerrufen.'));
    }
}
