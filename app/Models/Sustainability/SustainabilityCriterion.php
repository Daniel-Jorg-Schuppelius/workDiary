<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityCriterion.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Sustainability;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;

/**
 * ESG-Kriterium (Feature 071, MVP-224): org-eigener Katalog je Dimension
 * (Umwelt/Soziales/Governance) mit Gewichtung.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $dimension
 * @property string $label
 * @property string|null $description
 * @property int $weight
 * @property bool $active
 */
class SustainabilityCriterion extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'sustainability_criteria';

    public const DIMENSIONS = ['environment', 'social', 'governance'];

    protected $fillable = ['organization_id', 'dimension', 'label', 'description', 'weight', 'active'];

    /** @var array<string, string> */
    protected $casts = ['weight' => 'integer', 'active' => 'boolean'];
}
