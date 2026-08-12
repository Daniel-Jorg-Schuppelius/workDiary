<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeDimensionType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MVP-514 P2 (Feature 103): frei definierbarer Dimensionstyp eines
 * Mandanten (z. B. „ERP-Auftrag", „Anlage") — nur für Dimensionen ohne
 * vorhandenes Modell; bestehende Entitäten (Projekt, Kostenstelle, …)
 * werden direkt referenziert, nie gespiegelt.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $code
 * @property string $name
 * @property bool $enabled
 */
class TimeDimensionType extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'time_dimension_types';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    /** @return HasMany<TimeDimensionValue, $this> */
    public function values(): HasMany {
        return $this->hasMany(TimeDimensionValue::class, 'dimension_type_id')->orderBy('name');
    }
}
