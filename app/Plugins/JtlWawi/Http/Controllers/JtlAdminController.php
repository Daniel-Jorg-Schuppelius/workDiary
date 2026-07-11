<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\JtlWawi\Http\Controllers;

use App\Enums\Inventory\InventoryMode;
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, IntegrationInboxItem, JtlConnection, JtlWarehouseMapping, Organization, User, Warehouse};
use App\Plugins\JtlWawi\Api\JtlUrlGuard;
use App\Plugins\JtlWawi\JtlWawiPlugin;
use App\Plugins\JtlWawi\Services\{JtlRegistrationService, JtlScopePreflight, JtlSyncService, JtlTakeoverService};
use App\Services\Inventory\InventoryProviderResolver;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/**
 * JTL-Wawi-Admin (Feature 078, MVP-323/324): geführter Verbindungsdialog
 * beider Betriebsarten, Registrierungs-Wartezustand, Lager-Zuordnung,
 * Sync-Protokoll und der Bestandsführungs-Moduswechsel.
 *
 * Admin-Gate wie Todoist/Zammad (isAdmin + Org-Kontext); der Moduswechsel
 * verlangt zusätzlich `inventory.configure`. Secrets werden nie
 * zurückgegeben (write-only Felder, `$hidden` + encrypted am Modell).
 */
class JtlAdminController extends Controller {
    public function index(Request $request, JtlScopePreflight $preflight, InventoryProviderResolver $resolver): View {
        [$user, $organization] = $this->authorizeAdmin($request);

        $connection = JtlConnection::query()->where('organization_id', $organization->id)->first();
        $warehouseMappings = JtlWarehouseMapping::query()
            ->where('organization_id', $organization->id)
            ->orderBy('name')
            ->get();
        $warehouses = Warehouse::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $openInbox = IntegrationInboxItem::query()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', JtlWawiPlugin::ID)
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->count();
        $linkedArticles = \App\Models\ExternalArticleMapping::query()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', JtlWawiPlugin::ID)
            ->whereNotNull('article_variant_id')
            ->count();

        return view(JtlWawiPlugin::ID . '::admin.index', [
            'connection' => $connection,
            'warehouseMappings' => $warehouseMappings,
            'warehouses' => $warehouses,
            'openInbox' => $openInbox,
            'linkedArticles' => $linkedArticles,
            'inventoryMode' => $resolver->modeFor($organization),
            'scopeCheck' => $connection !== null ? $preflight->check($connection) : null,
            'canConfigureInventory' => $user->can(Permission::InventoryConfigure->value),
        ]);
    }

    /** Verbindung anlegen/aktualisieren (beide Betriebsarten). */
    public function storeConnection(Request $request, JtlRegistrationService $registration): RedirectResponse {
        [$user, $organization] = $this->authorizeAdmin($request);

        $data = $request->validate([
            'mode' => ['required', Rule::in([JtlConnection::MODE_ON_PREMISE, JtlConnection::MODE_CLOUD])],
            'base_url' => ['nullable', 'string', 'max:255', 'required_if:mode,' . JtlConnection::MODE_ON_PREMISE],
            'api_version' => ['required', Rule::in(['2.0', '2.1'])],
            'allow_private_network' => ['nullable', 'boolean'],
            'tenant_id' => ['nullable', 'string', 'max:64', 'required_if:mode,' . JtlConnection::MODE_CLOUD],
            'company_id' => ['nullable', 'string', 'max:64'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'],
        ]);

        $allowPrivate = (bool) ($data['allow_private_network'] ?? false);

        if ($data['mode'] === JtlConnection::MODE_ON_PREMISE) {
            try {
                JtlUrlGuard::assertAcceptable((string) $data['base_url'], $allowPrivate);
            } catch (RuntimeException $e) {
                return back()->withInput()->with('error', $e->getMessage());
            }
        }

        $connection = JtlConnection::query()->firstOrNew(['organization_id' => $organization->id]);
        $isNew = ! $connection->exists;

        $connection->fill([
            'mode' => $data['mode'],
            'base_url' => $data['base_url'] ?? null,
            'api_version' => $data['api_version'],
            'allow_private_network' => $allowPrivate,
            'tenant_id' => $data['tenant_id'] ?? null,
            'company_id' => $data['company_id'] ?? null,
            'app_id' => (string) config('plugins.' . JtlWawiPlugin::ID . '.app_id'),
        ]);

        // Write-only Secrets: leer gelassen = unverändert.
        if (trim((string) ($data['client_id'] ?? '')) !== '') {
            $connection->client_id = (string) $data['client_id'];
        }
        if (trim((string) ($data['client_secret'] ?? '')) !== '') {
            $connection->client_secret = (string) $data['client_secret'];
        }

        if ($isNew) {
            $connection->status = JtlConnection::STATUS_DRAFT;
            $connection->connectedBy()->associate($user);
            $connection->connected_at = now();
        }
        $connection->save();

        if ($connection->mode === JtlConnection::MODE_CLOUD && $connection->hasCredentials()) {
            // Cloud: Credentials sofort verproben; Scope-Preflight folgt
            // aus der Token-Antwort bzw. bleibt „unbekannt“ (Healthcheck-Probe).
            try {
                app(\App\Plugins\JtlWawi\Api\JtlCloudTokenService::class)->ensureToken($connection);
                $registration->applyScopePreflight($connection);

                return back()->with('success', __('jtl_wawi.flash.cloud_connected'));
            } catch (Throwable $e) {
                $connection->forceFill(['last_error' => mb_substr(class_basename($e), 0, 191)])->save();

                return back()->with('error', __('jtl_wawi.flash.cloud_failed'));
            }
        }

        return back()->with('success', __('jtl_wawi.flash.saved'));
    }

    /** OnPremise: App-Registrierung anstoßen (Schritt „in Wawi bestätigen“). */
    public function startRegistration(Request $request, JtlRegistrationService $registration): RedirectResponse {
        [, $organization] = $this->authorizeAdmin($request);
        $connection = $this->requireConnection($organization);

        try {
            $registration->start($connection);
        } catch (Throwable $e) {
            return back()->with('error', $e instanceof RuntimeException ? $e->getMessage() : __('jtl_wawi.flash.registration_failed'));
        }

        return back()->with('success', __('jtl_wawi.flash.registration_started'));
    }

    /** OnPremise: Registrierungsstatus prüfen und ggf. API-Key übernehmen. */
    public function checkRegistration(Request $request, JtlRegistrationService $registration): RedirectResponse {
        [, $organization] = $this->authorizeAdmin($request);
        $connection = $this->requireConnection($organization);

        try {
            $status = $registration->check($connection);
        } catch (Throwable $e) {
            return back()->with('error', $e instanceof RuntimeException ? $e->getMessage() : __('jtl_wawi.flash.registration_failed'));
        }

        return match ($status) {
            JtlConnection::REGISTRATION_ACCEPTED => back()->with('success', __('jtl_wawi.flash.registration_accepted')),
            JtlConnection::REGISTRATION_REJECTED => back()->with('error', __('jtl_wawi.flash.registration_rejected')),
            default => back()->with('success', __('jtl_wawi.flash.registration_pending')),
        };
    }

    /** Trennung: nur bei lokaler Bestandsführung; Secrets werden genullt, Projektionen bleiben. */
    public function disconnect(Request $request, InventoryProviderResolver $resolver): RedirectResponse {
        [, $organization] = $this->authorizeAdmin($request);
        $connection = $this->requireConnection($organization);

        if ($resolver->modeFor($organization) !== InventoryMode::Local) {
            return back()->with('error', __('jtl_wawi.flash.disconnect_blocked'));
        }

        $connection->forceFill([
            'api_key' => null,
            'client_id' => null,
            'client_secret' => null,
            'access_token' => null,
            'token_expires_at' => null,
            'challenge_code' => null,
            'registration_id' => null,
            'registration_status' => null,
            'granted_scopes' => null,
            'status' => JtlConnection::STATUS_DISCONNECTED,
            'blocked_reason' => null,
        ])->save();

        return back()->with('success', __('jtl_wawi.flash.disconnected'));
    }

    /** „Jetzt synchronisieren“ — derselbe Pfad wie der Scheduler-Lauf. */
    public function syncNow(Request $request, JtlSyncService $sync): RedirectResponse {
        [, $organization] = $this->authorizeAdmin($request);
        $connection = $this->requireConnection($organization);

        if (! $connection->isActive()) {
            return back()->with('error', __('jtl_wawi.flash.not_active'));
        }

        try {
            $sync->run($connection);
        } catch (Throwable $e) {
            return back()->with('error', __('jtl_wawi.flash.sync_failed', ['reason' => class_basename($e)]));
        }

        return back()->with('success', __('jtl_wawi.flash.sync_done'));
    }

    /** JTL-Lager ↔ WorkDiary-Lager zuordnen (leer = Zuordnung lösen). */
    public function mapWarehouse(Request $request, JtlWarehouseMapping $mapping): RedirectResponse {
        [, $organization] = $this->authorizeAdmin($request);
        abort_unless((int) $mapping->organization_id === (int) $organization->id, 404);

        $sqid = trim((string) $request->input('warehouse', ''));
        $warehouseId = null;

        if ($sqid !== '') {
            $warehouse = (new Warehouse())->resolveRouteBinding($sqid);
            abort_unless($warehouse instanceof Warehouse && (int) $warehouse->organization_id === (int) $organization->id, 404);
            $warehouseId = $warehouse->id;
        }

        $mapping->forceFill(['warehouse_id' => $warehouseId])->save();

        return back()->with('success', __('jtl_wawi.flash.warehouse_mapped'));
    }

    /** Bestandsführungs-Modus wechseln (MVP-324) — verlangt inventory.configure. */
    public function updateMode(Request $request, InventoryProviderResolver $resolver, JtlTakeoverService $takeover): RedirectResponse {
        [$user, $organization] = $this->authorizeAdmin($request);
        abort_unless($user->can(Permission::InventoryConfigure->value), 403);

        $data = $request->validate([
            'inventory_mode' => ['required', Rule::in(array_map(static fn (InventoryMode $mode): string => $mode->value, InventoryMode::cases()))],
        ]);
        $targetMode = InventoryMode::from($data['inventory_mode']);
        $currentMode = $resolver->modeFor($organization);

        if ($targetMode === $currentMode) {
            return back()->with('success', __('jtl_wawi.flash.mode_unchanged'));
        }

        if ($targetMode !== InventoryMode::Local) {
            $connection = JtlConnection::query()->where('organization_id', $organization->id)->first();
            if (! $connection instanceof JtlConnection || ! $connection->isActive()) {
                return back()->with('error', __('jtl_wawi.flash.mode_needs_connection'));
            }
            $mappedWarehouses = JtlWarehouseMapping::query()
                ->where('organization_id', $organization->id)
                ->whereNotNull('warehouse_id')
                ->count();
            if ($mappedWarehouses === 0) {
                return back()->with('error', __('jtl_wawi.flash.mode_needs_mapping'));
            }
        }

        $settings = (array) ($organization->settings ?? []);
        $settings['inventory_mode'] = $targetMode->value;
        $settings['inventory_plugin_id'] = $targetMode === InventoryMode::Local ? null : JtlWawiPlugin::ID;
        $organization->forceFill(['settings' => $settings])->save();

        AuditLog::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'event' => 'inventory.mode_changed',
            'auditable_type' => $organization->getMorphClass(),
            'auditable_id' => $organization->getKey(),
            'changes' => [
                'from' => $currentMode->value,
                'to' => $targetMode->value,
                'plugin_id' => $settings['inventory_plugin_id'],
            ],
        ]);

        // Wechsel zurück auf lokal: JTL-Bestände als Eröffnungs-Korrekturen
        // übernehmen (idempotent je Tag; nie zurückgespiegelt).
        if ($targetMode === InventoryMode::Local && $currentMode !== InventoryMode::Local) {
            try {
                $result = $takeover->importOpeningStock($organization, $user->id);

                return back()->with('success', __('jtl_wawi.flash.mode_changed_with_takeover', ['booked' => $result['booked']]));
            } catch (Throwable $e) {
                return back()->with('error', __('jtl_wawi.flash.takeover_failed', ['reason' => class_basename($e)]));
            }
        }

        return back()->with('success', __('jtl_wawi.flash.mode_changed'));
    }

    /** Übernahme-Inventur manuell wiederholen (idempotent je Tag). */
    public function takeover(Request $request, JtlTakeoverService $takeover): RedirectResponse {
        [$user, $organization] = $this->authorizeAdmin($request);
        abort_unless($user->can(Permission::InventoryConfigure->value), 403);

        try {
            $result = $takeover->importOpeningStock($organization, $user->id);
        } catch (Throwable $e) {
            return back()->with('error', __('jtl_wawi.flash.takeover_failed', ['reason' => class_basename($e)]));
        }

        return back()->with('success', __('jtl_wawi.flash.takeover_done', ['booked' => $result['booked'], 'pairs' => $result['pairs']]));
    }

    /** @return array{0: User, 1: Organization} */
    private function authorizeAdmin(Request $request): array {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isAdmin(), 403);
        $organization = $user->organization;
        abort_unless($organization instanceof Organization, 422);

        return [$user, $organization];
    }

    private function requireConnection(Organization $organization): JtlConnection {
        $connection = JtlConnection::query()->where('organization_id', $organization->id)->first();
        abort_unless($connection instanceof JtlConnection, 404);

        return $connection;
    }
}
