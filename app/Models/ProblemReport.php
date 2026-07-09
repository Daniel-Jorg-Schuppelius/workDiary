<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProblemReport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\Support\{ProblemReportDeliveryTarget, ProblemReportSeverity, ProblemReportStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * In-App-Fehlermeldung (Feature 041, MVP-053): technisches Problem mit
 * Seitenkontext, optionalem redaktiertem Diagnoseauszug und stabiler
 * Referenznummer. Erzeugung ausschließlich über ProblemReportService.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $user_id
 * @property string $reference_no
 * @property ProblemReportStatus $status
 * @property ProblemReportSeverity $severity
 * @property string $summary
 * @property string $description
 * @property string|null $expected_behavior
 * @property string|null $actual_behavior
 * @property bool $contact_ok
 * @property array<string, mixed> $page_context
 * @property array<string, mixed>|null $diagnostic_excerpt
 * @property int|null $diagnostics_approved_by
 * @property ProblemReportDeliveryTarget $delivery_target
 * @property \Carbon\CarbonImmutable|null $delivered_at
 * @property string|null $delivery_error
 * @property string|null $external_ref
 */
class ProblemReport extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;
    use HasSqid;

    protected $table = 'problem_reports';

    protected $fillable = [
        'organization_id',
        'user_id',
        'reference_no',
        'status',
        'severity',
        'summary',
        'description',
        'expected_behavior',
        'actual_behavior',
        'contact_ok',
        'page_context',
        'diagnostic_excerpt',
        'diagnostics_approved_by',
        'delivery_target',
        'delivered_at',
        'delivery_error',
        'external_ref',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => ProblemReportStatus::class,
        'severity' => ProblemReportSeverity::class,
        'contact_ok' => 'boolean',
        'page_context' => 'array',
        'diagnostic_excerpt' => 'array',
        'delivery_target' => ProblemReportDeliveryTarget::class,
        'delivered_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Export-Struktur für Mail/Webhook/Download (ohne interne IDs).
     *
     * @return array<string, mixed>
     */
    public function exportPayload(): array {
        return [
            'reference_no' => $this->reference_no,
            'status' => $this->status->value,
            'severity' => $this->severity->value,
            'summary' => $this->summary,
            'description' => $this->description,
            'expected_behavior' => $this->expected_behavior,
            'actual_behavior' => $this->actual_behavior,
            'contact_ok' => $this->contact_ok,
            'page_context' => $this->page_context,
            'diagnostic_excerpt' => $this->diagnostic_excerpt,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
