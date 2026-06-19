<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublicSerialController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Services\Inventory\SerialService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Öffentlicher Geräte-Pass (Feature 047/048, E2). Erlaubt eine Echtheits-/
 * Statusprüfung per Seriennummer OHNE Anmeldung – pro Organisation ausdrücklich
 * freizuschalten (`settings.serial_passport_enabled` + geheimer Token in der URL),
 * rate-limitiert und bewusst OHNE personenbezogene Daten (kein Kunde). Verhindert
 * Aufzählung durch unbekannten Token (404) und Drosselung.
 */
class PublicSerialController extends Controller {
    public function __construct(private readonly SerialService $serials) {}

    public function show(string $token, Request $request): View {
        // TENANT-BYPASS: Public-Route ohne Auth; Auflösung ausschließlich über den
        // geheimen Token; nicht freigeschaltete Organisationen liefern 404.
        $org = Organization::query()->withoutGlobalScopes()
            ->where('settings->serial_passport_token', $token)
            ->first();
        abort_unless($org instanceof Organization, 404);
        abort_unless((bool) data_get($org->settings, 'serial_passport_enabled'), 404);

        $serialNo = $request->string('serial')->toString();
        $serial = $serialNo !== '' ? $this->serials->lookup($org->id, $serialNo) : null;

        return view('public.serial-passport', [
            'token' => $token,
            'query' => $serialNo,
            'serial' => $serial,
            'searched' => $serialNo !== '',
            'orgName' => $org->name,
        ]);
    }
}
