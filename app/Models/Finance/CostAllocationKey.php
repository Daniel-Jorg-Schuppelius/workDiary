<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostAllocationKey.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Finance;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Umlageschlüssel (MVP-982): Anteil der Aufwendungen einer Vorkostenstelle, den
 * eine Endkostenstelle im Geschäftsjahr trägt. Rein rechnerisch für die BWA.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $fiscal_year
 * @property int $source_cost_center_id
 * @property int $target_cost_center_id
 * @property numeric-string $share_percent
 * @property int|null $created_by
 */
class CostAllocationKey extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'fiscal_year', 'source_cost_center_id', 'target_cost_center_id', 'share_percent', 'created_by'];

    /** @var array<string, string> */
    protected $casts = ['fiscal_year' => 'integer', 'share_percent' => 'decimal:2'];

    /** @return BelongsTo<CostCenter, $this> */
    public function source(): BelongsTo {
        return $this->belongsTo(CostCenter::class, 'source_cost_center_id');
    }

    /** @return BelongsTo<CostCenter, $this> */
    public function target(): BelongsTo {
        return $this->belongsTo(CostCenter::class, 'target_cost_center_id');
    }
}
