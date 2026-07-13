<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Problem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany};

/**
 * Problem (Feature 065, MVP-156): Ursachenobjekt hinter Incidents —
 * eigenes Objekt mit Ticket-Pivot, NIE ein Ticket-Duplikat. Incidents
 * schließen Probleme nie automatisch (entkoppelt, DoD).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $title
 * @property string $status
 * @property string $visibility
 * @property \Illuminate\Support\Carbon|null $effectiveness_check_due_at
 * @property \Illuminate\Support\Carbon|null $effectiveness_checked_at
 */
class Problem extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const STATUSES = ['open', 'analyzing', 'known_error', 'resolved', 'closed'];

    protected $fillable = [
        'organization_id', 'title', 'description', 'owner_id', 'status',
        'root_cause', 'evidence', 'workaround', 'permanent_fix', 'visibility',
        'effectiveness_check_due_at', 'effectiveness_checked_at', 'effectiveness_result',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'effectiveness_check_due_at' => 'datetime',
        'effectiveness_checked_at' => 'datetime',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'open', 'visibility' => 'internal'];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsToMany<ServiceTicket, $this> */
    public function tickets(): BelongsToMany {
        return $this->belongsToMany(ServiceTicket::class, 'problem_ticket')->withTimestamps();
    }

    /**
     * Changes, die dieses Problem beheben sollen (MVP-157);
     * Gegenstück zu {@see Change::problem()}.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Change, $this>
     */
    public function changes(): \Illuminate\Database\Eloquent\Relations\HasMany {
        return $this->hasMany(Change::class, 'problem_id');
    }
}
