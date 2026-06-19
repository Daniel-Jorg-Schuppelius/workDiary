<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialSubstitute.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Manufacturing\SubstituteStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ersatzmaterial-Abweichung (Feature 048, Fehlmaterialprozess).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property numeric-string $quantity
 * @property SubstituteStatus $status
 * @property int|null $approved_by
 */
class MaterialSubstitute extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'manufacturing_order_id',
        'manufacturing_order_material_id',
        'planned_article_id',
        'planned_variant_id',
        'substitute_article_id',
        'substitute_variant_id',
        'quantity',
        'status',
        'reason',
        'requested_by',
        'approved_by',
        'decided_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'quantity' => 'decimal:4',
        'status' => SubstituteStatus::class,
        'decided_at' => 'datetime',
    ];

    /** @return BelongsTo<ManufacturingOrder, $this> */
    public function order(): BelongsTo {
        return $this->belongsTo(ManufacturingOrder::class, 'manufacturing_order_id');
    }
}
