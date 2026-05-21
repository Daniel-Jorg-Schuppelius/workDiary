<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Room.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $code
 * @property string|null $building
 * @property string|null $floor
 * @property int|null $capacity
 * @property array<int, string>|null $equipment
 * @property string|null $color
 * @property bool $is_active
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Room extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<RoomFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'code',
        'building',
        'floor',
        'capacity',
        'equipment',
        'color',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'equipment' => 'array',
        'is_active' => 'boolean',
    ];

    /** @return BelongsToMany<Event, $this> */
    public function events(): BelongsToMany {
        return $this->belongsToMany(Event::class, 'event_room')
            ->withPivot(['started_at', 'ended_at', 'setup_minutes_before', 'teardown_minutes_after'])
            ->withTimestamps();
    }

    /** @param Builder<Room> $query */
    public function scopeActive(Builder $query): void {
        $query->where('is_active', true);
    }
}
