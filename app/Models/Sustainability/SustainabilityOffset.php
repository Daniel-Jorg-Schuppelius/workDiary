<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityOffset.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Sustainability;

use App\Enums\Sustainability\SustainabilityOffsetKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Klimanachweis (MVP-961): Kompensationszertifikat, Herkunftsnachweis für
 * Grünstrom oder Klimabeitrag. Wird nie mit den Emissionen verrechnet.
 *
 * @property int $id
 * @property int $organization_id
 * @property SustainabilityOffsetKind $kind
 * @property string $provider
 * @property string|null $standard
 * @property string|null $project_name
 * @property numeric-string $quantity_t
 * @property int|null $vintage_year
 * @property int $claim_year
 * @property Carbon|null $retired_on
 * @property string|null $registry_reference
 * @property string|null $note
 * @property int|null $created_by
 */
class SustainabilityOffset extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'kind', 'provider', 'standard', 'project_name', 'quantity_t', 'vintage_year', 'claim_year', 'retired_on', 'registry_reference', 'note', 'created_by'];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => SustainabilityOffsetKind::class,
        'quantity_t' => 'decimal:3',
        'vintage_year' => 'integer',
        'claim_year' => 'integer',
        'retired_on' => 'date',
    ];

    /** Kompensation ohne Stilllegung und Registerverweis ist kein belastbarer Nachweis. */
    public function isEvidenced(): bool {
        return $this->kind !== SustainabilityOffsetKind::Compensation || ($this->retired_on !== null && $this->registry_reference !== null);
    }
}
