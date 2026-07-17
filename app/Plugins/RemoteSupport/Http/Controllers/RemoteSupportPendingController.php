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
use App\Enums\Project\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\{Asset, Customer, Organization, Project, RemotePendingSession};
use App\Plugins\RemoteSupport\Providers\{AnyDeskClient, TeamViewerClient};
use App\Plugins\RemoteSupport\RemoteSupportService;
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use App\Services\Asset\AssetService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
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
    use ResolvesPluginOrgContext;

    private const PROVIDERS = [AnyDeskClient::ID, TeamViewerClient::ID];

    public function __construct(private readonly RemoteSupportService $service) {}

    public function index(): View {
        $admin = $this->admin();

        $organization = $admin->organization;
        $groups = $organization !== null ? $this->service->openPendingGroups($organization) : collect();
        $shared = $organization !== null ? $this->service->openSharedSessions($organization) : collect();

        // Nur fernwartbare Geräte (Arbeitsplatz/Server/Notebook) können eine ID tragen.
        $assets = Asset::query()
            ->whereIn('category_code', RemoteSupportService::REMOTE_CATEGORY_CODES)
            ->orderBy('name')
            ->get(['id', 'name', 'asset_no', 'customer_id', 'category_code']);

        $customers = Customer::query()->orderBy('name')->get(['id', 'name', 'company']);

        // Kunde (Sqid) → aktive Projekte (Sqid + Name) für die abhängige Projektauswahl.
        $projectMap = Project::query()
            ->where('status', ProjectStatus::Active->value)
            ->whereNotNull('customer_id')
            ->orderBy('name')
            ->get(['id', 'name', 'customer_id'])
            ->groupBy(fn (Project $p): int => (int) $p->customer_id)
            ->mapWithKeys(function ($projects, int $customerId): array {
                /** @var \Illuminate\Support\Collection<int, Project> $projects */
                $customer = Customer::query()->find($customerId);
                $key = $customer instanceof Customer ? $customer->sqid : (string) $customerId;

                return [$key => $projects->map(fn (Project $p): array => [
                    'id' => $p->sqid,
                    'name' => $p->name,
                ])->values()->all()];
            })
            ->all();

        $pool = (array) config('asset_categories', []);
        $categories = array_intersect_key($pool, array_flip(RemoteSupportService::REMOTE_CATEGORY_CODES));

        return view('remote-support::pending.index', [
            'groups' => $groups,
            'shared' => $shared,
            'assets' => $assets,
            'customers' => $customers,
            'projectMap' => $projectMap,
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

    /**
     * Bucht markierte Sitzungen eines Mehrkundengeräts auf einen Kunden (optional
     * konkretes Projekt). Mehrere Sitzungen werden per Checkbox gemeinsam zugewiesen.
     */
    public function assignShared(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $customerId = Sqid::decodeOrNumeric(Customer::class, $request->input('customer_id'));
        $projectId = Sqid::decodeOrNumeric(Project::class, $request->input('project_id'));

        $request->merge(['customer_id' => $customerId, 'project_id' => $projectId]);

        $validated = $request->validate([
            'customer_id' => ['required', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'pending_ids' => ['required', 'array', 'min:1'],
            'pending_ids.*' => ['string'],
        ]);

        $customer = Customer::query()->whereKey($validated['customer_id'])->firstOrFail();

        $project = null;
        if (($validated['project_id'] ?? null) !== null) {
            $project = Project::query()
                ->whereKey($validated['project_id'])
                ->where('customer_id', $customer->id)
                ->first();
            abort_if($project === null, 422, 'Projekt gehört nicht zum gewählten Kunden.');
        }

        $rows = $this->pendingRowsFromInput($organization, $validated['pending_ids']);
        if ($rows->isEmpty()) {
            return back()->with('error', __('Keine gültigen Sitzungen ausgewählt.'));
        }

        $result = $this->service->assignSharedSessions($organization, $rows, $customer, $project);

        return back()->with('status', $this->resultMessage($result));
    }

    /** Verwirft markierte Sitzungen eines Mehrkundengeräts. */
    public function dismissSession(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $validated = $request->validate([
            'pending_ids' => ['required', 'array', 'min:1'],
            'pending_ids.*' => ['string'],
        ]);

        $rows = $this->pendingRowsFromInput($organization, $validated['pending_ids']);
        $count = $this->service->dismissSessions($rows);

        return back()->with('status', __(':count Sitzung(en) verworfen.', ['count' => $count]));
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

    /**
     * Lädt offene Pending-Sitzungen eines Mehrkundengeräts aus den übergebenen
     * Sqids (org-scoped, nur asset-gebundene offene Sitzungen).
     *
     * @param  array<int, string>  $pendingIds
     * @return \Illuminate\Support\Collection<int, RemotePendingSession>
     */
    private function pendingRowsFromInput(Organization $organization, array $pendingIds): \Illuminate\Support\Collection {
        $ids = collect($pendingIds)
            ->map(fn (string $v): ?int => Sqid::decodeOrNumeric(RemotePendingSession::class, $v))
            ->filter(fn (?int $id): bool => $id !== null)
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return RemotePendingSession::query()
            ->where('organization_id', $organization->id)
            ->where('status', RemotePendingSession::STATUS_OPEN)
            ->whereNotNull('asset_id')
            ->whereIn('id', $ids)
            ->with('asset')
            ->get();
    }

    /** @param array{created: int, skipped: int} $result */
    private function resultMessage(array $result): string {
        return (string) __(':created gebucht, :skipped bereits vorhanden.', [
            'created' => $result['created'],
            'skipped' => $result['skipped'],
        ]);
    }
}
