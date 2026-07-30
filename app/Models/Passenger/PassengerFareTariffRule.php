<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PassengerFareTariffRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Passenger;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zuschlags-/Abschlagsregel eines Tarifs (MVP-456): fester Betrag oder
 * Prozentsatz, optional an Bedingungen gebunden (Zeitfenster, Anforderungen).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $tariff_id
 * @property string $code
 * @property string $label
 * @property string $kind
 * @property numeric-string|null $amount
 * @property numeric-string|null $percent
 * @property array<string, mixed>|null $conditions
 * @property int $sort_order
 * @property bool $active
 */
class PassengerFareTariffRule extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    public const KIND_SURCHARGE = 'surcharge';

    public const KIND_DISCOUNT = 'discount';

    protected $fillable = [
        'organization_id',
        'tariff_id',
        'code',
        'label',
        'kind',
        'amount',
        'percent',
        'conditions',
        'sort_order',
        'active',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'percent' => 'decimal:3',
        'conditions' => 'array',
        'sort_order' => 'integer',
        'active' => 'boolean',
    ];

    /** @return BelongsTo<PassengerFareTariff, $this> */
    public function tariff(): BelongsTo {
        return $this->belongsTo(PassengerFareTariff::class, 'tariff_id');
    }

    /** @return array<string, mixed> */
    public function snapshot(): array {
        return [
            'code' => $this->code,
            'label' => $this->label,
            'kind' => $this->kind,
            'amount' => $this->amount !== null ? (string) $this->amount : null,
            'percent' => $this->percent !== null ? (string) $this->percent : null,
        ];
    }
}
