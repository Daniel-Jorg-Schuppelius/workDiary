<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user
 * @property-read \App\Models\Legacy\LegacyUser|null $mitarbeiter
 * @property \Illuminate\Support\Carbon|null $von
 * @property \Illuminate\Support\Carbon|null $bis
 */
class LegacyArchiveNotdienst extends Model {
    protected $connection = 'legacy';

    protected $table = 'a_notdnst';

    public $timestamps = false;

    protected $guarded = [];

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
