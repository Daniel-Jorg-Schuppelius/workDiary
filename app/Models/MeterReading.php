<?php
/*
 * Created on   : Thu May 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeterReading.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization};
use Database\Factories\MeterReadingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $asset_id
 * @property Carbon $read_at
 * @property string $value
 * @property string $unit
 * @property string|null $previous_value
 * @property string|null $consumption
 * @property int|null $read_by_user_id
 * @property string|null $photo_path
 * @property string|null $notes
 * @property bool $is_estimated
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MeterReading extends Model {
    /** @use HasFactory<MeterReadingFactory> */
    use Auditable, BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'asset_id',
        'read_at',
        'value',
        'unit',
        'previous_value',
        'consumption',
        'read_by_user_id',
        'photo_path',
        'notes',
        'is_estimated',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'value' => 'decimal:4',
        'previous_value' => 'decimal:4',
        'consumption' => 'decimal:4',
        'is_estimated' => 'bool',
    ];

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<User, $this> */
    public function readBy(): BelongsTo {
        return $this->belongsTo(User::class, 'read_by_user_id');
    }
}
