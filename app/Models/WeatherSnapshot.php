<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WeatherSnapshot.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Unveränderlicher Wetter-Messwert eines Ortes an einem Tag (Feature 062,
 * MVP-131). Einmal angelegt, nie geändert — der `updating`-Guard erzwingt die
 * Beweisfestigkeit auf Modell-Ebene (Messwert ≠ nachträgliche Beobachtung).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $geo_lat
 * @property string $geo_lng
 * @property \Illuminate\Support\Carbon $snapshot_date
 * @property string $provider
 * @property \Illuminate\Support\Carbon $fetched_at
 * @property string|null $temp_min
 * @property string|null $temp_max
 * @property string|null $precipitation_mm
 * @property string|null $wind_gust_kmh
 * @property int|null $weather_code
 * @property array<string, mixed> $raw
 * @property int|null $created_by
 */
class WeatherSnapshot extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'geo_lat',
        'geo_lng',
        'snapshot_date',
        'provider',
        'fetched_at',
        'temp_min',
        'temp_max',
        'precipitation_mm',
        'wind_gust_kmh',
        'weather_code',
        'raw',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'snapshot_date' => 'date',
        'fetched_at' => 'datetime',
        'temp_min' => 'decimal:2',
        'temp_max' => 'decimal:2',
        'precipitation_mm' => 'decimal:2',
        'wind_gust_kmh' => 'decimal:2',
        'weather_code' => 'integer',
        'raw' => 'array',
    ];

    protected static function booted(): void {
        static::updating(function (): void {
            throw new RuntimeException('Weather snapshots are immutable.');
        });
    }
}
