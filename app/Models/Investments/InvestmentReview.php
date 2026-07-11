<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentReview.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Investments;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nachbewertung (Feature 069, MVP-207): tatsächlicher Nutzen,
 * Wirtschaftlichkeit, Lessons Learned und Folgemaßnahmen — genau eine
 * je Investitionsakte.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $investment_case_id
 * @property string|null $benefit_result
 * @property string|null $economics_result
 * @property string|null $lessons
 * @property string|null $follow_up
 * @property int|null $reviewed_by
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 */
class InvestmentReview extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'investment_case_id', 'benefit_result',
        'economics_result', 'lessons', 'follow_up', 'reviewed_by', 'reviewed_at',
    ];

    /** @var array<string, string> */
    protected $casts = ['reviewed_at' => 'datetime'];

    /** @return BelongsTo<InvestmentCase, $this> */
    public function investmentCase(): BelongsTo {
        return $this->belongsTo(InvestmentCase::class, 'investment_case_id');
    }
}
