<?php

/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyArchiveNotdienst.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user
 * @property Carbon|null $von
 * @property Carbon|null $bis
 * @property-read LegacyUser|null $mitarbeiter
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyArchiveNotdienst newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyArchiveNotdienst newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyArchiveNotdienst query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyArchiveNotdienst whereBis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyArchiveNotdienst whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyArchiveNotdienst whereUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyArchiveNotdienst whereVon($value)
 */
class LegacyArchiveNotdienst extends Model
{
    protected $connection = 'legacy';

    protected $table = 'a_notdnst';

    public $timestamps = false;

    protected $fillable = [];

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'von' => 'date',
            'bis' => 'date',
        ];
    }

    public function mitarbeiter(): BelongsTo
    {
        return $this->belongsTo(LegacyUser::class, 'user', 'id');
    }
}
