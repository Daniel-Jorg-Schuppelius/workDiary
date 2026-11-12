<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillbeeAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Billbee\Http\Controllers;

use App\Enums\Inventory\InventoryMode;
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\Audit\AuditLog;
use App\Models\Integration\IntegrationInboxItem;
use App\Models\Platform\{Organization, User};
use App\Plugins\Billbee\Api\BillbeeClientFactory;
use App\Plugins\Billbee\BillbeePlugin;
use App\Plugins\Billbee\Models\BillbeeOrder;
use App\Plugins\Billbee\Services\{BillbeeArticleMappingService, BillbeeOrderImportService};
use App\Plugins\PluginManager;
use App\Services\Inventory\InventoryProviderResolver;
use App\Support\MorphMap;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Billbee-Admin (MVP-433): Bestellspiegel mit Kanalherkunft, offene
 * Inbox-Fälle und manueller Sync. Admin-Gate wie JTL/Todoist (isAdmin +
 * Org-Kontext); Zugangsdaten laufen über die Auto-Form der Plugin-Karte.
 */
class BillbeeAdminController extends Controller {
    public function index(Request $request): View {
        [, $organization] = $this->authorizeAdmin($request);

        $channel = trim((string) $request->query('channel', ''));
        $state = $request->query('state');
        $search = trim((string) $request->query('q', ''));

        $orders = BillbeeOrder::query()
            ->where('organization_id', $organization->id)
            ->when($channel !== '', fn($q) => $q->where('channel', $channel))
            ->when($state !== null && $state !== '', fn($q) => $q->where('state', (int) $state))
            ->when($search !== '', fn($q) => $q->matching($search))
            ->orderByDesc('ordered_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $channels = BillbeeOrder::query()
            ->where('organization_id', $organization->id)
            ->whereNotNull('channel')
            ->distinct()
            ->orderBy('channel')
            ->pluck('channel');

        $openInbox = IntegrationInboxItem::openCount((int) $organization->id, BillbeePlugin::ID);

        return view(BillbeePlugin::ID . '::admin.index', [
            'orders' => $orders,
            'channels' => $channels,
            'channel' => $channel,
            'state' => $state,
            'search' => $search,
            'openInbox' => $openInbox,
            'lastSyncAt' => BillbeeOrder::query()->where('organization_id', $organization->id)->max('updated_at'),
            'inventoryMode' => app(InventoryProviderResolver::class)->modeFor($organization),
            'inventoryPlugin' => (string) data_get($organization->settings, 'inventory_plugin_id', ''),
            'canConfigureInventory' => $request->user()?->can(Permission::InventoryConfigure->value) ?? false,
        ]);
    }

    public function syncNow(Request $request, BillbeeOrderImportService $orders, BillbeeArticleMappingService $mappings): RedirectResponse {
        [, $organization] = $this->authorizeAdmin($request);

        try {
            $counters = $orders->import($organization) + ['mapping' => $mappings->import($organization)];

            return back()->with('success', (string) __('billbee::billbee.flash.synced', [
                'imported' => $counters['imported'],
                'staged' => $counters['staged'],
            ]));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', (string) __('billbee::billbee.flash.sync_failed'));
        }
    }

    /**
     * Bestandsrückkanal ein- oder ausschalten (MVP-1626): „Billbee“ bucht weiter
     * ins lokale Journal und meldet jeden Stand als Absolutbestand an Billbee;
     * „lokal“ meldet nichts. „Nur lesen“ gibt es nicht — Billbee führt keinen
     * eigenen Bestand, aus dem WorkDiary lesen könnte. Verlangt inventory.configure.
     */
    public function updateMode(Request $request, InventoryProviderResolver $resolver, BillbeeClientFactory $clients): RedirectResponse {
        [$user, $organization] = $this->authorizeAdmin($request);
        abort_unless($user->can(Permission::InventoryConfigure->value), 403);

        $data = $request->validate([
            'inventory_mode' => ['required', Rule::in([InventoryMode::Local->value, InventoryMode::External->value])],
        ]);
        $target = InventoryMode::from((string) $data['inventory_mode']);
        $current = $resolver->modeFor($organization);
        $currentPlugin = (string) data_get($organization->settings, 'inventory_plugin_id', '');

        // Führt ein anderes Plugin, wechselt nur dessen Seite — sonst entfiele etwa die Übernahme-Inventur von JTL.
        if ($current !== InventoryMode::Local && $currentPlugin !== '' && $currentPlugin !== BillbeePlugin::ID) {
            return back()->with('error', (string) __('billbee::billbee.inventory.other_leads', ['plugin' => app(PluginManager::class)->find($currentPlugin)?->name() ?? $currentPlugin]));
        }
        if ($target === $current) {
            return back()->with('success', (string) __('billbee::billbee.inventory.unchanged'));
        }
        if ($target === InventoryMode::External && ! $clients->isConfigured((int) $organization->id)) {
            return back()->with('error', (string) __('billbee::billbee.inventory.needs_connection'));
        }

        $settings = (array) ($organization->settings ?? []);
        $settings['inventory_mode'] = $target->value;
        $settings['inventory_plugin_id'] = $target === InventoryMode::Local ? null : BillbeePlugin::ID;
        $organization->forceFill(['settings' => $settings])->save();

        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'event' => 'inventory.mode_changed',
            'auditable_type' => MorphMap::stableKey($organization::class),
            'auditable_id' => $organization->getKey(),
            'changes' => ['from' => $current->value, 'to' => $target->value, 'plugin_id' => $settings['inventory_plugin_id']],
        ]);

        return back()->with('success', (string) __('billbee::billbee.inventory.changed'));
    }

    /** @return array{0: User, 1: Organization} */
    private function authorizeAdmin(Request $request): array {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isAdmin(), 403);
        $organization = $user->organization;
        abort_unless($organization instanceof Organization, 422);

        return [$user, $organization];
    }
}
