<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GeocodeCache.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $query_hash
 * @property string $query
 * @property string|null $address_formatted
 * @property string $lat
 * @property string $lng
 * @property string $provider
 * @property array<string, mixed>|null $raw
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class GeocodeCache extends Model {
    protected $table = 'geocode_cache';

    protected $fillable = [
        'query_hash',
        'query',
        'address_formatted',
        'lat',
        'lng',
        'provider',
        'raw',
        'expires_at',
    ];

    protected $casts = [
        'raw' => 'array',
        'expires_at' => 'datetime',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
    ];

    public static function hashFor(string $query): string {
        $hash = CryptoHelper::hash(mb_strtolower(trim($query)));

        return $hash;
    }

    public function isExpired(?Carbon $now = null): bool {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->lessThan($now ?? Carbon::now());
    }
}
