<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentOption.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Investments;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{Document, Supplier};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Variante/Angebot einer Investition (Feature 069, MVP-201): bleibt als
 * Entscheidungsgrundlage erhalten, auch wenn später anders beschafft wird.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $investment_case_id
 * @property string $title
 * @property int|null $supplier_id
 * @property string $one_time_cost
 * @property string $recurring_cost_yearly
 * @property int|null $delivery_weeks
 * @property int|null $useful_life_years
 * @property int|null $quality_score
 * @property string|null $risk_note
 * @property bool $recommended
 * @property string|null $note
 * @property int|null $document_id
 */
class InvestmentOption extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'investment_case_id', 'title', 'supplier_id',
        'one_time_cost', 'recurring_cost_yearly', 'delivery_weeks',
        'useful_life_years', 'quality_score', 'risk_note', 'recommended',
        'note', 'document_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'recommended' => 'boolean',
        'one_time_cost' => 'decimal:2',
        'recurring_cost_yearly' => 'decimal:2',
        'delivery_weeks' => 'integer',
        'useful_life_years' => 'integer',
        'quality_score' => 'integer',
    ];

    /** @return BelongsTo<InvestmentCase, $this> */
    public function investmentCase(): BelongsTo {
        return $this->belongsTo(InvestmentCase::class, 'investment_case_id');
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo {
        return $this->belongsTo(Document::class);
    }
}
