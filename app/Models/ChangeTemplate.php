<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChangeTemplate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;

/**
 * Standard-Change-Vorlage (Feature 065, P7): freigegeben + versioniert;
 * der Change friert den Vorlagenstand als template_snapshot ein.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property int $version
 * @property bool $approved
 */
class ChangeTemplate extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'name', 'implementation_plan', 'test_plan',
        'rollback_plan', 'version', 'approved',
    ];

    /** @var array<string, string> */
    protected $casts = ['approved' => 'boolean', 'version' => 'integer'];

    /** @var array<string, mixed> */
    protected $attributes = ['version' => 1, 'approved' => false];

    /**
     * Changes, die aus dieser Vorlage entstanden sind (MVP-157) — der
     * jeweilige Vorlagenstand liegt eingefroren im template_snapshot.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Change, $this>
     */
    public function changes(): \Illuminate\Database\Eloquent\Relations\HasMany {
        return $this->hasMany(Change::class, 'change_template_id');
    }
}
