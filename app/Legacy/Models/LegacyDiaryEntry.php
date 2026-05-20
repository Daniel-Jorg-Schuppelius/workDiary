<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyDiaryEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $aktuell
 * @property int|null $user
 * @property Carbon|null $von
 * @property Carbon|null $bis
 * @property string $inhalt
 * @property string $antwort
 * @property int $gelesen
 * @property string $sms
 * @property-read LegacyUser|null $author
 *
 * @method static Builder<static>|LegacyDiaryEntry active()
 * @method static Builder<static>|LegacyDiaryEntry newModelQuery()
 * @method static Builder<static>|LegacyDiaryEntry newQuery()
 * @method static Builder<static>|LegacyDiaryEntry query()
 * @method static Builder<static>|LegacyDiaryEntry whereAktuell($value)
 * @method static Builder<static>|LegacyDiaryEntry whereAntwort($value)
 * @method static Builder<static>|LegacyDiaryEntry whereBis($value)
 * @method static Builder<static>|LegacyDiaryEntry whereGelesen($value)
 * @method static Builder<static>|LegacyDiaryEntry whereId($value)
 * @method static Builder<static>|LegacyDiaryEntry whereInhalt($value)
 * @method static Builder<static>|LegacyDiaryEntry whereSms($value)
 * @method static Builder<static>|LegacyDiaryEntry whereUser($value)
 * @method static Builder<static>|LegacyDiaryEntry whereVon($value)
 */
class LegacyDiaryEntry extends Model {
    protected $connection = 'legacy';

    protected $table = 'tagebuch';

    public $timestamps = false;

    protected $fillable = ['user', 'von', 'bis', 'inhalt', 'antwort', 'gelesen', 'sms', 'aktuell'];

    protected $primaryKey = 'id';

    protected function casts(): array {
        return [
            'aktuell' => 'datetime',
            'von' => 'datetime',
            'bis' => 'datetime',
            'gelesen' => 'integer',
        ];
    }

    public function author(): BelongsTo {
        return $this->belongsTo(LegacyUser::class, 'user', 'id');
    }

    public function user(): BelongsTo {
        return $this->author();
    }

    #[Scope]
    protected function active(Builder $query): void {
        $query->where('bis', '>=', now()->subDays(30));
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

    public function statusTone(): string {
        return match ($this->gelesen) {
            -1 => 'done',
            1 => 'progress',
            2 => 'open',
            3 => 'alert',
            default => 'neutral',
        };
    }
}
