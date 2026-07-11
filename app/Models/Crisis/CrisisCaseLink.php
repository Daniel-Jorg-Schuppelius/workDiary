<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisCaseLink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Crisis;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Verknüpfung Krisenakte → Fachvorgang (Feature 070, MVP-218):
 * ServiceTicket, ISMS-/Datenschutzvorfall, Restore-Test, ProcedureRun …
 * Die Fachmodule bleiben führend.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $crisis_case_id
 * @property string $linkable_type
 * @property int $linkable_id
 * @property string|null $note
 */
class CrisisCaseLink extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'crisis_case_id', 'linkable_type', 'linkable_id', 'note', 'created_by'];

    /** @return BelongsTo<CrisisCase, $this> */
    public function crisisCase(): BelongsTo {
        return $this->belongsTo(CrisisCase::class, 'crisis_case_id');
    }

    /** @return MorphTo<Model, $this> */
    public function linkable(): MorphTo {
        return $this->morphTo();
    }
}
