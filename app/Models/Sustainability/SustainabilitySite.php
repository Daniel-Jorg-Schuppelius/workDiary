<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilitySite.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Sustainability;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Eigener Standort mit Bezugsgrößen (MVP-929) — Bezug der Aktivitätsdaten und
 * Grundlage der Intensitäten (je m², je beschäftigter Person).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $code
 * @property string|null $area_m2
 * @property int|null $headcount
 * @property bool $is_active
 * @property string|null $note
 * @property int|null $created_by
 */
class SustainabilitySite extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'name', 'code', 'area_m2', 'headcount', 'is_active', 'note', 'created_by'];

    /** @var array<string, string> */
    protected $casts = ['area_m2' => 'decimal:2', 'headcount' => 'integer', 'is_active' => 'boolean'];

    /** @return MorphMany<SustainabilityActivityRecord, $this> */
    public function activities(): MorphMany {
        return $this->morphMany(SustainabilityActivityRecord::class, 'subject');
    }
}
