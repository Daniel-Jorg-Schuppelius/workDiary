<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PresenceStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Time;

use App\Enums\Attendance\PresenceStatusKind;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Platform\User;
use Carbon\CarbonInterface;
use Database\Factories\Time\PresenceStatusFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Selbstauskunft am Anwesenheits-Board (Feature 103, MVP-1669): aktueller
 * Stand einer Person, höchstens eine Zeile je Person. Bewusst ohne Audit und
 * ohne Journal — es gibt keinen Verlauf und keine Auswertung. Geschrieben
 * und gelöscht wird nur über {@see \App\Services\Attendance\PresenceStatusService}.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property PresenceStatusKind $kind
 * @property Carbon|null $valid_until
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PresenceStatus extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<PresenceStatusFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'user_id',
        'kind',
        'valid_until',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => PresenceStatusKind::class,
        'valid_until' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /**
     * Noch gültig: ohne Ende oder mit Ende nach $now.
     *
     * @param  Builder<PresenceStatus>  $query
     */
    public function scopeCurrentAt(Builder $query, CarbonInterface $now): void {
        $query->where(static fn (Builder $q) => $q->whereNull('valid_until')->orWhere('valid_until', '>', $now));
    }

    public function isCurrentAt(CarbonInterface $now): bool {
        return $this->valid_until === null || $this->valid_until->greaterThan($now);
    }
}
