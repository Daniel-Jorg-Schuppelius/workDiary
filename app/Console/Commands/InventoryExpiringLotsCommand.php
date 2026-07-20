<?php
/*
 * Created on   : Sun Jul 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryExpiringLotsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Concerns\IteratesOrganizations;
use App\Enums\Notification\NotificationEvent;
use App\Models\Organization;
use App\Services\Inventory\LotService;
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * MHD-Überwachung (Feature 048 E2; Vollaudit 2026-07, M19): meldet je
 * Organisation Chargen-Bewertungsschichten mit Restbestand, deren
 * Mindesthaltbarkeit innerhalb des Vorlaufs fällt — LotService::expiringUntil
 * war zuvor toter Code ohne Aufrufer. Dedupliziert je Charge über den
 * NotificationDispatcher (Betreff enthält die Lot-ID).
 */
class InventoryExpiringLotsCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'inventory:expiring-lots {--days=30 : Vorlauf in Tagen} ' . self::ORGANIZATION_OPTION;

    protected $description = 'Meldet Chargen mit ablaufendem MHD (Feature 048 E2, MHD-Überwachung).';

    public function handle(NotificationDispatcher $dispatcher, LotService $lots): int {
        $days = max(1, (int) $this->option('days'));
        $until = Carbon::now()->addDays($days);
        $sent = 0;

        foreach ($this->organizationsToProcess() as $organization) {
            $sent += (int) $this->withOrganizationContext($organization, function (Organization $organization) use ($dispatcher, $lots, $until, $days): int {
                $count = 0;
                foreach ($lots->expiringUntil($until) as $layer) {
                    $lot = $layer->lot;
                    if ($lot === null || (int) $layer->organization_id !== (int) $organization->id) {
                        continue;
                    }

                    $count += $dispatcher->notify(
                        NotificationEvent::InventoryLotExpiring,
                        $lot,
                        null,
                        [
                            'title' => (string) __('notification.message.inventory_lot_expiring_title', [
                                'lot' => (string) $lot->lot_no,
                                'date' => Carbon::parse((string) $layer->best_before)->format('d.m.Y'),
                            ]),
                            'title_key' => 'notification.message.inventory_lot_expiring_title',
                            'title_params' => [
                                'lot' => (string) $lot->lot_no,
                                'date' => Carbon::parse((string) $layer->best_before)->format('d.m.Y'),
                            ],
                            'message' => (string) __('Restbestand :qty — Vorlauf :days Tage.', [
                                'qty' => (string) $layer->qty_remaining,
                                'days' => $days,
                            ]),
                            'url' => route('inventory.lots'),
                        ],
                        dedup: true,
                    );
                }

                return $count;
            });
        }

        $this->info(sprintf('%d MHD-Meldung(en) versendet.', $sent));

        return self::SUCCESS;
    }
}
