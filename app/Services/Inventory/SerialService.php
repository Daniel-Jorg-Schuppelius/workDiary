<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SerialService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\{SerialSource, SerialStatus};
use App\Models\{ArticleVariant, Customer, ManufacturingOrder, StockDelivery, StockSerial, Warehouse};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Lebenslauf von Einzelseriennummern (Feature 047/048, E2): Erzeugen/Erfassen,
 * Ausliefern (Eigentumsübergang + Versandnachweis), Sperren/Entsperren,
 * Rücknahme, Verschrottung sowie Provenienz-/Echtheitsprüfung. Eine Seriennummer
 * existiert genau einmal je Organisation + Artikel (harte Dublettensperre).
 */
class SerialService {
    public function __construct(private readonly SerialNumberGenerator $generator) {}

    /** Registriert eine zugekaufte oder erzeugte Seriennummer; lehnt Duplikate ab. */
    public function register(ArticleVariant $variant, string $serialNo, SerialSource $source, ?Warehouse $warehouse = null, ?ManufacturingOrder $order = null, ?int $createdBy = null): StockSerial {
        $serialNo = trim($serialNo);
        if ($serialNo === '') {
            throw new RuntimeException('Leere Seriennummer.');
        }

        $articleId = (int) $variant->article_id;
        $duplicate = StockSerial::query()
            ->where('organization_id', $variant->organization_id)
            ->where('article_id', $articleId)
            ->where('serial_no', $serialNo)
            ->exists();
        if ($duplicate) {
            throw new RuntimeException('Seriennummer bereits vergeben: ' . $serialNo);
        }

        return StockSerial::query()->create([
            'organization_id' => $variant->organization_id,
            'article_id' => $articleId,
            'article_variant_id' => $variant->id,
            'serial_no' => $serialNo,
            'status' => ($warehouse !== null ? SerialStatus::InStock : SerialStatus::Created)->value,
            'source' => $source->value,
            'warehouse_id' => $warehouse?->id,
            'manufacturing_order_id' => $order?->id,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Erzeugt fortlaufende Seriennummern für die Eigenfertigung.
     *
     * @return list<StockSerial>
     */
    public function generate(ArticleVariant $variant, int $count, ?Warehouse $warehouse = null, ?ManufacturingOrder $order = null, ?int $createdBy = null): array {
        if ($count < 1) {
            return [];
        }

        return DB::transaction(function () use ($variant, $count, $warehouse, $order, $createdBy): array {
            $serials = [];
            for ($i = 0; $i < $count; $i++) {
                $no = $this->generator->generateFor($variant);
                $serials[] = $this->register($variant, $no, SerialSource::Manufactured, $warehouse, $order, $createdBy);
            }

            return $serials;
        });
    }

    /**
     * Erfasst eine zugekaufte Seriennummer beim Wareneingang und prüft vorher die
     * Sperrliste: eine organisationsweit gesperrte Nummer (verloren/gestohlen/
     * Rückruf) wird abgewiesen.
     */
    public function captureForReceipt(ArticleVariant $variant, string $serialNo, ?Warehouse $warehouse = null, ?int $createdBy = null): StockSerial {
        $this->assertNotBlocklisted((int) $variant->organization_id, $serialNo);

        return $this->register($variant, $serialNo, SerialSource::Purchased, $warehouse, null, $createdBy);
    }

    /** Wirft, wenn die Seriennummer organisationsweit gesperrt ist. */
    public function assertNotBlocklisted(int $organizationId, string $serialNo): void {
        $blocked = StockSerial::query()
            ->where('organization_id', $organizationId)
            ->where('serial_no', trim($serialNo))
            ->where('status', SerialStatus::Blocked->value)
            ->exists();
        if ($blocked) {
            throw new RuntimeException('Seriennummer gesperrt: ' . trim($serialNo));
        }
    }

    /** Bindet eine Seriennummer an eine Auslieferung und überträgt das Eigentum an den Kunden. */
    public function ship(StockSerial $serial, StockDelivery $delivery, ?Customer $customer = null): StockSerial {
        $this->assertShippable($serial);

        $serial->forceFill([
            'status' => SerialStatus::Shipped,
            'customer_id' => $customer instanceof Customer ? $customer->id : $delivery->customer_id,
            'stock_delivery_id' => $delivery->id,
            'warehouse_id' => null,
            'shipped_at' => Carbon::now(),
        ])->save();

        return $serial;
    }

    public function assertShippable(StockSerial $serial): void {
        if (! $serial->status->isShippable()) {
            throw new RuntimeException('Seriennummer ' . $serial->serial_no . ' ist nicht auslieferbar (Status ' . $serial->status->value . ').');
        }
    }

    /** Sperrt eine Seriennummer (verloren/gestohlen/Rückruf) – kann nicht ausgeliefert werden. */
    public function block(StockSerial $serial, string $reason): StockSerial {
        $serial->forceFill(['status' => SerialStatus::Blocked, 'blocked_reason' => $reason])->save();

        return $serial;
    }

    public function unblock(StockSerial $serial, ?Warehouse $warehouse = null): StockSerial {
        $target = $warehouse ?? $serial->warehouse;
        $serial->forceFill([
            'status' => $target instanceof Warehouse ? SerialStatus::InStock : SerialStatus::Created,
            'warehouse_id' => $warehouse instanceof Warehouse ? $warehouse->id : $serial->warehouse_id,
            'blocked_reason' => null,
        ])->save();

        return $serial;
    }

    public function returnSerial(StockSerial $serial, ?Warehouse $warehouse = null): StockSerial {
        $serial->forceFill([
            'status' => SerialStatus::Returned,
            'warehouse_id' => $warehouse instanceof Warehouse ? $warehouse->id : $serial->warehouse_id,
        ])->save();

        return $serial;
    }

    public function scrap(StockSerial $serial): StockSerial {
        $serial->forceFill(['status' => SerialStatus::Scrapped, 'warehouse_id' => null])->save();

        return $serial;
    }

    /** Betrugs-/Garantieprüfung: wurde diese Seriennummer tatsächlich an diesen Kunden ausgeliefert? */
    public function wasShippedTo(int $organizationId, string $serialNo, Customer $customer): bool {
        return StockSerial::query()
            ->where('organization_id', $organizationId)
            ->where('serial_no', trim($serialNo))
            ->where('customer_id', $customer->id)
            ->whereIn('status', [SerialStatus::Shipped->value, SerialStatus::Returned->value])
            ->exists();
    }

    /** Geräte-Pass: Status einer Seriennummer abrufen (Echtheits-/Statusprüfung). */
    public function lookup(int $organizationId, string $serialNo): ?StockSerial {
        return StockSerial::query()
            ->where('organization_id', $organizationId)
            ->where('serial_no', trim($serialNo))
            ->first();
    }
}
