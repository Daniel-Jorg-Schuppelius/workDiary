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
class LegacyOnCall extends Model {
    protected $connection = 'legacy';

    protected $table = 'bereit';

    public $timestamps = false;

    protected $fillable = ['user', 'von', 'bis'];

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
        return $this->mitarbeiter();
    }
}
