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

use App\Http\Controllers\Controller;
use App\Models\{BillbeeOrder, IntegrationInboxItem, Organization, User};
use App\Plugins\Billbee\BillbeePlugin;
use App\Plugins\Billbee\Services\{BillbeeArticleMappingService, BillbeeOrderImportService};
use Illuminate\Http\{RedirectResponse, Request};
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

        $orders = BillbeeOrder::query()
            ->where('organization_id', $organization->id)
            ->when($channel !== '', fn($q) => $q->where('channel', $channel))
            ->when($state !== null && $state !== '', fn($q) => $q->where('state', (int) $state))
            ->orderByDesc('ordered_at')
            ->paginate(25)
            ->withQueryString();

        $channels = BillbeeOrder::query()
            ->where('organization_id', $organization->id)
            ->whereNotNull('channel')
            ->distinct()
            ->orderBy('channel')
            ->pluck('channel');

        $openInbox = IntegrationInboxItem::query()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', BillbeePlugin::ID)
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->count();

        return view(BillbeePlugin::ID . '::admin.index', [
            'orders' => $orders,
            'channels' => $channels,
            'channel' => $channel,
            'state' => $state,
            'openInbox' => $openInbox,
            'lastSyncAt' => BillbeeOrder::query()->where('organization_id', $organization->id)->max('updated_at'),
        ]);
    }

    public function syncNow(Request $request, BillbeeOrderImportService $orders, BillbeeArticleMappingService $mappings): RedirectResponse {
        [, $organization] = $this->authorizeAdmin($request);

        try {
            $counters = $orders->import($organization) + ['mapping' => $mappings->import($organization)];

            return back()->with('success', (string) __('billbee.flash.synced', [
                'imported' => $counters['imported'],
                'staged' => $counters['staged'],
            ]));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', (string) __('billbee.flash.sync_failed'));
        }
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
