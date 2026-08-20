<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CtiAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{CtiConnection, Organization, User};
use App\Services\SqidEncoder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Admin-Verwaltung der Telefonie-/CTI-Anbindung (Feature 056, MVP-118): eine
 * providerneutrale Anbindung je Konfiguration mit einem Webhook-Token, der als
 * Teil der Webhook-URL genau einmal angezeigt wird (danach nur noch der Hash).
 * Verarbeitet werden nur Anruf-Metadaten, nie Gesprächsinhalte.
 */
class CtiAdminController extends Controller {
    private const PROVIDERS = ['sipgate', 'placetel', 'starface', 'generic'];

    public function index(): View {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $issued = session('cti_issued');

        return view('admin.cti.index', [
            'connections' => CtiConnection::query()
                ->where('organization_id', $organization->id)
                ->orderByDesc('id')
                ->get(),
            'providers' => self::PROVIDERS,
            'issuedUrl' => is_array($issued) ? ($issued['url'] ?? null) : null,
        ]);
    }

    /** Stellt eine Anbindung samt Webhook-Token aus; die URL wird einmalig geflasht. */
    public function store(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'provider' => ['required', 'in:' . implode(',', self::PROVIDERS)],
        ]);

        [$connection, $plain] = CtiConnection::issue($organization->id, (string) $data['name'], (string) $data['provider'], (int) $admin->id);
        $connection->audit('cti.connection_issued', ['by_user_id' => (int) $admin->id, 'provider' => $connection->provider]);

        return back()->with('cti_issued', ['url' => route('api.cti.webhook', ['token' => $plain])])
            ->with('success', __('cti.flash.issued'));
    }

    /**
     * Click-to-Dial je Anbindung konfigurieren (Audit 2026-08, W4.5):
     * API-Zugang, eigene Durchwahl und der bewusste Schalter. Ein leeres
     * Token-Feld laesst das gespeicherte Token unangetastet — so kann die
     * Durchwahl geaendert werden, ohne den Zugang neu einzutippen.
     */
    public function dialSettings(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'connection' => ['required', 'string'],
            'dial_enabled' => ['nullable', 'boolean'],
            'api_token' => ['nullable', 'string', 'max:500'],
            'api_base_url' => ['nullable', 'string', 'max:255', 'url'],
            'dial_extension' => ['nullable', 'string', 'max:64'],
        ]);

        $decoded = app(SqidEncoder::class)->decode(CtiConnection::class, (string) $data['connection']);
        $connection = $decoded !== null
            ? CtiConnection::query()->whereKey($decoded)->where('organization_id', $organization->id)->first()
            : null;
        abort_unless($connection instanceof CtiConnection, 404);

        $attributes = [
            'dial_enabled' => (bool) ($data['dial_enabled'] ?? false),
            'api_base_url' => $data['api_base_url'] ?? null,
            'dial_extension' => $data['dial_extension'] ?? null,
        ];
        if (trim((string) ($data['api_token'] ?? '')) !== '') {
            $attributes['api_token'] = (string) $data['api_token'];
        }

        $connection->forceFill($attributes)->save();
        // Bewusst ohne Tokenwert im Audit-Log.
        $connection->audit('cti.dial_configured', [
            'by_user_id' => (int) $admin->id,
            'dial_enabled' => $attributes['dial_enabled'],
        ]);

        return back()->with('success', __('cti.flash.dial_saved'));
    }

    /** Deaktiviert eine Anbindung (Webhook wird nicht mehr angenommen). */
    public function disconnect(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $decoded = app(SqidEncoder::class)->decode(CtiConnection::class, (string) $request->input('connection', ''));
        $connection = $decoded !== null
            ? CtiConnection::query()->whereKey($decoded)->where('organization_id', $organization->id)->first()
            : null;
        abort_unless($connection instanceof CtiConnection, 404);

        if ($connection->active) {
            $connection->forceFill(['active' => false])->save();
            $connection->audit('cti.disconnected', ['by_user_id' => (int) $admin->id]);
        }

        return back()->with('success', __('cti.flash.disconnected'));
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
