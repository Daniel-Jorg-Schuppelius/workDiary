<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShopOrderAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\ShopOrders;

use App\Enums\Integration\ShopOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Integration\IntegrationInboxItem;
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use App\Plugins\Support\OAuthConnectionStatus;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;
use Throwable;

/**
 * Verwaltungsseite der Shop-Importe (Feature 175, MVP-1329): Verbindung
 * prüfen, Vorschau, Import bestätigen, abgleichen, trennen und der
 * Bestellspiegel mit Suche. Zugangsdaten stehen in den Plugin-Einstellungen.
 *
 * @template TOrder of Model&ShopOrder
 * @template TConnection of Model&ShopConnection
 */
abstract class ShopOrderAdminController extends Controller {
    use ResolvesPluginOrgContext;

    /** @return AbstractShopOrderImportService<TOrder, TConnection> */
    abstract protected function importService(): AbstractShopOrderImportService;

    /** @return class-string<TConnection> */
    abstract protected function connectionModel(): string;

    /** @return class-string<TOrder> */
    abstract protected function orderModel(): string;

    abstract protected function providerName(): string;

    /**
     * Shop anrufen und seine Identität liefern; wirft, wenn er nicht erreichbar
     * ist oder den Zugang ablehnt.
     *
     * @return array{source_id: string, source_name: string, source_url: string, account_name: ?string}
     */
    abstract protected function verifyConnection(int $organizationId): array;

    public function index(Request $request): View {
        $organization = $this->organization($this->admin());
        $service = $this->importService();
        $connection = $service->connection((int) $organization->id);
        $search = StringHelper::trimToNull($request->query('q'));
        $status = ShopOrderStatus::tryFrom((string) $request->query('status', ''));
        $model = $this->orderModel();

        $query = $model::query()->where('organization_id', $organization->id);
        if ($search !== null) {
            $model::whereMatching($query, $search);
        }
        if ($status !== null) {
            $query->where('status', $status->value);
        }
        $orders = $query
            ->with('customer')
            ->orderByDesc('ordered_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('plugins.shop-orders.admin', [
            'provider' => $this->providerName(),
            'pluginId' => $service->pluginId(),
            'routePrefix' => $this->routePrefix(),
            'configured' => $service->isConfigured((int) $organization->id),
            'connection' => $connection,
            'connected' => $connection !== null && $connection->getAttribute('status') === OAuthConnectionStatus::Active,
            'orders' => $orders,
            'search' => $search,
            'status' => $status,
            'openInbox' => IntegrationInboxItem::openCount((int) $organization->id, $service->pluginId()),
            'windowDays' => $service->initialWindowDays((int) $organization->id),
            'urls' => $orders->getCollection()->mapWithKeys(fn (Model $order): array => [(int) $order->getKey() => $service->orderUrl($order)])->all(),
        ]);
    }

    public function verify(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $service = $this->importService();
        if (! $service->isConfigured((int) $organization->id)) {
            return $this->back()->with('error', $this->flash('not_configured'));
        }
        try {
            $identity = $this->verifyConnection((int) $organization->id);
        } catch (Throwable $e) {
            report($e);

            return $this->back()->with('error', $this->flash('verify_failed', ['class' => class_basename($e)]));
        }

        $model = $this->connectionModel();
        $connection = $model::query()->firstOrNew(['organization_id' => $organization->id]);
        $orders = $this->orderModel();
        $previous = $connection->sourceId();
        if ($previous !== null && $previous !== $identity['source_id']
            && $orders::query()->where('organization_id', $organization->id)->exists()) {
            return $this->back()->with('error', $this->flash('source_locked'));
        }
        if ($previous !== $identity['source_id']) {
            // Ein anderer Shop ohne Bestand beginnt neu: Bestätigung und Aufholpunkt gelten nicht weiter.
            $connection->forceFill(['import_confirmed_at' => null, 'confirmed_by' => null, 'checkpoint_at' => null]);
        }

        $connection->forceFill($identity + [
            'status' => OAuthConnectionStatus::Active,
            'last_error' => null,
            'last_error_at' => null,
            'consecutive_failures' => 0,
            'disabled_at' => null,
            'connected_by' => $admin->id,
            'connected_at' => CarbonImmutable::now(),
            'disconnected_by' => null,
            'disconnected_at' => null,
        ])->save();
        // @phpstan-ignore method.notFound (Auditable)
        $connection->audit($service->pluginId() . '.connected', ['source_id' => $identity['source_id'], 'by_user_id' => (int) $admin->id]);

        return $this->back()->with('success', $this->flash('verified', ['source_name' => $identity['source_name']]));
    }

    /** Dialog: was der erste Lauf übernähme — ohne zu schreiben. */
    public function preview(): View|RedirectResponse {
        $organization = $this->organization($this->admin());
        $service = $this->importService();
        if (! $this->ready($organization->id)) {
            return $this->back()->with('error', $this->flash('not_ready'));
        }
        try {
            $preview = $service->preview($organization);
        } catch (Throwable $e) {
            report($e);

            return $this->back()->with('error', $this->flash('preview_failed', ['class' => class_basename($e)]));
        }

        return view('plugins.shop-orders._preview_dialog', $preview + [
            'provider' => $this->providerName(),
            'routePrefix' => $this->routePrefix(),
            'confirmed' => $service->connection((int) $organization->id)?->isImportConfirmed() === true,
        ]);
    }

    public function confirm(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        if (! $this->ready($organization->id)) {
            return $this->back()->with('error', $this->flash('not_ready'));
        }
        $connection = $this->importService()->connection((int) $organization->id);
        if ($connection !== null && ! $connection->isImportConfirmed()) {
            $connection->forceFill(['import_confirmed_at' => CarbonImmutable::now(), 'confirmed_by' => $admin->id])->save();
            // @phpstan-ignore method.notFound (Auditable)
            $connection->audit($this->importService()->pluginId() . '.import_confirmed', ['by_user_id' => (int) $admin->id]);
        }

        return $this->back()->with('success', $this->flash('confirmed'));
    }

    public function syncNow(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $service = $this->importService();
        if (! $service->mayRun($organization)) {
            return $this->back()->with('error', $this->flash('not_ready'));
        }
        try {
            $counters = $service->run($organization);
        } catch (ShopOrderBusyException) {
            return $this->back()->with('error', $this->flash('busy'));
        } catch (Throwable $e) {
            report($e);

            return $this->back()->with('error', $this->flash('sync_failed', ['class' => class_basename($e)]));
        }

        return $this->back()->with('success', $this->flash('synced', array_map('strval', $counters)));
    }

    /** Trennen: der Abgleich ruht; Bestellungen und Kundenzuordnungen bleiben. */
    public function disconnect(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $connection = $this->importService()->connection((int) $organization->id);
        if ($connection !== null) {
            $connection->forceFill([
                'status' => OAuthConnectionStatus::Disconnected,
                'disconnected_by' => $admin->id,
                'disconnected_at' => CarbonImmutable::now(),
            ])->save();
            // @phpstan-ignore method.notFound (Auditable)
            $connection->audit($this->importService()->pluginId() . '.disconnected', ['by_user_id' => (int) $admin->id]);
        }

        return $this->back()->with('success', $this->flash('disconnected'));
    }

    protected function routePrefix(): string {
        return 'admin.' . $this->importService()->pluginId();
    }

    private function ready(int|string $organizationId): bool {
        $service = $this->importService();

        return $service->isConfigured((int) $organizationId) && $service->connection((int) $organizationId)?->isReady() === true;
    }

    private function back(): RedirectResponse {
        return redirect()->toList($this->routePrefix() . '.index');
    }

    /** @param array<string, string> $replace */
    private function flash(string $key, array $replace = []): string {
        return (string) __('integration.shop_orders.flash.' . $key, $replace + ['provider' => $this->providerName()]);
    }
}
