<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimCaseLink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Claims;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Polymorphe Fall-Verknüpfung (MVP-247): weitere betroffene oder
 * belegende Objekte jenseits der festen FKs an der Akte.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $claim_case_id
 * @property string $linkable_type
 * @property int $linkable_id
 * @property string $role
 * @property string|null $note
 */
class ClaimCaseLink extends Model {
    use BelongsToOrganization;
    use HasSqid;

    public const ROLES = ['affected', 'evidence', 'follow_up', 'financial', 'recourse'];

    protected $fillable = [
        'organization_id', 'claim_case_id', 'linkable_type', 'linkable_id',
        'role', 'note', 'created_by',
    ];

    /** @return BelongsTo<ClaimCase, $this> */
    public function claimCase(): BelongsTo {
        return $this->belongsTo(ClaimCase::class);
    }

    /** @return MorphTo<\Illuminate\Database\Eloquent\Model, $this> */
    public function linkable(): MorphTo {
        return $this->morphTo();
    }
}
