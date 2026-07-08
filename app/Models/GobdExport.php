<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GobdExport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nachweis einer GoBD-Z3-Datenträgerüberlassung (Feature 063, MVP-132). Jede
 * Erzeugung eines Exportpakets wird hier revisionssicher festgehalten (Auditable
 * ⇒ Hash-Kette); die Datei-Hashes belegen die Unveränderlichkeit des Pakets.
 *
 * @property int $id
 * @property int $organization_id
 * @property \Illuminate\Support\Carbon $period_from
 * @property \Illuminate\Support\Carbon $period_to
 * @property array<int, string> $sections
 * @property array<string, string> $file_hashes
 * @property string $package_sha256
 * @property int $record_count
 * @property int|null $created_by
 */
class GobdExport extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'period_from',
        'period_to',
        'sections',
        'file_hashes',
        'package_sha256',
        'record_count',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'sections' => 'array',
        'file_hashes' => 'array',
        'record_count' => 'integer',
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }
}
