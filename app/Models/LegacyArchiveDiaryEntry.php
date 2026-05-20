<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user
 * @property-read LegacyUser|null $mitarbeiter
 * @property string|null $inhalt
 * @property string|null $antwort
 * @property Carbon|null $von
 * @property Carbon|null $bis
 * @property Carbon|null $aktuell
 * @property int|null $gelesen
 */
class LegacyArchiveDiaryEntry extends Model {
    protected $connection = 'legacy';

    protected $table = 'a_tagebuch';

    public $timestamps = false;

    protected $fillable = [];

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

    /** @return BelongsTo<LegacyUser, $this> */
    public function mitarbeiter(): BelongsTo {
        return $this->belongsTo(LegacyUser::class, 'user', 'id');
    }

    public function statusLabel(): string {
        return match ($this->gelesen) {
            -1 => __('Erledigt'),
            1 => __('Bestätigt'),
            2 => __('Offen'),
            3 => __('Problem'),
            default => __('Unbekannt'),
        };
    }
}
