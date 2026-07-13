<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShipmentAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{CarrierConnection, Organization, User};
use App\Services\Shipping\ShippingProviderRegistry;
use App\Services\SqidEncoder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Admin-Verwaltung der Carrier-Anbindungen (Feature 059, MVP-128): je Carrier
 * eine Anbindung pro Organisation (unique(org, carrier)). Zugangsdaten
 * (DHL: GK-Benutzer/Passwort + dhl-api-key; UPS/FedEx: OAuth2-Client-ID/-Secret
 * in den Feldern Benutzer/Passwort) sind at-rest verschlüsselt und werden nie
 * ausgegeben; leere Felder beim Bearbeiten lassen die gespeicherten Werte
 * unverändert. Nur registrierte Carrier (aus der {@see ShippingProviderRegistry})
 * sind wählbar.
 */
class ShipmentAdminController extends Controller {
    /** Zugangsdaten-Schlüssel im verschlüsselten credentials-Array. */
    private const CREDENTIAL_KEYS = ['username', 'password', 'api_key'];

    /**
     * Pflicht-Zugangsdaten je Carrier bei Neuanlage: DHL braucht zusätzlich
     * den Gateway-`dhl-api-key`; die OAuth2-Carrier (UPS/FedEx) nur
     * Client-ID/-Secret (Felder Benutzer/Passwort).
     */
    private const REQUIRED_CREDENTIALS = [
        'dhl' => ['username', 'password', 'api_key'],
    ];

    private const REQUIRED_CREDENTIALS_DEFAULT = ['username', 'password'];

    public function index(ShippingProviderRegistry $registry): View {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        return view('admin.shipments.index', [
            'connections' => CarrierConnection::query()
                ->where('organization_id', $organization->id)
                ->orderBy('carrier')
                ->get(),
            'carriers' => $registry->carriers(),
        ]);
    }

    /** Legt eine Carrier-Anbindung an oder aktualisiert die bestehende (per Carrier). */
    public function store(Request $request, ShippingProviderRegistry $registry): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $carriers = $registry->carriers();

        $data = $request->validate([
            'carrier' => ['required', 'string', 'in:' . implode(',', $carriers)],
            'name' => ['required', 'string', 'max:120'],
            'username' => ['nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'billing_number' => ['nullable', 'string', 'max:60'],
            'sandbox' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ]);

        /** @var CarrierConnection $connection */
        $connection = CarrierConnection::query()->firstOrNew([
            'organization_id' => $organization->id,
            'carrier' => (string) $data['carrier'],
        ]);

        $credentials = $connection->exists ? ($connection->credentials ?? []) : [];
        foreach (self::CREDENTIAL_KEYS as $key) {
            $value = trim((string) ($data[$key] ?? ''));
            if ($value !== '') {
                $credentials[$key] = $value;
            }
        }

        if (! $connection->exists) {
            $required = self::REQUIRED_CREDENTIALS[(string) $data['carrier']] ?? self::REQUIRED_CREDENTIALS_DEFAULT;
            foreach ($required as $key) {
                if (empty($credentials[$key])) {
                    return back()->with('error', __('shipping.flash.credentials_required'))->withInput();
                }
            }
        }

        $connection->forceFill([
            'organization_id' => $organization->id,
            'carrier' => (string) $data['carrier'],
            'name' => (string) $data['name'],
            'credentials' => $credentials,
            'billing_number' => filled($data['billing_number'] ?? null) ? trim((string) $data['billing_number']) : null,
            'sandbox' => (bool) ($data['sandbox'] ?? false),
            'active' => (bool) ($data['active'] ?? false),
            'created_by' => $connection->exists ? $connection->created_by : $admin->id,
        ])->save();

        $connection->audit('shipping.connection_saved', [
            'by_user_id' => (int) $admin->id,
            'carrier' => $connection->carrier,
            'active' => $connection->active,
        ]);

        return back()->with('success', __('shipping.flash.saved'));
    }

    /** Deaktiviert eine Carrier-Anbindung (kein Label-/Tracking-Zugriff mehr). */
    public function disconnect(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'connection' => ['required', 'string'],
        ]);

        $connection = $this->resolveBySqid($organization, (string) $data['connection']);
        abort_unless($connection instanceof CarrierConnection, 404);

        if ($connection->active) {
            $connection->forceFill(['active' => false])->save();
            $connection->audit('shipping.disconnected', ['by_user_id' => (int) $admin->id, 'carrier' => $connection->carrier]);
        }

        return back()->with('success', __('shipping.flash.disconnected'));
    }

    private function resolveBySqid(Organization $organization, string $sqid): ?CarrierConnection {
        $decoded = app(SqidEncoder::class)->decode(CarrierConnection::class, $sqid);

        return $decoded !== null
            ? CarrierConnection::query()->whereKey($decoded)->where('organization_id', $organization->id)->first()
            : null;
    }

    private function admin(): User {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->isAdmin(), 403);
        abort_unless($user->organization_id !== null, 422, 'Kein Organisationskontext.');

        return $user;
    }

    private function organization(User $admin): Organization {
        $org = $admin->organization;
        abort_unless($org instanceof Organization, 422, 'Kein Organisationskontext.');

        return $org;
    }
}
