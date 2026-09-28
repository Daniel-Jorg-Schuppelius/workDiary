<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetPosition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Asset;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Gemeldete Position eines Geräts (MVP-975), z. B. aus einem Telematik-Export.
 * Soll-Ort und Abweichung trägt ein Fachmodul nach (Verleih: Einsatzort).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $asset_id
 * @property Carbon $recorded_at
 * @property numeric-string $lat
 * @property numeric-string $lng
 * @property string $source
 * @property string|null $expected_label
 * @property int|null $deviation_m
 * @property int|null $created_by
 */
class AssetPosition extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'asset_id', 'recorded_at', 'lat', 'lng', 'source',
        'expected_label', 'deviation_m', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'recorded_at' => 'datetime',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'deviation_m' => 'integer',
    ];

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }
}
