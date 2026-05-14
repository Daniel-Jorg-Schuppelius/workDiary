<?php

namespace App\Legacy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user
 * @property-read LegacyUser|null $mitarbeiter
 * @property Carbon|null $von
 * @property Carbon|null $bis
 */
class LegacyArchiveOnCall extends Model
{
    protected $connection = 'legacy';

    protected $table = 'a_bereit';

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
