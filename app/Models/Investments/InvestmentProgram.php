<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentProgram.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Investments;

use App\Enums\Investments\InvestmentProgramStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Mehrjähriges Investitionsprogramm (MVP-927): Jahresbudgets und die
 * zugeordneten Investitionen; Planjahr je Investition bzw. Beginn der Akte.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $description
 * @property int $starts_year
 * @property int $ends_year
 * @property CurrencyCode $currency
 * @property InvestmentProgramStatus $status
 * @property int|null $responsible_user_id
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class InvestmentProgram extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'name', 'description', 'starts_year', 'ends_year', 'currency', 'status',
        'responsible_user_id', 'created_by', 'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'starts_year' => 'integer',
        'ends_year' => 'integer',
        'currency' => CurrencyCode::class,
        'status' => InvestmentProgramStatus::class,
    ];

    /** @return HasMany<InvestmentProgramBudget, $this> */
    public function budgets(): HasMany {
        return $this->hasMany(InvestmentProgramBudget::class)->orderBy('year');
    }

    /** @return HasMany<InvestmentCase, $this> */
    public function cases(): HasMany {
        return $this->hasMany(InvestmentCase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /** @return list<int> */
    public function years(): array {
        return range($this->starts_year, max($this->starts_year, $this->ends_year));
    }
}
