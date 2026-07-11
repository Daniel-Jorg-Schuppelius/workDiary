<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisSituationReport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Crisis;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lagebericht (Feature 070, MVP-214): versioniert und append-only —
 * Korrekturen erzeugen neue Versionen, nie stille Überschreibung.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $crisis_case_id
 * @property int $version
 * @property string $content
 * @property string|null $risks
 * @property string|null $communication_status
 * @property string|null $recovery_status
 * @property int|null $created_by
 */
class CrisisSituationReport extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'crisis_case_id', 'version', 'content', 'risks',
        'communication_status', 'recovery_status', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = ['version' => 'integer'];

    /** @return BelongsTo<CrisisCase, $this> */
    public function crisisCase(): BelongsTo {
        return $this->belongsTo(CrisisCase::class, 'crisis_case_id');
    }
}
