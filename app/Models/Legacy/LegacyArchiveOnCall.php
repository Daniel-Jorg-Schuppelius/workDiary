<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyArchiveOnCall extends Model {
    protected $connection = 'legacy';

    protected $table = 'a_bereit';

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
