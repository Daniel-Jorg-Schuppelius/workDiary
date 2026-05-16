<?php

/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyArchiveDiaryEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $aktuell
 * @property int $user
 * @property Carbon|null $von
 * @property Carbon|null $bis
 * @property string $inhalt
 * @property string $antwort
 * @property int $gelesen
 * @property string $sms
 * @property-read LegacyUser|null $mitarbeiter
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyArchiveDiaryEntry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyArchiveDiaryEntry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyArchiveDiaryEntry query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyArchiveDiaryEntry whereAktuell($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyArchiveDiaryEntry whereAntwort($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyArchiveDiaryEntry whereBis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyArchiveDiaryEntry whereGelesen($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyArchiveDiaryEntry whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyArchiveDiaryEntry whereInhalt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyArchiveDiaryEntry whereSms($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyArchiveDiaryEntry whereUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyArchiveDiaryEntry whereVon($value)
 */
class LegacyArchiveDiaryEntry extends Model
{
    protected $connection = 'legacy';

    protected $table = 'a_tagebuch';

    public $timestamps = false;

    protected $fillable = [];

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'aktuell' => 'datetime',
            'von' => 'datetime',
            'bis' => 'datetime',
            'gelesen' => 'integer',
        ];
    }

    public function mitarbeiter(): BelongsTo
    {
        return $this->belongsTo(LegacyUser::class, 'user', 'id');
    }

    public function statusLabel(): string
    {
        return match ($this->gelesen) {
            -1 => __('Erledigt'),
            1 => __('Bestätigt'),
            2 => __('Offen'),
            3 => __('Problem'),
            default => __('Unbekannt'),
        };
    }
}
