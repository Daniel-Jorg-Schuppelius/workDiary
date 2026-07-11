<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisReview.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Crisis;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nachbereitung (Feature 070, MVP-221/222): Zusammenfassung, Lessons
 * Learned und Folgemaßnahmen — genau eine je Krisenakte.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $crisis_case_id
 * @property string $summary
 * @property string|null $lessons
 * @property string|null $follow_up
 * @property int|null $reviewed_by
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 */
class CrisisReview extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'crisis_case_id', 'summary', 'lessons', 'follow_up', 'reviewed_by', 'reviewed_at'];

    /** @var array<string, string> */
    protected $casts = ['reviewed_at' => 'datetime'];

    /** @return BelongsTo<CrisisCase, $this> */
    public function crisisCase(): BelongsTo {
        return $this->belongsTo(CrisisCase::class, 'crisis_case_id');
    }
}
