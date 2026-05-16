<?php

/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyNotdienst.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property LegacyUser|null $user
 * @property Carbon|null $von
 * @property Carbon|null $bis
 * @property-read LegacyUser|null $mitarbeiter
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyNotdienst newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyNotdienst newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyNotdienst query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyNotdienst whereBis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyNotdienst whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyNotdienst whereUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyNotdienst whereVon($value)
 */
class LegacyNotdienst extends Model
{
    protected $connection = 'legacy';

    protected $table = 'notdnst';

    public $timestamps = false;

    protected $fillable = ['user', 'von', 'bis'];

    protected $primaryKey = 'id';

    protected function casts(): array
    {
        return [
            'von' => 'date',
            'bis' => 'date',
        ];
    }

    public function mitarbeiter(): BelongsTo
    {
        return $this->belongsTo(LegacyUser::class, 'user', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->mitarbeiter();
    }
}
