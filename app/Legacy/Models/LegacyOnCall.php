<?php

namespace App\Legacy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property-read LegacyUser|null $user
 * @property Carbon|null $von
 * @property Carbon|null $bis
 */
class LegacyOnCall extends Model
{
    protected $connection = 'legacy';

    protected $table = 'bereit';

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
