<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EmployeeDraft.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Applications;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mitarbeiter-Entwurf (Feature 068, MVP-193 / Flexibilitätsplan D4):
 * kontrollierte Zwischenstufe zwischen Zusage und Live-User — erst die
 * bewusste Übernahme löst den bestehenden Invite-Pfad aus. Kein
 * „User im Draft-Status" im users-Table.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $job_application_id
 * @property string $name
 * @property string|null $email
 * @property \Illuminate\Support\Carbon|null $planned_start_on
 * @property array<int, string>|null $qualifications
 * @property array<int, array{label: string, done: bool}>|null $checklist
 * @property string|null $note
 * @property string $status
 * @property int|null $invited_user_id
 * @property int|null $created_by
 */
class EmployeeDraft extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const STATUSES = ['draft', 'invited', 'discarded'];

    protected $fillable = [
        'organization_id', 'job_application_id', 'name', 'email',
        'planned_start_on', 'qualifications', 'checklist', 'note', 'status',
        'invited_user_id', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'planned_start_on' => 'date',
        'qualifications' => 'array',
        'checklist' => 'array',
    ];

    /** @return BelongsTo<JobApplication, $this> */
    public function application(): BelongsTo {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    /** @return BelongsTo<User, $this> */
    public function invitedUser(): BelongsTo {
        return $this->belongsTo(User::class, 'invited_user_id');
    }
}
