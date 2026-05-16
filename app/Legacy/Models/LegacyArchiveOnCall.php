<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyArchiveOnCall.php
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyArchiveOnCall newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyArchiveOnCall newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyArchiveOnCall query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyArchiveOnCall whereBis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyArchiveOnCall whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyArchiveOnCall whereUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyArchiveOnCall whereVon($value)
 */
class LegacyArchiveOnCall extends Model {
    protected $connection = 'legacy';

    protected $table = 'a_bereit';

    public $timestamps = false;

    protected $fillable = [];

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected function casts(): array {
        return [
            'id' => 'integer',
            'von' => 'date',
            'bis' => 'date',
        ];
    }

    public function mitarbeiter(): BelongsTo {
        return $this->belongsTo(LegacyUser::class, 'user', 'id');
    }
}
