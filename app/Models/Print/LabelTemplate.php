<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LabelTemplate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;

/**
 * Etikettenvorlage (Feature 047/048, E5).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $paper_size
 * @property string $orientation
 * @property bool $with_qr
 * @property list<string> $fields
 * @property bool $is_default
 */
class LabelTemplate extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    public const FIELDS = ['title', 'subtitle', 'code', 'code_type', 'lines'];

    protected $fillable = [
        'organization_id',
        'name',
        'paper_size',
        'orientation',
        'with_qr',
        'fields',
        'is_default',
    ];

    protected $casts = [
        'with_qr' => 'boolean',
        'fields' => 'array',
        'is_default' => 'boolean',
    ];
}
