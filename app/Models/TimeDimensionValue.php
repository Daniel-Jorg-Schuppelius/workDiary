<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeDimensionValue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MVP-514 P2 (Feature 103): Wert einer freien Mandanten-Dimension —
 * mit optionalem Gültigkeitszeitraum und `external_id` als Anker für die
 * vorgesehene Provider-Synchronisation (ERP-Kostenträger o. Ä.).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $dimension_type_id
 * @property string $name
 * @property string|null $external_id
 * @property \Illuminate\Support\Carbon|null $valid_from
 * @property \Illuminate\Support\Carbon|null $valid_until
 * @property array<string, mixed>|null $metadata
 */
class TimeDimensionValue extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'time_dimension_values';

    protected $fillable = [
        'organization_id',
        'dimension_type_id',
        'name',
        'external_id',
        'valid_from',
        'valid_until',
        'metadata',
    ];

    protected $casts = [
        'valid_from' => 'date:Y-m-d',
        'valid_until' => 'date:Y-m-d',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<TimeDimensionType, $this> */
    public function type(): BelongsTo {
        return $this->belongsTo(TimeDimensionType::class, 'dimension_type_id');
    }

    /** Gültig am Stichtag (Datumsgrenzen inklusiv; ohne Grenzen immer). */
    public function isValidOn(CarbonInterface $date): bool {
        return ($this->valid_from === null || $this->valid_from->lte($date))
            && ($this->valid_until === null || $this->valid_until->gte($date));
    }
}
