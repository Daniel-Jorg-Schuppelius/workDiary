<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierQuestionnaire.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Supplier;

use App\Casts\FieldSchemaCast;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Services\Fields\FieldSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fragebogen für die Lieferanten-Selbstauskunft (MVP-937); Fragen über das
 * Feldschema, Gültigkeit einer angenommenen Auskunft in Monaten.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $description
 * @property FieldSchema $schema
 * @property int $validity_months
 * @property bool $is_active
 * @property int|null $created_by
 */
class SupplierQuestionnaire extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'name', 'description', 'schema', 'validity_months', 'is_active', 'created_by'];

    /** @var array<string, string> */
    protected $casts = ['schema' => FieldSchemaCast::class, 'validity_months' => 'integer', 'is_active' => 'boolean'];

    /** @return HasMany<SupplierQuestionnaireRequest, $this> */
    public function requests(): HasMany {
        return $this->hasMany(SupplierQuestionnaireRequest::class);
    }
}
