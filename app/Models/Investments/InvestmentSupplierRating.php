<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentSupplierRating.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Investments;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Supplier\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bewertung eines Lieferanten nach einer Investition (MVP-928), je Merkmal
 * 1 (schlecht) bis 5 (sehr gut).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $investment_case_id
 * @property int $supplier_id
 * @property int $schedule_score
 * @property int $cost_score
 * @property int $quality_score
 * @property string|null $note
 * @property int|null $rated_by
 */
class InvestmentSupplierRating extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const CRITERIA = ['schedule_score', 'cost_score', 'quality_score'];

    protected $fillable = ['organization_id', 'investment_case_id', 'supplier_id', 'schedule_score', 'cost_score', 'quality_score', 'note', 'rated_by'];

    /** @var array<string, string> */
    protected $casts = ['schedule_score' => 'integer', 'cost_score' => 'integer', 'quality_score' => 'integer'];

    /** @return BelongsTo<InvestmentCase, $this> */
    public function investmentCase(): BelongsTo {
        return $this->belongsTo(InvestmentCase::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }

    public function average(): float {
        return round(($this->schedule_score + $this->cost_score + $this->quality_score) / 3, 1);
    }
}
