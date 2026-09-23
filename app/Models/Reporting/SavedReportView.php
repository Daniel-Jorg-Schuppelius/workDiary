<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SavedReportView.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Benannte, teilbare Report-Ansicht (MVP-529): Route + Filter-Parameter
 * unter einem Namen — persönlich oder org-weit geteilt.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $created_by
 * @property string $name
 * @property string $route_name
 * @property array<string, mixed>|null $params
 * @property bool $is_shared
 */
class SavedReportView extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'created_by',
        'name',
        'route_name',
        'params',
        'is_shared',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'params' => 'array',
        'is_shared' => 'boolean',
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Ziel-URL der Ansicht. */
    public function targetUrl(): string {
        return route($this->route_name, $this->params ?? []);
    }
}
