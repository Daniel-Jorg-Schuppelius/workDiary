<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CtiDialController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Services\Cti\Dial\{CtiDialException, CtiDialService};
use Illuminate\Http\{RedirectResponse, Request};

/**
 * Click-to-Dial (Feature 056/MVP-118; Audit 2026-08, W4.5): startet einen
 * ausgehenden Anruf über die Telefonanlage der Organisation.
 *
 * Die Nummer kommt aus dem angeklickten Datensatz (Kunde, Endkunde,
 * Ansprechpartner). Bewusst kein eigener Protokolleintrag — den Anruf erfasst
 * der CTI-Webhook ohnehin, ein zweiter Eintrag würde denselben Vorgang
 * doppelt zählen.
 */
class CtiDialController extends Controller {
    use ResolvesCurrentOrganization;

    public function __invoke(Request $request, CtiDialService $dialer): RedirectResponse {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:64'],
        ]);

        try {
            $dialer->dial($this->currentOrganization(), (string) $data['number']);
        } catch (CtiDialException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('cti.dial.started', ['number' => (string) $data['number']]));
    }
}
