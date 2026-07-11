<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApplicationRequirement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Applications;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unterlagenanforderung/Eignungsnachweis einer Ausschreibungsakte
 * (Feature 068, MVP-185): Checkliste mit Frist, Status und DMS-Verknüpfung.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $application_opportunity_id
 * @property string $label
 * @property string $kind
 * @property bool $required
 * @property \Illuminate\Support\Carbon|null $due_on
 * @property string $status
 * @property int|null $document_id
 * @property string|null $note
 * @property int $position
 */
class ApplicationRequirement extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const STATUSES = ['open', 'in_progress', 'done', 'not_applicable'];

    protected $fillable = [
        'organization_id', 'application_opportunity_id', 'label', 'kind',
        'required', 'due_on', 'status', 'document_id', 'note', 'position',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'required' => 'boolean',
        'due_on' => 'date',
        'position' => 'integer',
    ];

    /** @return BelongsTo<ApplicationOpportunity, $this> */
    public function opportunity(): BelongsTo {
        return $this->belongsTo(ApplicationOpportunity::class, 'application_opportunity_id');
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo {
        return $this->belongsTo(Document::class);
    }
}
