<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AdviceService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\Procurement\AdviceStatus;
use App\Models\{PurchaseOrder, PurchaseOrderAdvice, PurchaseOrderLine};
use App\Support\DecimalQty;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Lieferavis (ASN) – Feature 048, E4. Erfasst eine angekündigte Sendung zu einer
 * Bestellung und bucht daraus den Wareneingang gegen die Bestellzeilen
 * ({@see GoodsReceiptService}). Avis und Wareneingang bleiben getrennt: das
 * Erfassen kündigt nur an, das Vereinnahmen bucht Bestand.
 */
class AdviceService {
    public const SCALE = 4;

    public function __construct(private readonly GoodsReceiptService $receipts) {}

    /**
     * Erfasst ein Lieferavis. `$lines` = Liste aus [PurchaseOrderLine, Menge].
     *
     * @param  list<array{line: PurchaseOrderLine, qty: string}>  $lines
     * @param  array<string, mixed>  $options
     */
    public function announce(PurchaseOrder $order, array $lines, array $options = []): PurchaseOrderAdvice {
        $relevant = array_values(array_filter($lines, fn (array $l): bool => bccomp(DecimalQty::positive($l['qty']), '0', self::SCALE) > 0));
        if ($relevant === []) {
            throw new RuntimeException('Lieferavis ohne Positionen.');
        }

        return DB::transaction(function () use ($order, $relevant, $options): PurchaseOrderAdvice {
            /** @var PurchaseOrderAdvice $advice */
            $advice = $order->advices()->create([
                'organization_id' => $order->organization_id,
                'reference' => $options['reference'] ?? null,
                'carrier' => $options['carrier'] ?? null,
                'tracking' => $options['tracking'] ?? null,
                'expected_at' => $options['expected_at'] ?? null,
                'status' => AdviceStatus::Announced->value,
                'note' => $options['note'] ?? null,
                'created_by' => $options['created_by'] ?? null,
            ]);

            foreach ($relevant as $item) {
                $advice->lines()->create([
                    'organization_id' => $order->organization_id,
                    'purchase_order_line_id' => $item['line']->id,
                    'qty' => DecimalQty::positive($item['qty']),
                ]);
            }

            return $advice;
        });
    }

    /** Bucht den Wareneingang aus einem Avis gegen die Bestellzeilen. */
    public function receive(PurchaseOrderAdvice $advice, ?int $actorUserId = null): PurchaseOrderAdvice {
        if (! $advice->status->isOpen()) {
            throw new RuntimeException('Lieferavis ist nicht offen.');
        }

        return DB::transaction(function () use ($advice, $actorUserId): PurchaseOrderAdvice {
            foreach ($advice->lines()->with('line')->get() as $adviceLine) {
                $line = $adviceLine->line;
                if ($line instanceof PurchaseOrderLine) {
                    $this->receipts->receive($line, (string) $adviceLine->qty, actorUserId: $actorUserId);
                }
            }

            $advice->forceFill(['status' => AdviceStatus::Received])->save();

            return $advice;
        });
    }

    public function cancel(PurchaseOrderAdvice $advice): PurchaseOrderAdvice {
        $advice->forceFill(['status' => AdviceStatus::Cancelled])->save();

        return $advice;
    }
}
