<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VehicleAnnualCost.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Fleet;

use App\Casts\MoneyCast;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * Sonstige Jahreskosten eines Fahrzeugs (Leasing, Versicherung, Steuer,
 * Reparaturen, AfA) für den 1-%-Vergleich (MVP-993); Energie kommt aus den
 * Tank- und Ladebelegen.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $vehicle_id
 * @property int $year
 * @property Money $cost_amount
 * @property CurrencyCode $currency
 * @property string|null $note
 * @property int|null $created_by
 */
class VehicleAnnualCost extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'vehicle_id', 'year', 'currency', 'cost_amount', 'note', 'created_by'];

    /** @var array<string, string> */
    protected $casts = [
        'year' => 'integer',
        'currency' => CurrencyCode::class,
        'cost_amount' => MoneyCast::class . ':currency,2',
    ];
}
