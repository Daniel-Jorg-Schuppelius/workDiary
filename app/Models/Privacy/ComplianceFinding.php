<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ComplianceFinding.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Privacy;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Einzelner Compliance-/Lueckenbefund (regelbasiert ermittelt oder manuell
 * gepflegt). Drill-down ueber activity/agreement/processor.
 *
 * @property int $organization_id
 */
class ComplianceFinding extends Model {
    use BelongsToOrganization;

    protected $table = 'privacy_compliance_findings';

    protected $fillable = [
        'organization_id',
        'requirement_key',
        'label',
        'category',
        'status',
        'trigger',
        'activity_id',
        'agreement_id',
        'processor_id',
        'responsible_user_id',
        'due_at',
        'justification',
        'auto_detected',
        'detected_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'due_at' => 'date',
        'auto_detected' => 'boolean',
        'detected_at' => 'datetime',
    ];

    /** Stati, die als „offene Luecke" zaehlen (Ampel rot/gelb). */
    public const OPEN_STATUSES = ['missing', 'expiring', 'required', 'in_review'];

    /** @return BelongsTo<ProcessingActivity, $this> */
    public function activity(): BelongsTo {
        return $this->belongsTo(ProcessingActivity::class, 'activity_id');
    }

    /** @return BelongsTo<ProcessingAgreement, $this> */
    public function agreement(): BelongsTo {
        return $this->belongsTo(ProcessingAgreement::class, 'agreement_id');
    }

    /** @return BelongsTo<Processor, $this> */
    public function processor(): BelongsTo {
        return $this->belongsTo(Processor::class, 'processor_id');
    }
}
