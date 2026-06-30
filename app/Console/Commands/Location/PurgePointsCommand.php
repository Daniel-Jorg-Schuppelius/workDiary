<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurgePointsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Location;

use App\Models\Location\LocationPoint;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Löscht die rohe GPS-Spur (location_points) nach Ablauf der Aufbewahrungsfrist.
 * Nur bereits verarbeitete Punkte werden entfernt; abgeleitete Besuche und
 * Zeitbuchungen bleiben erhalten (Datenminimierung, Art. 5 DSGVO).
 */
class PurgePointsCommand extends Command {
    protected $signature = 'location:purge-points
        {--days= : Aufbewahrungsfrist in Tagen (Default aus config/location.php)}';

    protected $description = 'Entfernt verarbeitete Standort-Rohpunkte, die älter als die Aufbewahrungsfrist sind.';

    public function handle(): int {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) config('location.retention_days', 90);

        if ($days <= 0) {
            $this->info('Aufbewahrung unbegrenzt (retention_days <= 0) – nichts zu löschen.');

            return self::SUCCESS;
        }

        $cutoff = Carbon::now()->subDays($days);

        $deleted = LocationPoint::query()
            ->whereNotNull('processed_at')
            ->where('recorded_at', '<', $cutoff)
            ->delete();

        $this->info("Gelöscht: {$deleted} Standort-Rohpunkte älter als {$days} Tage.");

        return self::SUCCESS;
    }
}
