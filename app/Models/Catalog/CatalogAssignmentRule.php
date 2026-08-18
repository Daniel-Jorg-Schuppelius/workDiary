<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogAssignmentRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Catalog;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Vorschlagsregel für Katalogzuordnungen (Feature 109, MVP-640).
 *
 * Sie schlägt vor, sie entscheidet nicht: Was sie setzt, trägt
 * `source = 'rule'` und bleibt jederzeit überschreibbar.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $match_type
 * @property string $match_value
 * @property int $catalog_registry_id
 * @property string $code
 * @property int $priority
 * @property bool $active
 */
class CatalogAssignmentRule extends Model {
    use BelongsToOrganization;
    use HasSqid;

    /** Leistungsbereich der Position (StLB-Nummer) — die verlässlichste Grundlage. */
    public const MATCH_WORK_CATEGORY = 'work_category';

    /** Wort im Kurztext — schwächer, aber oft die einzige Handhabe. */
    public const MATCH_KEYWORD = 'keyword';

    public const MATCH_TYPES = [self::MATCH_WORK_CATEGORY, self::MATCH_KEYWORD];

    protected $table = 'catalog_assignment_rules';

    protected $fillable = [
        'organization_id', 'match_type', 'match_value', 'catalog_registry_id',
        'code', 'priority', 'active', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'priority' => 'integer',
        'active' => 'boolean',
    ];

    /** @return BelongsTo<CatalogRegistry, $this> */
    public function registry(): BelongsTo {
        return $this->belongsTo(CatalogRegistry::class, 'catalog_registry_id');
    }
}
