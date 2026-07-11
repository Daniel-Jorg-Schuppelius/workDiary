<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApplicationSubmission.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Applications;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Einreichungspaket (Feature 068, MVP-187): versionierter, gehashter
 * Snapshot des eingereichten Stands — nachträgliche Änderungen erzeugen
 * neue Versionen, nie stille Überschreibung.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $application_opportunity_id
 * @property int $version
 * @property string $channel
 * @property array<string, mixed> $snapshot
 * @property string $sha256
 * @property string|null $note
 * @property int|null $submitted_by
 */
class ApplicationSubmission extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'application_opportunity_id', 'version', 'channel',
        'snapshot', 'sha256', 'note', 'submitted_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'version' => 'integer',
        'snapshot' => 'array',
    ];

    /** @return BelongsTo<ApplicationOpportunity, $this> */
    public function opportunity(): BelongsTo {
        return $this->belongsTo(ApplicationOpportunity::class, 'application_opportunity_id');
    }
}
