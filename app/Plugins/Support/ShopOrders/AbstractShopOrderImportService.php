<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractShopOrderImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\ShopOrders;

use APIToolkit\Exceptions\UnauthorizedException;
use App\Enums\Integration\MarketplaceInboxStatus;
use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use App\Plugins\PluginErrorRecorder;
use App\Plugins\Support\Concerns\ResolvesMarketplaceBuyer;
use App\Plugins\Support\{PluginApiException, PluginSettingsResolver, PluginTenantGate};
use App\Services\Integration\{IntegrationResolver, MatchProfileRegistry};
use App\Services\Integration\Match\MatchProfile;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{Cache, DB};
use RuntimeException;
use Throwable;

/**
 * Gemeinsame Strecke der Shop-Importe (Feature 175, MVP-1328): Bestellungen
 * seit dem Aufholpunkt lesen, je Bestellung den Spiegel anlegen oder
 * nachführen und den Käufer über die Marktplatz-Zuordnung einem vorhandenen
 * Kunden zuordnen — eindeutig verknüpft, sonst Klärfall, nie neu angelegt.
 * Nichts geht an den Shop zurück.
 *
 * Läufe nur nach „Import bestätigen“, unter Sperre je Organisation und Shop.
 * Der Aufholpunkt rückt nur nach vollständigem, fehlerfreiem Lesen vor; ein
 * Lauf überlappt den vorigen um einige Minuten. Nach dem täglichen Volllauf
 * fragt der Abgleich die gespiegelten Bestellungen gezielt ab und markiert,
 * was der Shop nicht mehr führt (gelöscht oder im Papierkorb) — gelöscht wird
 * im Spiegel nichts. Neue stornierte oder
 * erstattete Bestellungen legt der Import nur auf Wunsch an, bekannte führt er
 * immer nach.
 *
 * @template TOrder of Model&ShopOrder
 * @template TConnection of Model&ShopConnection
 */
abstract class AbstractShopOrderImportService {
    use ResolvesMarketplaceBuyer;

    public const PREVIEW_LIMIT = 50;

    private const LOCK_SECONDS = 1800;

    private const OVERLAP_MINUTES = 10;

    /** Bestell-IDs je Abfrage im Löschabgleich. */
    private const RECONCILE_BATCH = 100;

    public function __construct(
        protected readonly IntegrationResolver $resolver,
        private readonly MatchProfileRegistry $profiles,
    ) {}

    abstract public function pluginId(): string;

    /** @return class-string<TOrder> */
    abstract protected function orderModel(): string;

    /** @return class-string<TConnection> */
    abstract protected function connectionModel(): string;

    abstract public function isConfigured(int $organizationId): bool;

    /**
     * Bestellungen, die seit `$since` angelegt oder geändert wurden, in
     * stabiler Reihenfolge (Bestell-ID — eine Änderung während des Laufs
     * verschiebt dann keine Seite); wirft, wenn der Shop nicht vollständig
     * gelesen werden kann.
     *
     * @return iterable<array<string, mixed>>
     */
    abstract protected function fetch(Organization $organization, CarbonImmutable $since): iterable;

    /** @param array<string, mixed> $raw */
    abstract protected function normalize(array $raw, Organization $organization): RemoteOrder;

    /**
     * Welche der genannten Bestell-IDs der Shop noch führt. Wirft, wenn die
     * Antwort nicht vollständig ist — sonst würde Vorhandenes als gelöscht
     * markiert.
     *
     * @param  list<string>  $ids
     * @return list<string>
     */
    abstract protected function existingRemoteIds(Organization $organization, array $ids): array;

    /** Link zur Bestellung in der Shop-Verwaltung; null, wenn der Shop keinen stabilen Link kennt. */
    abstract public function orderUrl(Model $order): ?string;

    /** @return TConnection|null */
    public function connection(int $organizationId): ?Model {
        $model = $this->connectionModel();

        return $model::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->first();
    }

    /** Darf jetzt importiert werden? Plugin, Konfiguration, Verbindung und Bestätigung am Ausführungszeitpunkt. */
    public function mayRun(Organization $organization): bool {
        $orgId = (int) $organization->id;
        if (PluginTenantGate::blocks($orgId) || ! PluginSettingsResolver::for($this->pluginId(), $orgId)->enabled() || ! $this->isConfigured($orgId)) {
            return false;
        }
        $connection = $this->connection($orgId);

        return $connection !== null && $connection->isReady() && $connection->isImportConfirmed();
    }

    /** @return array{imported: int, updated: int, unchanged: int, skipped: int, linked: int, staged: int, failed: int, deleted: int} */
    public function run(Organization $organization, bool $full = false): array {
        $lock = Cache::lock('shop-orders:' . $this->pluginId() . ':' . $organization->id, self::LOCK_SECONDS);
        if (! $lock->get()) {
            throw ShopOrderBusyException::for($this->pluginId(), (int) $organization->id);
        }
        try {
            return $this->runLocked($organization, $full);
        } finally {
            $lock->release();
        }
    }

    /**
     * Was der erste Lauf im Zeitfenster sähe — ohne zu schreiben.
     *
     * @return array{rows: list<array{order: RemoteOrder, known: bool, customer: ?string, skipped: bool}>, more: bool, since: CarbonImmutable}
     */
    public function preview(Organization $organization): array {
        $orgId = (int) $organization->id;
        $since = CarbonImmutable::now()->subDays($this->initialWindowDays($orgId));
        $model = $this->orderModel();
        $rows = [];
        $more = false;
        foreach ($this->fetch($organization, $since) as $raw) {
            if (count($rows) >= self::PREVIEW_LIMIT) {
                $more = true;

                break;
            }
            $remote = $this->normalize($raw, $organization);
            $known = $model::query()->where('organization_id', $orgId)->where('remote_order_id', $remote->id)->exists();
            $customer = $remote->buyerKey !== null ? $this->marketplaceCustomerByReference($organization, $this->pluginId(), $remote->buyerKey) : null;
            $rows[] = [
                'order' => $remote,
                'known' => $known,
                'customer' => $customer?->name,
                'skipped' => ! $known && $remote->status->isVoid() && ! $this->includeVoid($orgId),
            ];
        }

        return ['rows' => $rows, 'more' => $more, 'since' => $since];
    }

    public function initialWindowDays(int $organizationId): int {
        return max(1, min(365, PluginSettingsResolver::for($this->pluginId(), $organizationId)->int('initial_window_days', 30)));
    }

    protected function includeVoid(int $organizationId): bool {
        return PluginSettingsResolver::for($this->pluginId(), $organizationId)->bool('include_cancelled', false);
    }

    /** @return array{imported: int, updated: int, unchanged: int, skipped: int, linked: int, staged: int, failed: int, deleted: int} */
    private function runLocked(Organization $organization, bool $full): array {
        $counters = ['imported' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'linked' => 0, 'staged' => 0, 'failed' => 0, 'deleted' => 0];
        if (! $this->mayRun($organization)) {
            return $counters;
        }
        $connection = $this->connection((int) $organization->id);
        if ($connection === null) {
            return $counters;
        }
        $profile = $this->profiles->for(Customer::class);
        if (! $profile instanceof MatchProfile) {
            throw new RuntimeException('Kein MatchProfile für Customer registriert.');
        }

        $startedAt = CarbonImmutable::now();
        $checkpoint = $connection->getAttribute('checkpoint_at');
        $since = ! $full && $checkpoint instanceof CarbonImmutable
            ? $checkpoint->subMinutes(self::OVERLAP_MINUTES)
            : $startedAt->subDays($this->initialWindowDays((int) $organization->id));

        $complete = false;
        $error = null;
        try {
            foreach ($this->fetch($organization, $since) as $raw) {
                try {
                    $remote = $this->normalize($raw, $organization);
                    // Per Referenz: importOne zählt Zuordnungen und Klärfälle mit.
                    $outcome = DB::transaction(function () use ($organization, $profile, $remote, &$counters): string {
                        return $this->importOne($organization, $profile, $remote, $counters);
                    });
                } catch (Throwable $e) {
                    $outcome = 'failed';
                    $this->recordError($e, $organization, ['remote_order_id' => is_scalar($raw['id'] ?? null) ? (string) $raw['id'] : null]);
                }
                $counters[$outcome]++;
            }
            $complete = true;
        } catch (Throwable $e) {
            $this->recordError($e, $organization, []);
            if ($e instanceof UnauthorizedException || ($e instanceof PluginApiException && $e->isAuthError())) {
                $connection->recordConnectionFailure(class_basename($e));

                throw $e;
            }
            $error = class_basename($e) . ($e instanceof PluginApiException && $e->status > 0 ? ' ' . $e->status : '');
        }

        $succeeded = $complete && $counters['failed'] === 0;
        if ($full && $succeeded) {
            try {
                $counters['deleted'] = $this->reconcileDeletions($organization);
            } catch (Throwable $e) {
                $this->recordError($e, $organization, ['phase' => 'deletions']);
                $error = class_basename($e);
            }
        }
        $connection->forceFill([
            'last_synced_at' => $startedAt,
            'last_sync_counters' => $counters,
            'is_last_run_complete' => $succeeded,
            'checkpoint_at' => $succeeded ? $startedAt : $checkpoint,
            'last_full_sync_at' => $full && $succeeded ? $startedAt : $connection->getAttribute('last_full_sync_at'),
        ])->save();
        $error !== null ? $connection->recordConnectionFailure($error) : $connection->recordConnectionSuccess();

        return $counters;
    }

    /**
     * @param  array{imported: int, updated: int, unchanged: int, skipped: int, linked: int, staged: int, failed: int, deleted: int}  $counters
     * @return 'imported'|'updated'|'unchanged'|'skipped'
     */
    private function importOne(Organization $organization, MatchProfile $profile, RemoteOrder $remote, array &$counters): string {
        $model = $this->orderModel();
        $row = $model::query()->where('organization_id', $organization->id)->where('remote_order_id', $remote->id)->lockForUpdate()->first();
        if ($row === null && $remote->status->isVoid() && ! $this->includeVoid((int) $organization->id)) {
            return 'skipped';
        }

        $customerId = $row?->getAttribute('customer_id');
        if ($customerId === null && $remote->buyerKey !== null) {
            $customer = $this->resolveMarketplaceBuyer(
                $organization,
                $profile,
                $this->pluginId(),
                $remote->buyerKey,
                ['name' => $remote->buyerCompany ?? $remote->buyerName, 'email' => $remote->buyerEmail],
                $remote->buyer() ?? [],
                $this->pluginId(),
                $model,
            );
            $customer !== null ? $counters['linked']++ : $counters['staged']++;
            $customerId = $customer?->id;
        }

        $row ??= new $model(['organization_id' => $organization->id, 'remote_order_id' => $remote->id]);
        $row->fill([
            'order_number' => $remote->number,
            'status' => $remote->status,
            'remote_status_code' => $remote->remoteStatus,
            'currency' => $remote->currency,
            'gross_amount' => $remote->grossAmount,
            'net_amount' => $remote->netAmount,
            'refunded_amount' => $remote->refundedAmount,
            'buyer_external_id' => $remote->buyerKey,
            'buyer' => $remote->buyer(),
            'items' => $remote->items !== [] ? $remote->items : null,
            'ordered_at' => $remote->orderedAt,
            'remote_modified_at' => $remote->modifiedAt,
            'remote_deleted_at' => null,
            'customer_id' => $customerId,
            'inbox_status' => $customerId !== null ? MarketplaceInboxStatus::Linked : MarketplaceInboxStatus::Open,
        ]);
        if ($row->exists && ! $row->isDirty()) {
            return 'unchanged';
        }
        $created = ! $row->exists;
        $row->save();

        return $created ? 'imported' : 'updated';
    }

    /**
     * Markiert gespiegelte Bestellungen, die der Shop nicht mehr führt, und
     * nimmt die Markierung zurück, wenn eine wieder auftaucht (Papierkorb).
     *
     * @return int neu als gelöscht markierte Bestellungen
     */
    private function reconcileDeletions(Organization $organization): int {
        $model = $this->orderModel();
        $now = CarbonImmutable::now();
        $marked = 0;
        $model::query()->where('organization_id', $organization->id)->select(['id', 'remote_order_id'])
            ->chunkById(self::RECONCILE_BATCH, function ($rows) use ($organization, $model, $now, &$marked): void {
                $remoteIds = array_values(array_map(static fn (Model $row): string => (string) $row->getAttribute('remote_order_id'), $rows->all()));
                $present = array_flip($this->existingRemoteIds($organization, $remoteIds));
                $gone = array_values(array_filter($remoteIds, static fn (string $id): bool => ! isset($present[$id])));
                $scope = $model::query()->where('organization_id', $organization->id);
                $marked += (clone $scope)->whereIn('remote_order_id', $gone)->whereNull('remote_deleted_at')->update(['remote_deleted_at' => $now]);
                // array_flip macht Ziffern-IDs zu int-Schlüsseln; die Spalte ist Text.
                (clone $scope)->whereIn('remote_order_id', array_map('strval', array_keys($present)))->whereNotNull('remote_deleted_at')->update(['remote_deleted_at' => null]);
            });

        return $marked;
    }

    /** @param array<string, mixed> $context */
    private function recordError(Throwable $e, Organization $organization, array $context): void {
        try {
            app(PluginErrorRecorder::class)->record($this->pluginId(), 'runtime', $e, $context, (int) $organization->id);
        } catch (Throwable) {
            // Aufzeichnung darf den Lauf nicht reißen.
        }
    }
}
