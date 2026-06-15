<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetAssignment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\AssetAssignmentFactory;
use Illuminate\Database\Eloquent\{Builder, Model, SoftDeletes};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ausgabe-/Rückgabe-Vorgang eines Assets (Feature 009).
 *
 * Eine offene Zuweisung (returned_at = null) bedeutet, dass das Asset aktuell
 * ausgegeben ist. Pro Asset darf es höchstens eine offene Zuweisung geben; das
 * wird vom AssetAssignmentService erzwungen.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $asset_id
 * @property int|null $assigned_to_user_id
 * @property int|null $assigned_to_team_id
 * @property int|null $diary_entry_id
 * @property Carbon $checked_out_at
 * @property int|null $checked_out_by_user_id
 * @property Carbon|null $expected_return_at
 * @property Carbon|null $returned_at
 * @property int|null $returned_by_user_id
 * @property string|null $condition_out
 * @property string|null $condition_in
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AssetAssignment extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<AssetAssignmentFactory> */
    use HasFactory;

    use HasSqid;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'asset_id',
        'assigned_to_user_id',
        'assigned_to_team_id',
        'diary_entry_id',
        'checked_out_at',
        'checked_out_by_user_id',
        'expected_return_at',
        'returned_at',
        'returned_by_user_id',
        'condition_out',
        'condition_in',
        'note',
    ];

    protected $casts = [
        'checked_out_at' => 'datetime',
        'expected_return_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedToUser(): BelongsTo {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function assignedToTeam(): BelongsTo {
        return $this->belongsTo(Team::class, 'assigned_to_team_id');
    }

    /** @return BelongsTo<DiaryEntry, $this> */
    public function diaryEntry(): BelongsTo {
        return $this->belongsTo(DiaryEntry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function checkedOutBy(): BelongsTo {
        return $this->belongsTo(User::class, 'checked_out_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function returnedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'returned_by_user_id');
    }

    public function isOpen(): bool {
        return $this->returned_at === null;
    }

    public function isOverdue(?Carbon $reference = null): bool {
        if ($this->returned_at !== null || $this->expected_return_at === null) {
            return false;
        }

        return ($reference ?? Carbon::now())->greaterThan($this->expected_return_at);
    }

    /**
     * @param  Builder<AssetAssignment>  $query
     * @return Builder<AssetAssignment>
     */
    public function scopeOpen(Builder $query): Builder {
        return $query->whereNull('returned_at');
    }
}
