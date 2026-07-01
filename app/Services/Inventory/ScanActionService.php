<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScanActionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\{OwnershipType, ScanAction, StockMovementType, StockState};
use App\Models\{ArticleVariant, StockMovement, Warehouse};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mobile Bestandsbuchung per Scan (Feature 048, E5): löst den Code zur Variante
 * auf und bucht die gewählte Aktion (Eingang/Entnahme/Umlagerung) gegen das
 * append-only Journal. Entnahme und Umlagerung prüfen die Verfügbarkeit.
 */
class ScanActionService {
    public const SCALE = 4;

    public function __construct(
        private readonly BarcodeResolver $resolver,
        private readonly InventoryLedger $ledger,
    ) {}

    /** @param array{actor?: int|null, target?: Warehouse|null} $options */
    public function book(string $code, ScanAction $action, Warehouse $warehouse, string $qty, array $options = []): StockMovement {
        $match = $this->resolver->resolve($code);
        $variant = $match->variant;
        if (! $variant instanceof ArticleVariant) {
            throw new RuntimeException('Unbekannter oder nicht bestandsführender Code: ' . trim($code));
        }

        $qty = $this->positive($qty);
        $actor = $options['actor'] ?? null;

        return match ($action) {
            ScanAction::Receipt => $this->ledger->receipt($variant, $warehouse, $qty, actorUserId: $actor),
            ScanAction::Issue => $this->ledger->issue($variant, $warehouse, $qty, actorUserId: $actor),
            ScanAction::Transfer => $this->transfer($variant, $warehouse, $options['target'] ?? null, $qty, $actor),
        };
    }

    /** @param numeric-string $qty */
    private function transfer(ArticleVariant $variant, Warehouse $from, ?Warehouse $to, string $qty, ?int $actor): StockMovement {
        if (! $to instanceof Warehouse) {
            throw new RuntimeException('Umlagerung ohne Ziel-Lager.');
        }
        if (bccomp($this->ledger->available($variant, $from), $qty, self::SCALE) < 0) {
            throw new RuntimeException('Umlagerung übersteigt den verfügbaren Bestand.');
        }

        return DB::transaction(function () use ($variant, $from, $to, $qty, $actor): StockMovement {
            $this->ledger->post(new StockPosting(
                $variant, $from, StockState::Physical, bcmul($qty, '-1', self::SCALE), StockMovementType::TransferOut,
                OwnershipType::Own, actorUserId: $actor,
            ));

            return $this->ledger->post(new StockPosting(
                $variant, $to, StockState::Physical, $qty, StockMovementType::TransferIn,
                OwnershipType::Own, actorUserId: $actor,
            ));
        });
    }

    /** @return numeric-string */
    private function positive(string $value): string {
        $value = NumberHelper::normalizeDecimalString($value);
        if ($value === '' || ! is_numeric($value)) {
            return '0';
        }

        return bccomp($value, '0', self::SCALE) < 0 ? bcmul($value, '-1', self::SCALE) : $value;
    }
}
