<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudDocumentRoute.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\CloudIntake;

use App\Enums\CloudIntake\CloudIntakeRouteTarget;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Priorisierte Ordnerregel (Feature 080, MVP-352): bindet ein Pfadmuster
 * (`**`-Glob + Whitelist-Variablen) an einen Zielbereich. Regeln ordnen NUR
 * vorhandene Objekte zu — nie Auto-Anlage; unsichere Treffer landen in der
 * Integrations-Inbox ({@see \App\Services\CloudIntake\RoutePatternValidator}).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $connection_id
 * @property int $priority
 * @property string $path_pattern
 * @property array<int, string>|null $allowed_extensions
 * @property int|null $max_file_size
 * @property CloudIntakeRouteTarget $target
 * @property string|null $document_type
 * @property string|null $target_ref_type
 * @property int|null $target_ref_id
 * @property bool $auto_version
 * @property bool $active
 */
class CloudDocumentRoute extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'connection_id',
        'priority',
        'path_pattern',
        'allowed_extensions',
        'max_file_size',
        'target',
        'document_type',
        'target_ref_type',
        'target_ref_id',
        'auto_version',
        'active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'target' => CloudIntakeRouteTarget::class,
        'allowed_extensions' => 'array',
        'auto_version' => 'boolean',
        'active' => 'boolean',
    ];

    /** @return BelongsTo<CloudDocumentConnection, $this> */
    public function connection(): BelongsTo {
        return $this->belongsTo(CloudDocumentConnection::class, 'connection_id');
    }

    /** @return MorphTo<Model, $this> */
    public function targetRef(): MorphTo {
        return $this->morphTo('target_ref');
    }
}
