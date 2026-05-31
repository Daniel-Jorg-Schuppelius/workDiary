<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteSupportPendingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\RemoteSupport\Http\Controllers;

use App\Enums\Asset\{AssetClass, AssetOwnership};
use App\Http\Controllers\Controller;
use App\Models\{Asset, Customer, Organization, User};
use App\Plugins\RemoteSupport\Providers\{AnyDeskClient, TeamViewerClient};
use App\Plugins\RemoteSupport\RemoteSupportService;
use App\Services\Asset\AssetService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Admin-Inbox für Fernwartungs-Verbindungen, deren Geräte-ID noch keinem Asset
 * zugeordnet ist. Pro unbekannter ID kann der Admin:
 *  - sie einem bestehenden Gerät zuweisen,
 *  - ein neues Gerät anlegen und zuweisen,
 *  - die Gruppe verwerfen.
 * Beim Zuweisen werden die gespeicherten Sessions sofort als Zeiteinträge gebucht.
 */
class RemoteSupportPendingController extends Controller {
    private const PROVIDERS = [AnyDeskClient::ID, TeamViewerClient::ID];

    public function __construct(private readonly RemoteSupportService $service) {}

    public function index(): View {
        $admin = $this->admin();

        $organization = $admin->organization;
        $groups = $organization !== null ? $this->service->openPendingGroups($organization) : collect();

        // Nur fernwartbare Geräte (Arbeitsplatz/Server/Notebook) können eine ID tragen.
        $assets = Asset::query()
            ->whereIn('category_code', RemoteSupportService::REMOTE_CATEGORY_CODES)
            ->orderBy('name')
            ->get(['id', 'name', 'asset_no', 'customer_id', 'category_code']);

        $customers = Customer::query()->orderBy('name')->get(['id', 'name', 'company']);

        $pool = (array) config('asset_categories', []);
        $categories = array_intersect_key($pool, array_flip(RemoteSupportService::REMOTE_CATEGORY_CODES));

        return view('remote-support::pending.index', [
            'groups' => $groups,
            'assets' => $assets,
            'customers' => $customers,
            'categories' => $categories,
        ]);
    }

    public function assignExisting(Request $request): RedirectResponse {
        $admin = $this->admin();

        $rawAssetId = $request->input('asset_id');
        $assetId = Sqid::decode(Asset::class, $rawAssetId);
        if ($assetId === null && is_numeric($rawAssetId)) {
            $assetId = (int) $rawAssetId;
        }

        $request->merge([
            'asset_id' => $assetId,
        ]);

        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:' . implode(',', self::PROVIDERS)],
            'remote_id' => ['required', 'string', 'max:191'],
            'asset_id' => ['required', 'integer'],
        ]);

        $asset = Asset::query()->whereKey($validated['asset_id'])->firstOrFail();

        $result = $this->service->assignPending(
            $this->organization($admin),
            $validated['provider'],
            $validated['remote_id'],
            $asset,
        );

        return back()->with('status', $this->resultMessage($result));
    }

    public function assignNew(Request $request, AssetService $assets): RedirectResponse {
        $admin = $this->admin();

        $rawCustomerId = $request->input('customer_id');
        $customerId = Sqid::decode(Customer::class, $rawCustomerId);
        if ($customerId === null && is_numeric($rawCustomerId)) {
            $customerId = (int) $rawCustomerId;
        }

        $request->merge([
            'customer_id' => $customerId,
        ]);

        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:' . implode(',', self::PROVIDERS)],
            'remote_id' => ['required', 'string', 'max:191'],
            'name' => ['required', 'string', 'max:191'],
            'customer_id' => ['required', 'integer'],
            'category_code' => ['required', 'string', 'in:' . implode(',', RemoteSupportService::REMOTE_CATEGORY_CODES)],
        ]);

        $asset = $assets->create($admin, [
            'asset_class' => AssetClass::Device->value,
            'category_code' => $validated['category_code'],
            'name' => $validated['name'],
            'owned_by' => AssetOwnership::Customer->value,
            'customer_id' => $validated['customer_id'],
        ]);

        $result = $this->service->assignPending(
            $this->organization($admin),
            $validated['provider'],
            $validated['remote_id'],
            $asset,
        );

        return back()->with('status', __('Gerät „:name" angelegt. ', ['name' => $asset->name]) . $this->resultMessage($result));
    }

    public function dismiss(Request $request): RedirectResponse {
        $admin = $this->admin();

        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:' . implode(',', self::PROVIDERS)],
            'remote_id' => ['required', 'string', 'max:191'],
        ]);

        $count = $this->service->dismissPending($this->organization($admin), $validated['provider'], $validated['remote_id']);

        return back()->with('status', __(':count Verbindung(en) verworfen.', ['count' => $count]));
    }

    /** @param array{created: int, skipped: int} $result */
    private function resultMessage(array $result): string {
        return (string) __(':created gebucht, :skipped bereits vorhanden.', [
            'created' => $result['created'],
            'skipped' => $result['skipped'],
        ]);
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
