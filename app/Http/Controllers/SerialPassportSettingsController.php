<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SerialPassportSettingsController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Services\Inventory\SerialPassportService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Verwaltung des öffentlichen Geräte-Passes (Feature 047/048, E2).
 *
 * Der Token war bis zum Sicherheitsscan 2026-08-23 (S-44) nur von Hand in der
 * Datenbank zu setzen — es gab keinen Weg, ihn nach einem Leck zu wechseln.
 * Hier entstehen Ausstellen, Rotieren und Entziehen.
 *
 * Berechtigung ist bewusst `organization.update` und nicht `inventory.*`: die
 * Aktion öffnet eine Seite für jedermann ohne Anmeldung, das ist eine
 * Entscheidung über die Organisation, keine Lagerbuchung.
 */
class SerialPassportSettingsController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly SerialPassportService $passports) {}

    public function edit(Request $request): View {
        Gate::authorize(P::OrganizationUpdate->value);

        return view('serials._passport_dialog', [
            'status' => $this->passports->status($this->currentOrganization()),
            // Der Klartext überlebt genau eine Umleitung — danach ist er weg.
            'token' => $request->session()->get('serial_passport_token'),
        ]);
    }

    public function rotate(): RedirectResponse {
        Gate::authorize(P::OrganizationUpdate->value);

        $token = $this->passports->issue($this->currentOrganization());

        return redirect()->route('serials.passport.edit')
            ->with('serial_passport_token', $token)
            ->with('success', __('inventory.serial.passport.flash.issued'));
    }

    public function revoke(): RedirectResponse {
        Gate::authorize(P::OrganizationUpdate->value);

        $this->passports->revoke($this->currentOrganization());

        return redirect()->route('serials.passport.edit')
            ->with('success', __('inventory.serial.passport.flash.revoked'));
    }

    public function toggle(Request $request): RedirectResponse {
        Gate::authorize(P::OrganizationUpdate->value);

        $this->passports->setEnabled($this->currentOrganization(), $request->boolean('enabled'));

        return redirect()->route('serials.passport.edit')
            ->with('success', __('inventory.serial.passport.flash.saved'));
    }
}
