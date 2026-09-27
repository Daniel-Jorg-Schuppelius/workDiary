<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BranchProfileVariant.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Classification;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;

/**
 * Kundenspezifische Variante eines Branchenprofils (MVP-933): Basisprofil,
 * entfernte Bausteine je Abschnitt und ergänzter Profilausschnitt.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $code
 * @property string $label
 * @property string|null $description
 * @property string $base_code
 * @property array<string, list<string>>|null $removals
 * @property array<string, mixed>|null $additions
 * @property int $version
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class BranchProfileVariant extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'code', 'label', 'description', 'base_code', 'removals', 'additions', 'version', 'created_by', 'updated_by'];

    /** @var array<string, string> */
    protected $casts = ['removals' => 'array', 'additions' => 'array', 'version' => 'integer'];
}
