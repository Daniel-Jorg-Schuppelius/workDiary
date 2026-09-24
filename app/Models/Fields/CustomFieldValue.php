<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomFieldValue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Fields;

use App\Casts\FieldValuesCast;
use App\Models\Concerns\{Auditable, BelongsToOrganization};
use App\Services\Fields\FieldValues;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Wertesatz der benutzerdefinierten Felder eines Datensatzes (MVP-868),
 * genau einer je Träger-Datensatz.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $subject_type
 * @property int $subject_id
 * @property FieldValues $values
 * @property int $schema_version
 */
class CustomFieldValue extends Model {
    use Auditable;
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'subject_type', 'subject_id', 'values', 'schema_version'];

    /** @var array<string, string> */
    protected $casts = [
        'values' => FieldValuesCast::class,
        'schema_version' => 'integer',
    ];

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo {
        return $this->morphTo();
    }
}
