<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteSupportAssetController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\RemoteSupport\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{Asset, Organization};
use App\Plugins\RemoteSupport\Providers\{AnyDeskClient, TeamViewerClient};
use App\Plugins\RemoteSupport\{RemoteSupportConfig, RemoteSupportService};
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;

/**
 * Steuert die Fernwartungs-Aktionen auf der Asset-Detailseite: Geräte-IDs
 * setzen/entfernen und den manuellen Verbindungs-Import auslösen.
 */
class RemoteSupportAssetController extends Controller {
    private const PROVIDERS = [AnyDeskClient::ID, TeamViewerClient::ID];

    public function __construct(private readonly RemoteSupportService $service) {}

    public function saveId(Request $request, Asset $asset): RedirectResponse {
        Gate::authorize('update', $asset);

        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:' . implode(',', self::PROVIDERS)],
            'remote_id' => ['nullable', 'string', 'max:191'],
        ]);

        $this->service->setRemoteId($asset, $validated['provider'], (string) ($validated['remote_id'] ?? ''));

        return back()->with('status', __('Geräte-ID gespeichert.'));
    }

    public function forgetId(Asset $asset, string $provider): RedirectResponse {
        Gate::authorize('update', $asset);
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        $this->service->forgetRemoteId($asset, $provider);

        return back()->with('status', __('Geräte-ID entfernt.'));
    }

    public function sync(Asset $asset): RedirectResponse {
        Gate::authorize('update', $asset);

        $organization = $asset->organization;
        if (! $organization instanceof Organization) {
            return back()->with('error', __('Keine Organisation am Gerät hinterlegt.'));
        }

        $config = RemoteSupportConfig::resolve($organization->id);
        if (! $config['enabled'] || $this->service->providersFor($config) === []) {
            return back()->with('error', __('Fernwartung ist nicht konfiguriert.'));
        }

        $to = CarbonImmutable::now();
        $from = $to->subDays(max(1, (int) $config['sync_window_days']));
        $result = $this->service->import($organization, $config, $from, $to);

        return back()->with('status', __(':created neue, :skipped vorhandene, :unmatched ohne Gerät.', [
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'unmatched' => $result['unmatched'],
        ]));
    }
}
