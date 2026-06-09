<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CaseAssignment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Whistleblowing;

use App\Enums\Whistleblowing\CaseRole;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Explizite Bearbeiter-Zuweisung an einen Fall. Massgeblich fuer die
 * Autorisierung: nur aktiv (revoked_at null) zugewiesene Personen sehen Inhalte.
 */
class CaseAssignment extends Model {
    use BelongsToOrganization;

    protected $table = 'whistleblowing_case_assignments';

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'case_id',
        'user_id',
        'role',
        'assigned_by',
        'assigned_at',
        'revoked_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'role' => CaseRole::class,
        'assigned_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /** @return BelongsTo<WhistleblowingCase, $this> */
    public function case(): BelongsTo {
        return $this->belongsTo(WhistleblowingCase::class, 'case_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }
}
