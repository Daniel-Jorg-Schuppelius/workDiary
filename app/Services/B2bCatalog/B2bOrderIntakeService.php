<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : B2bOrderIntakeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\B2bCatalog;

use App\Enums\Diary\Status;
use App\Models\Article;
use App\Models\B2b\{B2bCatalogAccess, B2bOrder};
use App\Models\{Customer, DiaryEntry, Organization, User};
use CommonToolkit\Helper\Data\CryptoHelper;
use ERechnungToolkit\Entities\Order;
use ERechnungToolkit\Parsers\OpenTransOrderParser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * openTRANS-2.1-ORDER-Auftragseingang (Feature 099, MVP-458).
 *
 * Bestellungen der Punchout-Kunden (Upload, Mail-Intake, Cloud-Intake) werden
 * über das erechnung-toolkit geparst und landen ausschließlich als offener
 * {@see B2bOrder}-Vorschlag in der Integrations-Drehscheibe
 * ({@see B2bOrderGroupBooker}) — kein Blind-Import. Idempotenz über
 * ORDER-ID + Käufer (Unique-Index als Backstop), Positionszuordnung über die
 * im Punchout übergebene workDiary-Artikelnummer (SUPPLIER_PID). Erst die
 * Buchung erzeugt den Auftrag ({@see DiaryEntry}).
 *
 * Läuft auch in Org-losen Kontexten (Mail-/Cloud-Jobs): alle Queries explizit
 * org-gebunden und ohne Global Scopes.
 */
class B2bOrderIntakeService {
    /**
     * @return array{status: 'created'|'duplicate', order: B2bOrder}
     *
     * @throws \RuntimeException wenn der Inhalt kein openTRANS-ORDER ist
     */
    public function intake(Organization $organization, string $xml, string $source): array {
        $parsed = (new OpenTransOrderParser)->parse($xml);

        $buyerName = trim($parsed->getBuyer()->getName());
        $buyerVat = trim((string) $parsed->getBuyer()->getVatId());
        $buyerKey = CryptoHelper::hash(mb_strtolower($buyerVat !== '' ? $buyerVat : $buyerName));

        $existing = B2bOrder::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('external_order_id', $parsed->getId())
            ->where('buyer_key', $buyerKey)
            ->first();
        if ($existing instanceof B2bOrder) {
            return ['status' => 'duplicate', 'order' => $existing];
        }

        $access = $this->matchAccess($organization, $buyerVat, $buyerName);

        try {
            $order = B2bOrder::query()->create([
                'organization_id' => $organization->id,
                'access_id' => $access?->id,
                'customer_id' => $access?->customer_id,
                'external_order_id' => $parsed->getId(),
                'buyer_key' => $buyerKey,
                'buyer' => $this->buyerSnapshot($parsed),
                'currency' => $parsed->getCurrency()->value,
                'total_net' => $this->totalNet($parsed),
                'lines' => $this->lineSnapshots($organization, $parsed),
                'source' => $source,
                'status' => B2bOrder::STATUS_OPEN,
                'ordered_at' => $parsed->getIssueDate()->format('Y-m-d H:i:s'),
                'requested_delivery_date' => $parsed->getRequestedDeliveryStartDate()?->format('Y-m-d'),
            ]);
        } catch (QueryException $e) {
            // Unique-Backstop (parallele Anlieferung): als Dublette behandeln.
            $order = B2bOrder::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('external_order_id', $parsed->getId())
                ->where('buyer_key', $buyerKey)
                ->first();
            if (! $order instanceof B2bOrder) {
                throw $e;
            }

            return ['status' => 'duplicate', 'order' => $order];
        }

        $order->audit('b2b_order.received', [
            'external_order_id' => $order->external_order_id,
            'source' => $source,
            'matched_customer_id' => $order->customer_id,
        ]);

        return ['status' => 'created', 'order' => $order];
    }

    /** Bucht die Bestellung: erzeugt den Auftrag (DiaryEntry) — idempotent. */
    public function book(B2bOrder $order, Customer $customer, User $actor): DiaryEntry {
        if (! $order->isOpen()) {
            $entry = $order->diaryEntry;
            if ($entry instanceof DiaryEntry) {
                return $entry;
            }

            throw new \RuntimeException((string) __('b2b_catalog.error.not_open'));
        }

        return DB::transaction(function () use ($order, $customer, $actor): DiaryEntry {
            $entry = DiaryEntry::query()->create([
                'organization_id' => $order->organization_id,
                'user_id' => $actor->id,
                'customer_id' => $customer->id,
                'title' => (string) __('b2b_catalog.order.entry_title', ['id' => $order->external_order_id]),
                'content' => $this->entryContent($order),
                'status' => Status::Open,
                'start_at' => now(),
                'end_at' => now()->addHour(),
                'scheduled_for' => $order->requested_delivery_date?->toDateString(),
            ]);

            $order->forceFill([
                'customer_id' => $customer->id,
                'status' => B2bOrder::STATUS_BOOKED,
                'diary_entry_id' => $entry->id,
                'booked_by' => $actor->id,
                'booked_at' => now(),
            ])->save();

            $order->audit('b2b_order.booked', ['diary_entry_id' => $entry->id, 'customer_id' => $customer->id]);

            return $entry;
        });
    }

    public function dismiss(B2bOrder $order): void {
        if (! $order->isOpen()) {
            return;
        }
        $order->forceFill(['status' => B2bOrder::STATUS_DISMISSED])->save();
        $order->audit('b2b_order.dismissed', ['external_order_id' => $order->external_order_id]);
    }

    /** Käuferzuordnung ausschließlich über die Punchout-Zugänge (USt-IdNr., sonst Name). */
    private function matchAccess(Organization $organization, string $buyerVat, string $buyerName): ?B2bCatalogAccess {
        $accesses = B2bCatalogAccess::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->active()
            ->with('customer')
            ->get();

        $normalizeVat = static fn(string $vat): string => strtoupper((string) preg_replace('/\s+/', '', $vat));
        if ($buyerVat !== '') {
            $wanted = $normalizeVat($buyerVat);
            foreach ($accesses as $access) {
                $vat = (string) ($access->customer->vat_id ?? '');
                if ($vat !== '' && $normalizeVat($vat) === $wanted) {
                    return $access;
                }
            }
        }

        $wantedName = mb_strtolower(trim($buyerName));
        if ($wantedName === '') {
            return null;
        }
        foreach ($accesses as $access) {
            if (mb_strtolower(trim((string) ($access->customer->name ?? ''))) === $wantedName) {
                return $access;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function buyerSnapshot(Order $parsed): array {
        $buyer = $parsed->getBuyer();
        $address = $buyer->getPostalAddress();

        return array_filter([
            'name' => $buyer->getName(),
            'vat_id' => $buyer->getVatId(),
            'contact_name' => $buyer->getContactName(),
            'contact_email' => $buyer->getContactEmail(),
            'contact_phone' => $buyer->getContactPhone(),
            'street' => $address?->getStreetName(),
            'zip' => $address?->getPostalCode(),
            'city' => $address?->getCity(),
            'country' => $address?->getCountry()?->value,
        ], static fn($value): bool => $value !== null && $value !== '');
    }

    /** @return list<array<string, mixed>> */
    private function lineSnapshots(Organization $organization, Order $parsed): array {
        $numbers = [];
        foreach ($parsed->getLines() as $line) {
            $number = trim((string) $line->getSellersItemId());
            if ($number !== '') {
                $numbers[] = $number;
            }
        }

        $articles = $numbers === [] ? collect() : Article::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('number', array_values(array_unique($numbers)))
            ->get()
            ->keyBy('number');

        $snapshots = [];
        foreach ($parsed->getLines() as $line) {
            $number = trim((string) $line->getSellersItemId());
            /** @var Article|null $article */
            $article = $number !== '' ? $articles->get($number) : null;

            $snapshots[] = [
                'line_id' => $line->getId(),
                'article_number' => $number !== '' ? $number : null,
                'article_id' => $article?->id,
                'buyer_item_id' => $line->getBuyersItemId(),
                'gtin' => $line->getStandardItemId(),
                'name' => $line->getItemName(),
                'quantity' => $line->getQuantity(),
                'unit' => $line->getUnitCode()->value,
                'unit_price' => $line->getUnitPrice()->getAmount(),
                'net_amount' => $line->getNetAmount()->getAmount(),
            ];
        }

        return $snapshots;
    }

    private function totalNet(Order $parsed): string {
        $total = \CommonToolkit\ValueObjects\Money::zero($parsed->getCurrency(), 2);
        foreach ($parsed->getLines() as $line) {
            $total = $total->plus($line->getNetAmount()->withScale(2));
        }

        return $total->getAmount();
    }

    /** Positionsliste als Auftragstext — unzugeordnete Nummern sichtbar markiert. */
    private function entryContent(B2bOrder $order): string {
        $rows = [];
        foreach ($order->lines as $line) {
            $qty = rtrim(rtrim(number_format((float) ($line['quantity'] ?? 0), 3, ',', ''), '0'), ',');
            $rows[] = sprintf(
                '%s× %s — %s%s',
                $qty,
                (string) ($line['article_number'] ?? ($line['name'] ?? '?')),
                (string) ($line['name'] ?? ''),
                ($line['article_id'] ?? null) === null ? ' ⚠ ' . __('b2b_catalog.order.line_unmatched') : '',
            );
        }

        return (string) __('b2b_catalog.order.entry_intro', [
            'id' => $order->external_order_id,
            'source' => $order->source,
        ]) . "\n\n" . implode("\n", $rows);
    }
}
