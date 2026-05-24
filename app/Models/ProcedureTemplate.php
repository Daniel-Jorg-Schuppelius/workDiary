<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureTemplate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization};
use Database\Factories\ProcedureTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Vorlage einer Prozedur (Update, Wartung, Inbetriebnahme, ...) mit
 * versionierten Schrittdefinitionen (MVP-025 §3.1).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string|null $domain
 * @property bool $active
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProcedureTemplateVersion> $versions
 */
class ProcedureTemplate extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<ProcedureTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'description',
        'domain',
        'active',
    ];

    protected $casts = [
        'active' => 'bool',
    ];

    /** @return HasMany<ProcedureTemplateVersion, $this> */
    public function versions(): HasMany {
        return $this->hasMany(ProcedureTemplateVersion::class)->orderBy('version');
    }
}
