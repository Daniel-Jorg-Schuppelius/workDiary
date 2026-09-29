<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingExchangeRate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Casts\DecimalCast;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Monatskurs einer Fremdwährung (Feature 125, MVP-1012): Einheiten der Währung
 * je Einheit der Basiswährung, wie das BMF die Umsatzsteuer-Umrechnungskurse
 * veröffentlicht (§ 16 Abs. 6 UStG).
 *
 * @property int $id
 * @property int $organization_id
 * @property CurrencyCode $currency
 * @property Carbon $period Erster Tag des Monats
 * @property Decimal $rate
 * @property string|null $source
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class AccountingExchangeRate extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'currency',
        'period',
        'rate',
        'source',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'currency' => CurrencyCode::class,
        'period' => 'date',
        'rate' => DecimalCast::class . ':6',
    ];
}
