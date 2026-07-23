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
use App\Models\{Asset, Customer, ForeignCustomer, Organization, Project, RemotePendingSession};
use App\Plugins\RemoteSupport\Providers\{AnyDeskClient, TeamViewerClient};
use App\Plugins\RemoteSupport\{RemoteSupportService, RemoteSupportSuggestionService};
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use App\Services\Asset\AssetService;
use App\Support\{Setting, Sqid};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/**
 * Admin-Inbox für Fernwartungs-Verbindungen, deren Geräte-ID noch keinem Asset
 * zugeordnet ist. Pro unbekannter ID kann der Admin:
 *  - sie einem bestehenden Gerät zuweisen,
 *  - ein neues Gerät anlegen und zuweisen (ohne Kunde = eigenes Firmengerät),
 *  - die Gruppe verwerfen.
 * Beim Zuweisen werden die gespeicherten Sessions sofort als Zeiteinträge
 * gebucht — Kundengeräte aufs Kunden-/Endkundenprojekt, eigene Geräte ohne
 * Kunden aufs interne Wartungsprojekt. Nur Mehrkundengeräte (shared_remote)
 * bleiben offen und werden im Reiter „Sitzungen zuordnen" je Kunde gebucht.
 */
class RemoteSupportPendingController extends Controller {
    use ResolvesPluginOrgContext;

    private const PROVIDERS = [AnyDeskClient::ID, TeamViewerClient::ID];

    public function __construct(
        private readonly RemoteSupportService $service,
        private readonly RemoteSupportSuggestionService $suggester,
    ) {}

    public function index(Request $request): View {
        $admin = $this->admin();

        $q = trim((string) $request->query('q', ''));
        $search = $q !== '' ? $q : null;

        $organization = $admin->organization;
        $groupsAll = $organization !== null ? $this->service->openPendingGroups($organization, $search) : collect();
        $sharedAll = $organization !== null ? $this->service->openSharedSessions($organization, $search) : collect();
        $sharedSessionCount = (int) $sharedAll->sum(fn (object $d): int => $d->sessions->count());

        $groups = $this->paginateGroups($groupsAll, (int) Setting::get('pagination.remote_pending_groups', 10), 'ids_page', $request);
        $shared = $this->paginateGroups($sharedAll, (int) Setting::get('pagination.remote_shared_devices', 8), 'sessions_page', $request);
        $sharedSessionLimit = max(1, (int) Setting::get('pagination.remote_shared_sessions', 30));

        // Zuweisungsvorschläge nur für die sichtbare Seite berechnen (Überlappung
        // mit erfassten Zeiten + Alias-Abgleich); befüllen ausschließlich vor.
        $suggestions = [];
        $sessionSuggestions = [];
        if ($organization !== null) {
            $suggestions = $this->suggester->suggestForGroups($organization, $groups->items());
            $sessionSuggestions = $this->suggester->suggestForSharedSessions(
                $organization,
                collect($shared->items())->map(fn (object $d): object => (object) [
                    'asset' => $d->asset,
                    'sessions' => $d->sessions->take($sharedSessionLimit),
                ]),
            );
        }

        // Nur fernwartbare Geräte (Arbeitsplatz/Server/Notebook) können eine ID tragen.
        $assets = Asset::query()
            ->whereIn('category_code', RemoteSupportService::REMOTE_CATEGORY_CODES)
            ->orderBy('name')
            ->get(['id', 'name', 'asset_no', 'customer_id', 'category_code']);

        $customers = Customer::query()->orderBy('name')->get(['id', 'name', 'company']);
        $customerSqids = $customers->mapWithKeys(fn (Customer $c): array => [(int) $c->id => $c->sqid])->all();

        // Fremdkunden (Endkunden) je Kunde für die abhängige Auswahl; die
        // Sqid-Lookup-Tabelle bindet Projekte an ihren Endkunden.
        $foreignCustomers = ForeignCustomer::query()->orderBy('name')->get(['id', 'name', 'customer_id']);
        $foreignSqids = $foreignCustomers->mapWithKeys(fn (ForeignCustomer $f): array => [(int) $f->id => $f->sqid])->all();
        $foreignMap = $foreignCustomers
            ->groupBy(fn (ForeignCustomer $f): int => (int) $f->customer_id)
            ->mapWithKeys(fn ($items, int $customerId): array => [
                $customerSqids[$customerId] ?? (string) $customerId => $items->map(fn (ForeignCustomer $f): array => [
                    'id' => $f->sqid,
                    'name' => $f->name,
                ])->values()->all(),
            ])
            ->all();

        // Kunde (Sqid) → aktive Projekte (Sqid + Name + Endkunden-Bindung) für
        // die abhängige Projektauswahl.
        $projectMap = Project::query()
            ->where('status', ProjectStatus::Active->value)
            ->whereNotNull('customer_id')
            ->orderBy('name')
            ->get(['id', 'name', 'customer_id', 'foreign_customer_id'])
            ->groupBy(fn (Project $p): int => (int) $p->customer_id)
            ->mapWithKeys(function ($projects, int $customerId) use ($customerSqids, $foreignSqids): array {
                /** @var \Illuminate\Support\Collection<int, Project> $projects */
                $key = $customerSqids[$customerId] ?? (string) $customerId;

                return [$key => $projects->map(fn (Project $p): array => [
                    'id' => $p->sqid,
                    'name' => $p->name,
                    'fc' => $p->foreign_customer_id !== null ? ($foreignSqids[(int) $p->foreign_customer_id] ?? null) : null,
                ])->values()->all()];
            })
            ->all();

        $pool = (array) config('asset_categories', []);
        $categories = array_intersect_key($pool, array_flip(RemoteSupportService::REMOTE_CATEGORY_CODES));

        return view('remote-support::pending.index', [
            'groups' => $groups,
            'shared' => $shared,
            'sharedSessionCount' => $sharedSessionCount,
            'sharedSessionLimit' => $sharedSessionLimit,
            'q' => $q,
            'assets' => $assets,
            'customers' => $customers,
            'projectMap' => $projectMap,
            'foreignMap' => $foreignMap,
            'categories' => $categories,
            'suggestions' => $suggestions,
            'sessionSuggestions' => $sessionSuggestions,
        ]);
    }

    /**
     * Paginiert die in PHP aggregierten Inbox-Gruppen (eigener Seitenname je
     * Tab, damit beide Reiter unabhängig blättern).
     *
     * @template TItem of object
     *
     * @param  \Illuminate\Support\Collection<int, TItem>  $items
     * @return LengthAwarePaginator<int, TItem>
     */
    private function paginateGroups(\Illuminate\Support\Collection $items, int $perPage, string $pageName, Request $request): LengthAwarePaginator {
        $perPage = max(1, $perPage);
        $page = max(1, (int) $request->query($pageName, '1'));

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'pageName' => $pageName],
        );
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
            'shared_remote' => ['sometimes', 'boolean'],
            'matchcode' => ['nullable', 'string', 'max:16'],
        ]);

        $asset = Asset::query()->whereKey($validated['asset_id'])->firstOrFail();

        if ($request->boolean('shared_remote') && ! $asset->shared_remote) {
            $asset->update(['shared_remote' => true]);
        }

        $this->persistMatchcode($validated['matchcode'] ?? null, $asset->customer);

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
            'foreign_customer_id' => Sqid::decodeOrNumeric(ForeignCustomer::class, $request->input('foreign_customer_id')),
        ]);

        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:' . implode(',', self::PROVIDERS)],
            'remote_id' => ['required', 'string', 'max:191'],
            'name' => ['required', 'string', 'max:191'],
            'customer_id' => ['nullable', 'integer'],
            'foreign_customer_id' => ['nullable', 'integer'],
            'category_code' => ['required', 'string', 'in:' . implode(',', RemoteSupportService::REMOTE_CATEGORY_CODES)],
            'shared_remote' => ['sometimes', 'boolean'],
            'matchcode' => ['nullable', 'string', 'max:16'],
        ]);

        // Ohne Kunde ist es ein eigenes Firmengerät (owned_by=org, Sitzungen
        // je Kunde einzeln zuordnen); mit Kunde ein Kundengerät, optional beim
        // Fremdkunden (Endkunden) angesiedelt und/oder als Mehrkundengerät
        // markiert (Selbstständige für mehrere Firmen).
        $customerId = $validated['customer_id'] ?? null;
        $foreignCustomer = $this->foreignCustomerForInput($customerId, $validated['foreign_customer_id'] ?? null);

        $asset = $assets->create($admin, [
            'asset_class' => AssetClass::Device->value,
            'category_code' => $validated['category_code'],
            'name' => $validated['name'],
            'owned_by' => $customerId !== null ? AssetOwnership::Customer->value : AssetOwnership::Organization->value,
            'customer_id' => $customerId,
            'foreign_customer_id' => $foreignCustomer?->id,
        ]);

        if ($request->boolean('shared_remote')) {
            $asset->update(['shared_remote' => true]);
        }

        $this->persistMatchcode($validated['matchcode'] ?? null, $asset->customer);

        $result = $this->service->assignPending(
            $this->organization($admin),
            $validated['provider'],
            $validated['remote_id'],
            $asset,
        );

        return back()->with('status', __('Gerät „:name" angelegt. ', ['name' => $asset->name]) . $this->resultMessage($result));
    }

    /**
     * Hinterlegt das per Vorschlag übernommene Kürzel am Kunden — nur wenn der
     * Kunde noch keins hat und das Kürzel org-weit noch frei ist (stille
     * Kollision statt Fehler: die Zuweisung selbst darf nicht scheitern).
     */
    private function persistMatchcode(?string $matchcode, ?Customer $customer): void {
        $matchcode = trim((string) $matchcode);
        if ($matchcode === '' || $customer === null || $customer->matchcode !== null) {
            return;
        }

        $taken = Customer::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $customer->organization_id)
            ->where('matchcode', $matchcode)
            ->exists();

        if (! $taken) {
            $customer->update(['matchcode' => $matchcode]);
        }
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
        $foreignId = Sqid::decodeOrNumeric(ForeignCustomer::class, $request->input('foreign_customer_id'));

        $request->merge(['customer_id' => $customerId, 'project_id' => $projectId, 'foreign_customer_id' => $foreignId]);

        $validated = $request->validate([
            'customer_id' => ['required', 'integer'],
            'foreign_customer_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'pending_ids' => ['required', 'array', 'min:1'],
            'pending_ids.*' => ['string'],
        ]);

        $customer = Customer::query()->whereKey($validated['customer_id'])->firstOrFail();
        $foreignCustomer = $this->foreignCustomerForInput($customer->id, $validated['foreign_customer_id'] ?? null);

        $project = null;
        if (($validated['project_id'] ?? null) !== null) {
            $project = Project::query()
                ->whereKey($validated['project_id'])
                ->where('customer_id', $customer->id)
                ->first();
            abort_if($project === null, 422, 'Projekt gehört nicht zum gewählten Kunden.');

            // Endkunden-Trennung wie in der Integrations-Inbox: das Projekt
            // muss zur gewählten Fremdkunden-Ebene passen.
            if ($foreignCustomer !== null) {
                abort_unless((int) $project->foreign_customer_id === (int) $foreignCustomer->id, 422, __('Das gewählte Projekt gehört nicht zum gewählten Fremdkunden.'));
            } else {
                abort_unless($project->foreign_customer_id === null, 422, __('Das gewählte Projekt gehört zu einem Endkunden — bitte den passenden Fremdkunden auswählen.'));
            }
        }

        $rows = $this->pendingRowsFromInput($organization, $validated['pending_ids']);
        if ($rows->isEmpty()) {
            return back()->with('error', __('Keine gültigen Sitzungen ausgewählt.'));
        }

        $result = $this->service->assignSharedSessions($organization, $rows, $customer, $project, foreignCustomer: $foreignCustomer);

        return back()->with('status', $this->resultMessage($result));
    }

    /**
     * Bucht markierte Sitzungen eines Mehrkundengeräts ohne Kunden auf das
     * interne Wartungsprojekt (eigene Firma).
     */
    public function assignSharedInternal(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $validated = $request->validate([
            'pending_ids' => ['required', 'array', 'min:1'],
            'pending_ids.*' => ['string'],
        ]);

        $rows = $this->pendingRowsFromInput($organization, $validated['pending_ids']);
        if ($rows->isEmpty()) {
            return back()->with('error', __('Keine gültigen Sitzungen ausgewählt.'));
        }

        $result = $this->service->assignSharedSessions($organization, $rows);

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
     * Löst den optionalen Fremdkunden (Endkunden) auf: nur mit Kunde erlaubt,
     * Zugehörigkeit wird erzwungen (Regel wie in der Integrations-Inbox).
     */
    private function foreignCustomerForInput(?int $customerId, ?int $foreignCustomerId): ?ForeignCustomer {
        if ($foreignCustomerId === null) {
            return null;
        }

        abort_if($customerId === null, 422, __('Der gewählte Fremdkunde gehört nicht zum gewählten Kunden.'));

        $foreign = ForeignCustomer::query()
            ->whereKey($foreignCustomerId)
            ->where('customer_id', $customerId)
            ->first();
        abort_if($foreign === null, 422, __('Der gewählte Fremdkunde gehört nicht zum gewählten Kunden.'));

        return $foreign;
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

    /** @param array{created: int, skipped: int, linked?: int, pending?: int} $result */
    private function resultMessage(array $result): string {
        $message = (string) __(':created gebucht, :linked mit vorhandenen Zeiten verknüpft, :skipped bereits vorhanden.', [
            'created' => $result['created'],
            'linked' => $result['linked'] ?? 0,
            'skipped' => $result['skipped'],
        ]);

        if (($result['pending'] ?? 0) > 0) {
            $message .= ' ' . trans_choice(
                ':count Sitzung wartet auf die Kundenzuordnung.|:count Sitzungen warten auf die Kundenzuordnung.',
                (int) $result['pending'],
                ['count' => $result['pending']],
            );
        }

        return $message;
    }
}
