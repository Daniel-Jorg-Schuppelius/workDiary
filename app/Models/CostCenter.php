<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostCenter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;

/**
 * Kostenstellen-Stammdaten (Feature 069, Flexibilitätsplan D2): kleines
 * Model mit Code/Label je Organisation. Nutzer referenzieren per
 * nullable FK + Label-Fallback; `CostCenterRule` (Zeitexport) bleibt
 * vorerst auf String und wird per Backfill migriert.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $code
 * @property string $label
 * @property bool $active
 */
class CostCenter extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'code', 'label', 'active'];

    /** @var array<string, string> */
    protected $casts = ['active' => 'boolean'];
}
