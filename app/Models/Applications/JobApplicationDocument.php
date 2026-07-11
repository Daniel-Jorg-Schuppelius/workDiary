<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobApplicationDocument.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Applications;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bewerberunterlage (Feature 068, MVP-190): Verknüpfung Bewerbungsakte →
 * DMS-Dokument; Zugriff läuft ausschließlich über recruiting.*-Rechte.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $job_application_id
 * @property int $document_id
 * @property string|null $label
 */
class JobApplicationDocument extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'job_application_id', 'document_id', 'label'];

    /** @return BelongsTo<JobApplication, $this> */
    public function application(): BelongsTo {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo {
        return $this->belongsTo(Document::class);
    }
}
