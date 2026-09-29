<?php
/*
 * Created on   : Mon Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PhoneDirectoryController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\PhoneDirectory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use App\Services\Contacts\ExternalPhoneContactDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Stammdaten-Anreicherung aus der Kundenakte: füllt das fehlende Feld (Name
 * bzw. Firma) mit dem Namen, den der Rufnummern-Aggregator (CRM-Verzeichnisse
 * + Telefonauskunft) zur hinterlegten Rufnummer liefert. Reine Anreicherung,
 * durch die Kunden-`update`-Policy geschützt.
 */
class PhoneDirectoryController extends Controller {
    public function fillCustomer(Customer $customer, ExternalPhoneContactDirectory $directory): RedirectResponse {
        Gate::authorize('update', $customer);

        $phone = trim((string) ($customer->phone ?: $customer->mobile));
        $organization = Organization::query()->find($customer->organization_id);
        if ($phone === '' || ! $organization instanceof Organization) {
            return back()->with('error', __('Für die Telefonauskunft ist keine Rufnummer hinterlegt.'));
        }

        $match = $directory->find($organization, $phone);
        $name = $match !== null ? trim((string) $match->displayName) : '';
        if ($name === '') {
            return back()->with('error', __('Die Telefonauskunft hat keinen Namen zu dieser Rufnummer geliefert.'));
        }

        if (trim((string) $customer->name) === '') {
            $customer->name = $name;
        } elseif (trim((string) $customer->company) === '') {
            $customer->company = $name;
        } else {
            return back()->with('info', __('Name und Firma sind bereits gefüllt — nichts zu übernehmen.'));
        }
        $customer->save();

        return back()->with('success', __('Aus der Telefonauskunft übernommen: :name', ['name' => $name]));
    }
}
