<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentDeviation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Investments;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Abweichung (Feature 069, MVP-206): Budgetüberschreitung, Termin,
 * Umfang oder Abbruch — mit eigener Begründung und Entscheidung;
 * genehmigte Budgeterhöhungen laufen über einen NEUEN Antrag (Nachtrag).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $investment_case_id
 * @property string $kind
 * @property string $description
 * @property string|null $amount_delta
 * @property string $status
 * @property int|null $decided_by
 * @property \Illuminate\Support\Carbon|null $decided_at
 * @property string|null $decision_note
 * @property int|null $created_by
 */
class InvestmentDeviation extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const KINDS = ['budget', 'schedule', 'scope', 'cancellation'];

    protected $fillable = [
        'organization_id', 'investment_case_id', 'kind', 'description',
        'amount_delta', 'status', 'decided_by', 'decided_at', 'decision_note',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = ['decided_at' => 'datetime', 'amount_delta' => 'decimal:2'];

    /** @return BelongsTo<InvestmentCase, $this> */
    public function investmentCase(): BelongsTo {
        return $this->belongsTo(InvestmentCase::class, 'investment_case_id');
    }
}
