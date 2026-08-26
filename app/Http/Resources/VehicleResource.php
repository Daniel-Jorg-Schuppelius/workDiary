<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VehicleResource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\{Asset, User, Vehicle};
use App\Support\Sqid;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Read-only-Darstellung eines Fahrzeugs (MVP-718).
 *
 * @mixin Vehicle
 */
class VehicleResource extends JsonResource {
    /** @return array<string, mixed> */
    public function toArray(Request $request): array {
        return [
            'id' => $this->sqid,
            'license_plate' => $this->license_plate,
            'label' => $this->label,
            'vehicle_type' => $this->vehicle_type->value,
            'propulsion' => $this->propulsion->value,
            'ownership' => $this->ownership->value,
            'asset_id' => Sqid::encodeOrNull(Asset::class, $this->asset_id),
            'default_user_id' => Sqid::encodeOrNull(User::class, $this->default_user_id),
            'odometer_km' => $this->odometer_km,
            'logbook_mode' => (bool) $this->logbook_mode,
            'subject_to_driving_time_rules' => (bool) $this->subject_to_driving_time_rules,
            'rental_provider' => $this->rental_provider,
            'rental_start' => $this->rental_start?->toDateString(),
            'rental_end' => $this->rental_end?->toDateString(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
