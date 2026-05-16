<?php

/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyUser.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uname
 * @property string $userpw
 * @property string $email
 * @property-read Collection<int, LegacyDiaryEntry> $entries
 * @property-read int|null $entries_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyUser query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyUser whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyUser whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyUser whereUname($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyUser whereUserpw($value)
 */
class LegacyUser extends Model
{
    protected $connection = 'legacy';

    protected $table = 'user';

    public $timestamps = false;

    protected $fillable = ['uname', 'userpw', 'email'];

    protected $primaryKey = 'id';

    public function entries(): HasMany
    {
        return $this->hasMany(LegacyDiaryEntry::class, 'user', 'id');
    }
}
