<?php

/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Services\Licensing\LicenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LicenseController extends Controller
{
    public function __construct(private readonly LicenseService $service) {}

    public function show(Request $request): View
    {
        $result = $this->service->current($request->getHost());

        return view('licensing.required', [
            'status' => $result->status,
            'message' => $result->message,
            'host' => $request->getHost(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'license_key' => ['required', 'string', 'max:8192'],
        ]);

        $result = $this->service->install($data['license_key']);

        if (! $result->isUsable()) {
            return back()->withErrors([
                'license_key' => $result->message ?? 'Lizenz konnte nicht installiert werden ('.$result->status->value.').',
            ]);
        }

        return redirect('/')->with('status', 'Lizenz erfolgreich aktiviert.');
    }
}
