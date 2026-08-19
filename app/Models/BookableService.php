<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BookableService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Buchbare Leistungsart (Feature 087): kuratiert je Organisation — nichts
 * ist automatisch buchbar.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $title
 * @property string|null $description
 * @property int $duration_minutes
 * @property int $lead_time_hours
 * @property int $cancel_hours
 * @property int $buffer_minutes
 * @property int|null $site_id
 * @property int|null $required_qualification_id
 * @property bool $active
 * @property int|null $created_by
 */
class BookableService extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'title', 'description', 'duration_minutes',
        'lead_time_hours', 'cancel_hours', 'buffer_minutes', 'site_id',
        'required_qualification_id', 'active', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'duration_minutes' => 'integer',
        'lead_time_hours' => 'integer',
        'cancel_hours' => 'integer',
        'buffer_minutes' => 'integer',
        'active' => 'boolean',
    ];

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo {
        return $this->belongsTo(Site::class);
    }

    /** Frühester buchbarer Zeitpunkt nach Vorlauf. */
    public function earliestStart(): Carbon {
        return Carbon::now()->addHours($this->lead_time_hours);
    }
}
