<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendanceCheckpoint.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Attendance\CheckpointKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Check-in-Punkt: QR-Code oder NFC-Aufkleber an Standort oder Fahrzeug (MVP-800).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property CheckpointKind $kind
 * @property int|null $site_id
 * @property int|null $vehicle_id
 * @property string $token
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int|null $radius_m
 * @property bool $active
 * @property int|null $created_by
 * @property-read Site|null $site
 * @property-read Vehicle|null $vehicle
 */
class AttendanceCheckpoint extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $table = 'attendance_checkpoints';

    protected $fillable = [
        'organization_id',
        'name',
        'kind',
        'site_id',
        'vehicle_id',
        'token',
        'latitude',
        'longitude',
        'radius_m',
        'active',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => CheckpointKind::class,
        'latitude' => 'float',
        'longitude' => 'float',
        'radius_m' => 'integer',
        'active' => 'boolean',
    ];

    public static function newToken(): string {
        return Str::random(40);
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Mittelpunkt der Ortsprüfung: eigene Koordinaten, sonst die des Standorts.
     *
     * @return array{0: float, 1: float}|null
     */
    public function center(): ?array {
        if ($this->latitude !== null && $this->longitude !== null) {
            return [$this->latitude, $this->longitude];
        }
        $site = $this->site;
        if ($site instanceof Site && $site->geo_lat !== null && $site->geo_lng !== null) {
            return [(float) $site->geo_lat, (float) $site->geo_lng];
        }

        return null;
    }

    public function requiresLocation(): bool {
        return $this->radius_m !== null && $this->radius_m > 0;
    }
}
