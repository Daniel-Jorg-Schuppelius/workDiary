<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningScormPackage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Services\Learning\Scorm\ScormManifest;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Importiertes SCORM-Paket an einer Lerneinheit (Feature 149, MVP-743).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_unit_id
 * @property string $title
 * @property string $version
 * @property string $storage_path
 * @property string|null $launch_href
 * @property string $manifest_hash
 * @property int $file_count
 * @property int $size_bytes
 */
class LearningScormPackage extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'learning_unit_id',
        'title',
        'version',
        'storage_path',
        'launch_href',
        'manifest_hash',
        'file_count',
        'size_bytes',
        'uploaded_by_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'file_count' => 'integer',
        'size_bytes' => 'integer',
    ];

    /** @return BelongsTo<LearningUnit, $this> */
    public function unit(): BelongsTo {
        return $this->belongsTo(LearningUnit::class, 'learning_unit_id');
    }

    /** @return HasMany<LearningScormState, $this> */
    public function states(): HasMany {
        return $this->hasMany(LearningScormState::class, 'learning_scorm_package_id');
    }

    /** Runtime-Objekt, das der Inhalt im Fenster sucht. */
    public function apiObjectName(): string {
        return $this->version === ScormManifest::VERSION_2004 ? 'API_1484_11' : 'API';
    }

    public function isScorm2004(): bool {
        return $this->version === ScormManifest::VERSION_2004;
    }
}
