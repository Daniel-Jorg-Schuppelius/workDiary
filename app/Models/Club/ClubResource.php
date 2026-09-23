<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubResource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\ClubResourceKind;
use App\Models\{Asset, Room};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\{Collection, Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Sportstätte/Ressource (Feature 159, MVP-853) als Baum: Halle mit
 * Teilflächen und Tischen, Platz, Bahn/Stand, Boot/Gerät. Optional an einen
 * Raum (Belegung teilt sich mit dem Terminkalender) oder ein Asset (Sperren
 * über das gemeinsame Sperrmodell) gebunden.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $parent_id
 * @property int|null $room_id
 * @property int|null $asset_id
 * @property string $name
 * @property ClubResourceKind $kind
 * @property int $capacity
 * @property int $setup_minutes
 * @property int $teardown_minutes
 * @property bool $requires_clearance
 * @property bool $is_active
 * @property int $sort_order
 * @property string|null $notes
 */
class ClubResource extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const MAX_DEPTH = 4;

    protected $fillable = [
        'organization_id', 'parent_id', 'room_id', 'asset_id', 'name', 'kind', 'capacity', 'setup_minutes', 'teardown_minutes',
        'requires_clearance', 'is_active', 'sort_order', 'notes',
    ];

    protected $casts = [
        'kind' => ClubResourceKind::class,
        'capacity' => 'integer',
        'setup_minutes' => 'integer',
        'teardown_minutes' => 'integer',
        'requires_clearance' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @return BelongsTo<ClubResource, $this> */
    public function parent(): BelongsTo {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<ClubResource, $this> */
    public function children(): HasMany {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo {
        return $this->belongsTo(Room::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return HasMany<ClubResourceBooking, $this> */
    public function bookings(): HasMany {
        return $this->hasMany(ClubResourceBooking::class);
    }

    /** @return HasMany<ClubResourceClosure, $this> */
    public function closures(): HasMany {
        return $this->hasMany(ClubResourceClosure::class);
    }

    /** @return HasMany<ClubResourceClearance, $this> */
    public function clearances(): HasMany {
        return $this->hasMany(ClubResourceClearance::class);
    }

    /**
     * Übergeordnete Ressourcen (Halle über der Teilfläche), nächste zuerst.
     *
     * @return Collection<int, ClubResource>
     */
    public function ancestors(): Collection {
        $result = new Collection();
        $current = $this;
        for ($i = 0; $i < self::MAX_DEPTH; $i++) {
            $parent = $current->parent_id !== null ? self::query()->find($current->parent_id) : null;
            if (! $parent instanceof self) {
                break;
            }
            $result->push($parent);
            $current = $parent;
        }

        return $result;
    }

    /**
     * Alle untergeordneten Ressourcen (Teilflächen, Tische) beliebiger Tiefe.
     *
     * @return Collection<int, ClubResource>
     */
    public function descendants(): Collection {
        $result = new Collection();
        $ids = [$this->id];
        for ($i = 0; $i < self::MAX_DEPTH && $ids !== []; $i++) {
            $level = self::query()->whereIn('parent_id', $ids)->get();
            if ($level->isEmpty()) {
                break;
            }
            $result = $result->merge($level);
            $ids = $level->pluck('id')->all();
        }

        return $result;
    }

    public function fullName(): string {
        $parent = $this->parent_id !== null ? $this->parent : null;

        return $parent instanceof self ? $parent->fullName() . ' › ' . $this->name : $this->name;
    }
}
