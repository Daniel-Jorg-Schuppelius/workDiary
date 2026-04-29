<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyArchiveDiaryEntry extends Model {
    protected $connection = 'legacy';

    protected $table = 'a_tagebuch';

    public $timestamps = false;

    protected $guarded = [];

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected function casts(): array {
        return [
            'id' => 'integer',
            'aktuell' => 'datetime',
            'von' => 'datetime',
            'bis' => 'datetime',
            'gelesen' => 'integer',
        ];
    }

    public function mitarbeiter(): BelongsTo {
        return $this->belongsTo(LegacyUser::class, 'user', 'id');
    }
}
