<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomFieldDefinition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Fields;

use App\Casts\FieldSchemaCast;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Services\Fields\FieldSchema;
use App\Support\MorphMap;
use Illuminate\Database\Eloquent\Model;

/**
 * Feldschema einer Organisation für einen Träger (MVP-868): `subject_alias`
 * ist der Morph-Alias (Tabellenname) des Trägers, `schema` die Definitionen,
 * `version` zählt bei jeder Schemaänderung hoch (Werte tragen die Version,
 * mit der sie erfasst wurden).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $subject_alias
 * @property FieldSchema $schema
 * @property int $version
 * @property bool $is_active
 */
class CustomFieldDefinition extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'subject_alias', 'schema', 'version', 'is_active'];

    /** @var array<string, string> */
    protected $casts = [
        'schema' => FieldSchemaCast::class,
        'version' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return class-string|null */
    public function subjectClass(): ?string {
        return MorphMap::classFor($this->subject_alias);
    }
}
