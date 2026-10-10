<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrackShipmentsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Shipping;

use App\Models\Shipping\Shipment;
use App\Services\Shipping\ShipmentService;
use DateInterval;
use Illuminate\Console\Command;
use Throwable;

/**
 * Sendungsverfolgung (MVP-1095): offene Sendungen beim Carrier abgleichen.
 * Ein Fehler betrifft nur die eine Sendung; die Anbindung zählt ihn über
 * {@see ShipmentService::refreshTracking()}. Registriert in
 * config/scheduler.php (shipping.track).
 */
class TrackShipmentsCommand extends Command {
    protected $signature = 'shipping:track {--stale=3 : Stunden seit dem letzten Abgleich}';

    protected $description = 'Sendungsverfolgung offener Versandaufträge beim Carrier abgleichen';

    public function handle(ShipmentService $shipping): int {
        $tracked = 0;
        $failed = 0;

        $due = $shipping->dueForTracking(new DateInterval('PT' . max(1, (int) $this->option('stale')) . 'H'));
        foreach ($due->orderBy('id')->cursor() as $shipment) {
            /** @var Shipment $shipment */
            try {
                $shipping->refreshTracking($shipment);
                $tracked++;
            } catch (Throwable) {
                $failed++;
            }
        }

        $this->info("Sendungen abgeglichen: {$tracked}, fehlgeschlagen: {$failed}");

        return self::SUCCESS;
    }
}
