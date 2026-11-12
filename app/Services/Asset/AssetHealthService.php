<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetHealthService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Asset;

use App\Enums\Asset\{AssetHealth, DefectStatus};
use App\Models\Asset\{Asset, AssetBlock};
use Illuminate\Support\Carbon;

/**
 * Leitet `assets.health` aus Sperren, Defekten und überfälligen Terminen ab
 * (MVP-1574). Der Wert wird nie von Hand gesetzt: kritisch bei aktiver Sperre
 * oder blockierendem Defekt, eingeschränkt bei offenem Defekt oder
 * überschrittener Wartung/Prüfung, sonst in Ordnung.
 */
class AssetHealthService {
    public function resolve(Asset $asset): AssetHealth {
        if (AssetBlock::query()->where('asset_id', $asset->id)->active()->exists()
            || $asset->defects()->blocking()->exists()) {
            return AssetHealth::Critical;
        }

        $today = Carbon::today();
        $overdue = ($asset->next_maintenance_on !== null && $asset->next_maintenance_on->lt($today))
            || ($asset->next_inspection_on !== null && $asset->next_inspection_on->lt($today));

        if ($overdue || $asset->defects()->whereIn('status', [DefectStatus::Open->value, DefectStatus::InRepair->value])->exists()) {
            return AssetHealth::Degraded;
        }

        return AssetHealth::Ok;
    }

    public function sync(Asset $asset): AssetHealth {
        $asset->refresh();
        $health = $this->resolve($asset);

        if ($asset->health !== $health) {
            $from = $asset->health;
            // Ohne Modell-Events: der Zustand ist eine Ableitung, kein Bearbeitungsschritt.
            $asset->forceFill(['health' => $health->value])->saveQuietly();
            $asset->audit('asset.healthChanged', ['from' => $from->value, 'to' => $health->value]);
        }

        return $health;
    }

    /** Nachtlauf: Termine laufen ohne Schreibvorgang ab, der den Zustand nachzieht. */
    public function syncDue(): int {
        $today = Carbon::today()->toDateString();
        $changed = 0;

        $candidates = Asset::query()
            ->withoutGlobalScopes()
            ->where(function ($query) use ($today): void {
                $query->where('health', '!=', AssetHealth::Ok->value)
                    ->orWhere('next_maintenance_on', '<', $today)
                    ->orWhere('next_inspection_on', '<', $today);
            });

        foreach ($candidates->lazyById(200) as $asset) {
            $before = $asset->health;
            if ($this->sync($asset) !== $before) {
                $changed++;
            }
        }

        return $changed;
    }
}
