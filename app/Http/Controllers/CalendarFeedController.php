<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendarFeedController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Verwaltet den persönlichen Kalender-Feed-Token. Anlegen/Rotieren
 * generiert einen neuen kryptografisch zufälligen 48-Zeichen-Token;
 * Widerrufen setzt das Feld zurück, sodass bestehende Abonnenten
 * den Feed verlieren (z. B. nach Geräteverlust).
 */
class CalendarFeedController extends Controller {
    public function show(): View {
        return view('account.calendar', [
            'user' => Auth::user(),
        ]);
    }

    public function rotate(): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();
        $user->calendar_feed_token = Str::random(48);
        $user->save();

        return redirect()
            ->route('account.calendar.show')
            ->with('status', __('Neuer Kalender-Link erzeugt.'));
    }

    public function revoke(): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();
        $user->calendar_feed_token = null;
        $user->save();

        return redirect()
            ->route('account.calendar.show')
            ->with('status', __('Kalender-Link widerrufen.'));
    }
}
