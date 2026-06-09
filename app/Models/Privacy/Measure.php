<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Measure.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Privacy;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Massnahme (Folge-/Abhilfemassnahme) zu einem Vorfall oder einer DSFA.
 * Verfolgung bis zur wirksamen Erledigung.
 *
 * @property int $id
 * @property int $organization_id
 */
class Measure extends Model {
    use BelongsToOrganization;

    protected $table = 'privacy_measures';

    protected $fillable = [
        'organization_id',
        'incident_id',
        'activity_id',
        'title',
        'description',
        'due_at',
        'status',
        'assigned_user_id',
        'completed_at',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'due_at' => 'date',
        'completed_at' => 'datetime',
    ];

    /** @return BelongsTo<Incident, $this> */
    public function incident(): BelongsTo {
        return $this->belongsTo(Incident::class, 'incident_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedUser(): BelongsTo {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function isOverdue(): bool {
        $due = $this->getAttribute('due_at');

        return $this->getAttribute('status') !== 'done'
            && $due !== null
            && $due->isPast();
    }
}
