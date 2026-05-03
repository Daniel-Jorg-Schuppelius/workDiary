<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property-read \App\Models\Legacy\LegacyUser|null $user
 * @property \Illuminate\Support\Carbon|null $von
 * @property \Illuminate\Support\Carbon|null $bis
 */
class LegacyNotdienst extends Model {
    protected $connection = 'legacy';

    protected $table = 'notdnst';

    public $timestamps = false;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected function casts(): array {
        return [
            'von' => 'date',
            'bis' => 'date',
        ];
    }

    public function mitarbeiter(): BelongsTo {
        return $this->belongsTo(LegacyUser::class, 'user', 'id');
    }

    public function user(): BelongsTo {
        return $this->belongsTo(LegacyUser::class, 'user', 'id');
    }
}
