<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CarryForwardNote.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\CarryForward;

use App\Casts\PeriodKeyCast;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use App\Support\Period\PeriodKey;
use Database\Factories\CarryForward\CarryForwardNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Merker für die nächste Periode (Feature 201, MVP-1645). Gehört zur Reihe
 * (`series_key`), gilt ab der Zielperiode und bleibt offen, bis ihn jemand an
 * einem Vorkommen abhakt. Angelegt und erledigt wird nur über
 * {@see \App\Services\CarryForward\CarryForwardNotes}; der Verlauf steht im Audit
 * (`created`, `carry_forward_note.resolved`, `deleted`).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $series_key
 * @property PeriodKey|null $period_key Zielperiode „ab“
 * @property Carbon $valid_from Beginn der Zielperiode
 * @property string $note
 * @property string|null $origin_type
 * @property int|null $origin_id
 * @property PeriodKey|null $origin_period_key
 * @property int|null $created_by
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property string|null $resolved_occurrence_type
 * @property int|null $resolved_occurrence_id
 * @property string|null $resolution_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CarryForwardNote extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<CarryForwardNoteFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'series_key',
        'period_key',
        'valid_from',
        'note',
        'origin_type',
        'origin_id',
        'origin_period_key',
        'created_by',
        'resolved_at',
        'resolved_by',
        'resolved_occurrence_type',
        'resolved_occurrence_id',
        'resolution_note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'period_key' => PeriodKeyCast::class,
        'origin_period_key' => PeriodKeyCast::class,
        'valid_from' => 'date',
        'resolved_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpen(): bool {
        return $this->resolved_at === null;
    }

    /**
     * Abhaken erscheint im Audit als eigenes Ereignis statt als „Geändert“.
     *
     * @param  array<string, mixed>  $changes
     */
    protected function resolveAuditEvent(string $event, array $changes): string {
        if ($event === 'updated' && array_key_exists('resolved_at', $changes) && $changes['resolved_at'] !== null) {
            return 'carry_forward_note.resolved';
        }

        return $event;
    }
}
