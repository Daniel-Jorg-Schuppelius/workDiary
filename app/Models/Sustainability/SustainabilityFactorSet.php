<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityFactorSet.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Sustainability;

use App\Models\Concerns\HasSqid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Faktoren-Set (Feature 071, MVP-228): versionierte Bibliothek je Quelle/
 * Region/Jahr. org NULL = ausgeliefertes Standard-Set (UBA/DEFRA);
 * Org-Sets überschreiben bei der Auflösung (analog PerDiemRate).
 *
 * ACHTUNG: bewusst OHNE BelongsToOrganization-Scope — globale Sets müssen
 * für alle Mandanten lesbar bleiben; die Auflösung filtert explizit.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $name
 * @property string|null $source
 * @property string $region
 * @property int $year
 * @property bool $active
 */
class SustainabilityFactorSet extends Model {
    use HasSqid;

    protected $fillable = ['organization_id', 'name', 'source', 'region', 'year', 'active'];

    /** @var array<string, string> */
    protected $casts = ['year' => 'integer', 'active' => 'boolean'];

    /** @return HasMany<SustainabilityEmissionFactor, $this> */
    public function factors(): HasMany {
        return $this->hasMany(SustainabilityEmissionFactor::class, 'factor_set_id');
    }
}
