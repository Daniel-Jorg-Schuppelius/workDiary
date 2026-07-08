<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsRequirement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Isms;

use App\Enums\Isms\RequirementSource;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\Isms\IsmsRequirementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsToMany, HasMany};

/**
 * Normanforderung (Feature 046, gemeinsamer Kern): versionierte Referenz
 * (Norm + Ausgabe + Referenznummer + eigener Kurztitel — KEINE Normtexte,
 * Urheberrecht!). Normkataloge (config/isms-norms/, siehe
 * {@see \App\Services\Isms\NormProfileRegistry}) werden mit norm/edition
 * des Profils und source catalog importiert; eigene Anforderungen tragen
 * source custom. Die SoA-Aussage je Geltungsbereich liegt im
 * {@see IsmsApplicabilityStatement}; Maßnahmen ({@see IsmsControl})
 * erfüllen Anforderungen n:m über isms_control_requirement.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $norm
 * @property string $edition
 * @property string $ref_no
 * @property string $title
 * @property RequirementSource $source
 */
class IsmsRequirement extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<IsmsRequirementFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'norm',
        'edition',
        'ref_no',
        'title',
        'description',
        'source',
    ];

    protected $casts = [
        'source' => RequirementSource::class,
    ];

    /** Anzeige der Normreferenz, z. B. "ISO/IEC 27001:2022" oder "Eigene". */
    public function normLabel(): string {
        return $this->edition === '-' ? $this->norm : $this->norm . ':' . $this->edition;
    }

    /** @return BelongsToMany<IsmsControl, $this> */
    public function controls(): BelongsToMany {
        return $this->belongsToMany(IsmsControl::class, 'isms_control_requirement', 'requirement_id', 'control_id');
    }

    /** @return HasMany<IsmsApplicabilityStatement, $this> */
    public function statements(): HasMany {
        return $this->hasMany(IsmsApplicabilityStatement::class, 'isms_requirement_id');
    }
}
