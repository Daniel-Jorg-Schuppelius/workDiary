<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JointControllerAgreement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Privacy;

use App\Enums\Privacy\AgreementStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany};

/**
 * Vereinbarung gemeinsam Verantwortlicher (GVV, Art. 26) mit
 * Zuständigkeitsmatrix (`responsibilities`) und Verknuepfung zu
 * Verarbeitungstaetigkeiten.
 *
 * @property array<string, mixed>|null $responsibilities
 * @property int $id
 * @property int $organization_id
 */
class JointControllerAgreement extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'privacy_joint_controller_agreements';

    protected $fillable = [
        'organization_id',
        'partner_id',
        'title',
        'version',
        'status',
        'valid_from',
        'valid_until',
        'review_due_at',
        'responsibilities',
        'contact_point',
        'essence_provided',
        'document_path',
        'document_name',
        'notes',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => AgreementStatus::class,
        'responsibilities' => 'array',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'review_due_at' => 'date',
        'essence_provided' => 'boolean',
    ];

    /** @return BelongsTo<Processor, $this> */
    public function partner(): BelongsTo {
        return $this->belongsTo(Processor::class, 'partner_id');
    }

    /** @return BelongsToMany<ProcessingActivity, $this> */
    public function activities(): BelongsToMany {
        return $this->belongsToMany(ProcessingActivity::class, 'privacy_gvv_activity', 'gvv_id', 'activity_id');
    }

    public function isReviewOverdue(): bool {
        $due = $this->getAttribute('review_due_at');

        return $due !== null && $due->isPast();
    }
}
