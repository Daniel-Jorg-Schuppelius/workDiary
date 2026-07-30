<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : B2bOrderGroupBooker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\B2bCatalog;

use App\Models\B2b\B2bOrder;
use App\Models\{Customer, Organization};
use App\Services\Integration\InboxGroupBooker;
use App\Services\SqidEncoder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Bindet die offenen openTRANS-Bestellungen (Feature 099, MVP-458) an die
 * universelle Zuordnungs-Inbox: jede Bestellung ist eine Gruppe, die Buchung
 * erzeugt den Auftrag über den {@see B2bOrderIntakeService}. Bestellungen
 * ohne automatische Käuferzuordnung verlangen die Kundenwahl im Formular.
 */
class B2bOrderGroupBooker implements InboxGroupBooker {
    public const PLUGIN_ID = 'b2b-katalog';

    public function __construct(
        private readonly B2bOrderIntakeService $service,
        private readonly SqidEncoder $sqids,
    ) {}

    public function groups(Organization $organization): Collection {
        /** @var Collection<int, array<string, mixed>> $groups */
        $groups = B2bOrder::query()
            ->where('organization_id', $organization->id)
            ->open()
            ->with('customer')
            ->orderByDesc('id')
            ->get()
            ->map(function (B2bOrder $order): array {
                $lines = $order->lines;
                $unmatched = count(array_filter($lines, static fn(array $line): bool => ($line['article_id'] ?? null) === null));

                return [
                    'plugin_id' => self::PLUGIN_ID,
                    'form' => 'b2b_order',
                    'group_key' => $order->sqid,
                    'order_id' => $order->external_order_id,
                    'customer_name' => $order->customer?->name,
                    'customer_sqid' => $order->customer?->sqid,
                    'buyer_name' => (string) ($order->buyer['name'] ?? ''),
                    'source' => $order->source,
                    'count' => count($lines),
                    'unmatched' => $unmatched,
                    'total' => $order->total_net?->format(),
                    'ordered_at' => $order->ordered_at,
                ];
            })
            ->values();

        return $groups;
    }

    public function rules(): array {
        return [
            'customer' => ['nullable', 'string'],
        ];
    }

    public function book(Organization $organization, string $groupKey, array $input): array {
        $order = $this->order($organization, $groupKey);

        $customer = $order->customer;
        $customerSqid = trim((string) ($input['customer'] ?? ''));
        if ($customerSqid !== '') {
            $id = $this->sqids->decode(Customer::class, $customerSqid);
            $customer = $id !== null
                ? Customer::query()->where('organization_id', $organization->id)->whereKey($id)->first()
                : null;
        }
        if (! $customer instanceof Customer) {
            throw new \RuntimeException((string) __('b2b_catalog.error.customer_required'));
        }

        $actor = Auth::user();
        if (! $actor instanceof \App\Models\User) {
            throw new \RuntimeException((string) __('b2b_catalog.error.customer_required'));
        }

        $alreadyBooked = ! $order->isOpen();
        $this->service->book($order, $customer, $actor);

        return ['created' => $alreadyBooked ? 0 : 1, 'skipped' => $alreadyBooked ? 1 : 0];
    }

    public function dismiss(Organization $organization, string $groupKey): int {
        $order = $this->order($organization, $groupKey);
        $this->service->dismiss($order);

        return 1;
    }

    private function order(Organization $organization, string $groupKey): B2bOrder {
        $id = $this->sqids->decode(B2bOrder::class, $groupKey);

        return B2bOrder::query()
            ->where('organization_id', $organization->id)
            ->whereKey((int) $id)
            ->firstOrFail();
    }
}
