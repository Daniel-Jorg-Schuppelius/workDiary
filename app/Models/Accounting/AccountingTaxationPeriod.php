<?php
/*
 * Created on   : Sat Aug 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingTaxationPeriod.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Enums\Finance\TaxationMethod;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Abschnitt einer Versteuerungsart (Feature 125, MVP-679).
 *
 * @property TaxationMethod $method
 * @property array<string, mixed>|null $changeover
 */
class AccountingTaxationPeriod extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'method',
        'valid_from',
        'valid_to',
        'reason',
        'changeover',
        'actor_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'method' => TaxationMethod::class,
        'valid_from' => 'date',
        'valid_to' => 'date',
        'changeover' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
