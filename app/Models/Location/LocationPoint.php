<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocationPoint.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Location;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property Carbon $recorded_at
 * @property string $lat
 * @property string $lng
 * @property int|null $accuracy_m
 * @property string $source
 * @property string|null $ingest_batch_id
 * @property Carbon|null $processed_at
 */
class LocationPoint extends Model {
    use BelongsToOrganization;

    public const SOURCE_OWNTRACKS = 'owntracks';

    public const SOURCE_TRACCAR = 'traccar';

    public const SOURCE_GOOGLE = 'google';

    public const SOURCE_BROWSER = 'browser';

    protected $fillable = [
        'organization_id',
        'user_id',
        'recorded_at',
        'lat',
        'lng',
        'accuracy_m',
        'source',
        'ingest_batch_id',
        'processed_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'processed_at' => 'datetime',
        'accuracy_m' => 'integer',
        // Persönliche Bewegungsspur at-rest verschlüsselt (Spalten als text).
        // ACHTUNG: bulk insert() umgeht Casts – Punkte daher via create()
        // schreiben (siehe LocationIngestService), sonst landen sie im Klartext.
        'lat' => 'encrypted',
        'lng' => 'encrypted',
    ];

    /** @param Builder<LocationPoint> $query */
    public function scopeUnprocessed(Builder $query): void {
        $query->whereNull('processed_at');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
