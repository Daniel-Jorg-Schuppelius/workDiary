<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisMapPoint.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Crisis;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;

/**
 * Eigener Lagepunkt auf der Krisenkarte (MVP-963), etwa Schadensort, Sammelpunkt oder Sperrung.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $crisis_case_id
 * @property string $label
 * @property string $kind
 * @property numeric-string $lat
 * @property numeric-string $lng
 * @property string|null $note
 * @property int|null $created_by
 */
class CrisisMapPoint extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const KINDS = ['incident', 'assembly', 'closure', 'resource', 'other'];

    protected $fillable = ['organization_id', 'crisis_case_id', 'label', 'kind', 'lat', 'lng', 'note', 'created_by'];

    /** @var array<string, string> */
    protected $casts = ['lat' => 'decimal:7', 'lng' => 'decimal:7'];
}
