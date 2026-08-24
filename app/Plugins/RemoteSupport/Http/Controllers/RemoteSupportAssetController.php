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
use App\Plugins\RemoteSupport\{RemoteDeviceRegistry, RemoteSessionImporter, RemoteSupportConfig};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;

/**
 * Steuert die Fernwartungs-Aktionen auf der Asset-Detailseite: Geräte-IDs
 * setzen/entfernen und den manuellen Verbindungs-Import auslösen.
 */
class RemoteSupportAssetController extends Controller {
    private const PROVIDERS = [AnyDeskClient::ID, TeamViewerClient::ID];

    public function __construct(
        private readonly RemoteDeviceRegistry $devices,
        private readonly RemoteSessionImporter $importer,
    ) {}

    public function saveId(Request $request, Asset $asset): RedirectResponse {
        Gate::authorize('update', $asset);

        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:' . implode(',', self::PROVIDERS)],
            'remote_id' => ['required', 'string', 'max:191'],
        ]);

        $this->devices->setRemoteId($asset, $validated['provider'], $validated['remote_id']);

        return back()->with('status', __('Geräte-ID gespeichert.'));
    }

    public function forgetId(Request $request, Asset $asset, string $provider): RedirectResponse {
        Gate::authorize('update', $asset);
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        $remoteId = trim((string) $request->input('remote_id', ''));
        $this->devices->forgetRemoteId($asset, $provider, $remoteId !== '' ? $remoteId : null);

        return back()->with('status', __('Geräte-ID entfernt.'));
    }

    /**
     * Überführt IDs + Pending-Sitzungen dieses (Duplikat-)Geräts auf ein
     * Zielgerät; das leere Duplikat kann der Admin danach archivieren.
     */
    public function merge(Request $request, Asset $asset): RedirectResponse {
        Gate::authorize('update', $asset);

        $request->merge([
            'target_asset_id' => Sqid::decodeOrNumeric(Asset::class, $request->input('target_asset_id')),
        ]);
        $validated = $request->validate([
            'target_asset_id' => ['required', 'integer'],
        ]);

        $target = Asset::query()->whereKey($validated['target_asset_id'])->firstOrFail();
        Gate::authorize('update', $target);
        abort_if($target->id === $asset->id, 422, __('Quell- und Zielgerät sind identisch.'));

        $result = $this->devices->mergeRemoteDevice($asset, $target);

        return redirect()
            ->route('assets.show', $target)
            ->with('status', __(':ids Geräte-ID(s) und :sessions Sitzung(en) an „:name" übertragen. Das leere Duplikat kann jetzt archiviert werden.', [
                'ids' => $result['ids'],
                'sessions' => $result['sessions'],
                'name' => $target->name ?: $target->asset_no,
            ]));
    }

    /**
     * Schaltet die Mehrkundengerät-Markierung am Asset um. Sitzungen solcher
     * Geräte werden nicht automatisch gebucht, sondern in der Inbox je Sitzung
     * einem Kunden zugeordnet.
     */
    public function toggleShared(Request $request, Asset $asset): RedirectResponse {
        Gate::authorize('update', $asset);

        $asset->update(['shared_remote' => $request->boolean('shared_remote')]);

        return back()->with('status', $asset->shared_remote
            ? __('Gerät als Mehrkundengerät markiert. Sitzungen werden künftig je Sitzung zugeordnet.')
            : __('Mehrkundengerät-Markierung entfernt.'));
    }

    public function sync(Asset $asset): RedirectResponse {
        Gate::authorize('update', $asset);

        $organization = $asset->organization;
        if (! $organization instanceof Organization) {
            return back()->with('error', __('Keine Organisation am Gerät hinterlegt.'));
        }

        $config = RemoteSupportConfig::resolve($organization->id);
        if (! $config['enabled'] || $this->importer->providersFor($config) === []) {
            return back()->with('error', __('Fernwartung ist nicht konfiguriert.'));
        }

        $to = CarbonImmutable::now();
        $from = $to->subDays(max(1, (int) $config['sync_window_days']));
        $result = $this->importer->import($organization, $config, $from, $to);

        return back()->with('status', __(':created neue, :linked mit vorhandenen Zeiten verknüpft, :skipped vorhandene, :unmatched ohne Gerät, :pending zur Zuordnung.', [
            'created' => $result['created'],
            'linked' => $result['linked'],
            'skipped' => $result['skipped'],
            'unmatched' => $result['unmatched'],
            'pending' => $result['pending'],
        ]));
    }
}
