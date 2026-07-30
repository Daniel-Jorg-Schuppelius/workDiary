<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecipeProfile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Recipes;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\ProcedureTemplateVersion;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Partyservice-Aufsatz einer Rezeptversion (MVP-455): Grundausbeute,
 * Ausgabeeinheit/Portionen und manuelle Allergen-Abweichungen (mit
 * Begründung). Existiert NUR im Partyservice-Kontext — technische Rezepturen
 * anderer Gewerke tragen kein Profil und bleiben unberührt.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $procedure_template_version_id
 * @property numeric-string $base_portions
 * @property numeric-string|null $base_yield_qty
 * @property string|null $yield_unit
 * @property array{added?: list<string>, removed?: list<string>, reason?: string}|null $allergen_overrides
 */
class RecipeProfile extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'procedure_template_version_id',
        'base_portions',
        'base_yield_qty',
        'yield_unit',
        'allergen_overrides',
    ];

    protected $casts = [
        'base_portions' => 'decimal:2',
        'base_yield_qty' => 'decimal:3',
        'allergen_overrides' => 'array',
    ];

    /** @return BelongsTo<ProcedureTemplateVersion, $this> */
    public function version(): BelongsTo {
        return $this->belongsTo(ProcedureTemplateVersion::class, 'procedure_template_version_id');
    }

    /** @return list<string> */
    public function addedAllergens(): array {
        return array_values(array_filter((array) ($this->allergen_overrides['added'] ?? []), 'is_string'));
    }

    /** @return list<string> */
    public function removedAllergens(): array {
        return array_values(array_filter((array) ($this->allergen_overrides['removed'] ?? []), 'is_string'));
    }

    public function overrideReason(): ?string {
        $reason = trim((string) ($this->allergen_overrides['reason'] ?? ''));

        return $reason !== '' ? $reason : null;
    }
}
