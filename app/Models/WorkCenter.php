<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkCenter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;

/**
 * Arbeitsplatz / Maschine für die Kapazitätsplanung (Feature 047/048, E7).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property int $capacity_minutes
 * @property int $setup_minutes
 * @property bool $active
 */
class WorkCenter extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'name',
        'code',
        'capacity_minutes',
        'setup_minutes',
        'active',
    ];

    protected $casts = [
        'capacity_minutes' => 'integer',
        'setup_minutes' => 'integer',
        'active' => 'boolean',
    ];
}
