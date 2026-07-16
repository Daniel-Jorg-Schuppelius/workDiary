<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainProviderConnectionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin\Domain;

use App\Enums\Domain\{DomainConnectionStatus, DomainProviderEnvironment};
use App\Http\Controllers\Controller;
use App\Models\Domain\DomainProviderConnection;
use App\Services\Domain\{DomainConnectionService, DomainSyncService};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Zentrale Seite „DomainReselling-Verbindung" (Feature 083, MVP-385/394):
 * Verbindung anlegen (Zugangsdaten-Dialog), prüfen, Passwort rotieren, Pilot
 * bestätigen und trennen. Zugangsdaten liegen verschlüsselt und erscheinen nie
 * in URLs/Logs.
 */
class DomainProviderConnectionController extends Controller {
    public function index(): View {
        Gate::authorize('viewAny', DomainProviderConnection::class);

        $connections = DomainProviderConnection::query()
            ->withCount(['projections', 'resellerAccounts'])
            ->orderBy('name')
            ->get();

        return view('admin.domain-provider.index', [
            'connections' => $connections,
            'canManage' => Gate::allows('create', DomainProviderConnection::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', DomainProviderConnection::class);

        return view('admin.domain-provider._connect_dialog', [
            'connection' => null,
        ]);
    }

    public function store(Request $request, DomainConnectionService $service): RedirectResponse {
        Gate::authorize('create', DomainProviderConnection::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'environment' => ['required', 'string', 'in:ote,production'],
            'login' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:512'],
            'default_user' => ['nullable', 'string', 'max:190'],
        ]);

        $connection = DomainProviderConnection::create([
            'name' => $data['name'],
            'environment' => DomainProviderEnvironment::from($data['environment']),
            'endpoint' => 'domainreselling',
            'login' => $data['login'],
            'password' => $data['password'],
            'default_user' => $data['default_user'] ?? null,
            'status' => DomainConnectionStatus::Draft,
            'created_by_user_id' => $request->user()?->id,
        ]);

        $ok = $service->test($connection);

        return redirect()->route('admin.domain-provider.index')->with(
            $ok ? 'success' : 'error',
            $ok ? __('domain.flash.connected') : __('domain.flash.connection_failed'),
        );
    }

    public function test(DomainProviderConnection $connection, DomainConnectionService $service): RedirectResponse {
        Gate::authorize('update', $connection);

        $ok = $service->test($connection);

        return back()->with($ok ? 'success' : 'error', $ok ? __('domain.flash.connection_ok') : __('domain.flash.connection_failed'));
    }

    public function rotate(Request $request, DomainProviderConnection $connection, DomainConnectionService $service): RedirectResponse {
        Gate::authorize('update', $connection);

        $data = $request->validate([
            'login' => ['nullable', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:512'],
            'default_user' => ['nullable', 'string', 'max:190'],
        ]);

        $connection->forceFill([
            'login' => $data['login'] ?? $connection->login,
            'password' => $data['password'],
            'default_user' => $data['default_user'] ?? $connection->default_user,
        ])->save();

        $service->test($connection);

        return back()->with('success', __('domain.flash.credentials_rotated'));
    }

    /** Sync auslösen (Reseller/Domains/Kontakte). */
    public function sync(DomainProviderConnection $connection, DomainSyncService $service): RedirectResponse {
        Gate::authorize('update', $connection);

        if (! $connection->isRunnable()) {
            return back()->with('error', __('domain.flash.not_runnable'));
        }
        $service->syncAll($connection);

        return back()->with('success', __('domain.flash.synced'));
    }

    /** Bestätigt einen bestandenen realen Pilot (hebt „Pilot offen" auf). */
    public function confirmPilot(DomainProviderConnection $connection, DomainConnectionService $service): RedirectResponse {
        Gate::authorize('update', $connection);

        $service->confirmPilot($connection);

        return back()->with('success', __('domain.flash.pilot_confirmed'));
    }

    public function destroy(DomainProviderConnection $connection): RedirectResponse {
        Gate::authorize('delete', $connection);

        $connection->delete();

        return redirect()->route('admin.domain-provider.index')->with('success', __('domain.flash.disconnected'));
    }
}
