<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegalHold.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Privacy;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};
use Illuminate\Support\Carbon;

/**
 * Legal Hold an Person oder Kunde (MVP-801). Aktiv, solange `released_at` leer ist.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $holdable_type
 * @property int $holdable_id
 * @property string $reason
 * @property string|null $reference
 * @property int|null $placed_by
 * @property Carbon $placed_at
 * @property int|null $released_by
 * @property Carbon|null $released_at
 * @property string|null $release_reason
 * @property-read Model|null $holdable
 * @property-read User|null $placedBy
 * @property-read User|null $releasedBy
 */
class LegalHold extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'legal_holds';

    protected $fillable = [
        'organization_id',
        'holdable_type',
        'holdable_id',
        'reason',
        'reference',
        'placed_by',
        'placed_at',
        'released_by',
        'released_at',
        'release_reason',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'reason' => 'encrypted',
        'release_reason' => 'encrypted',
        'placed_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    /** @return MorphTo<Model, $this> */
    public function holdable(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function placedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'placed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function releasedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'released_by');
    }

    /**
     * @param  Builder<LegalHold>  $query
     * @return Builder<LegalHold>
     */
    public function scopeActive(Builder $query): Builder {
        return $query->whereNull('released_at');
    }

    public function isActive(): bool {
        return $this->released_at === null;
    }
}
