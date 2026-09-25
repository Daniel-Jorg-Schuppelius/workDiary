<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockCountSyncHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory\Sync;

use App\Enums\User\Permission as P;
use App\Models\Inventory\StockCount;
use App\Models\Platform\User;
use App\Services\Inventory\StocktakeService;
use App\Services\Sync\Contracts\SyncCommandHandler;
use App\Support\Sqid;
use Illuminate\Support\Facades\{Gate, Validator};
use RuntimeException;

/**
 * Offline erfasste Inventur-Scans (MVP-898). Jeder Befehl addiert wie der
 * Online-Scan; die Idempotenz über die client_uuid verhindert doppeltes
 * Zählen beim erneuten Flush.
 */
final class StockCountSyncHandler implements SyncCommandHandler {
    public function __construct(private readonly StocktakeService $stocktake) {}

    /** @return list<string> */
    public function types(): array {
        return ['inventory.count'];
    }

    public function handle(User $user, string $type, array $payload): string {
        if ($type !== 'inventory.count') {
            throw new RuntimeException('Unbekannter Sync-Befehlstyp: ' . $type);
        }
        if (! Gate::forUser($user)->allows(P::InventoryPost->value)) {
            throw new RuntimeException((string) __('inventory.count_ui.forbidden'));
        }

        $data = Validator::make($payload, [
            'count' => ['required', 'string'],
            'code' => ['required', 'string', 'max:120'],
            'qty' => ['required', 'numeric', 'gt:0'],
        ])->validate();

        $count = StockCount::query()->whereKey(Sqid::decode(StockCount::class, $data['count']))->first();
        if (! $count instanceof StockCount) {
            throw new RuntimeException((string) __('inventory.count_ui.not_found'));
        }

        return 'stock_count_lines:' . $this->stocktake->addByScan($count, (string) $data['code'], (string) $data['qty'], $user->id)->id;
    }
}
