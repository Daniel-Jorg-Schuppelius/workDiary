<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ComplianceFinding.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Compliance\ComplianceFindingStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\ComplianceFindingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};
use Illuminate\Support\Carbon;

/**
 * Persistierter Compliance-Verstoß (Feature 006, Welle D). Bildet die bisher
 * nur on-the-fly berechneten ArbZG-Befunde
 * ({@see \App\Services\Compliance\AttendanceComplianceChecker}) revisionssicher
 * ab: derselbe Verstoß wird beim erneuten Scan über den `dedup_key`
 * wiedererkannt (nicht dupliziert), ein nicht mehr auftretender Verstoß wird
 * auf `resolved` gesetzt statt gelöscht.
 *
 * BEWUSST OHNE automatisches Änderungs-Logging: der Scan aktualisiert
 * `last_detected_at` bei jedem Lauf — ein Auto-`updated`-Audit je Lauf wäre
 * reines Rauschen. `bootAuditable()` ist deshalb zu einem No-op überschrieben;
 * es werden ausschließlich die fachlichen Statuswechsel explizit über
 * {@see Auditable::audit()} in die Audit-Hash-Kette geschrieben
 * (compliance.finding.detected/acknowledged/accepted/resolved/reopened).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $category
 * @property string $rule_code
 * @property string $severity
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property Carbon $scope_date
 * @property int $detected_value
 * @property int $threshold_value
 * @property string $dedup_key
 * @property ComplianceFindingStatus $status
 * @property Carbon|null $first_detected_at
 * @property Carbon|null $last_detected_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $acknowledged_at
 * @property int|null $acknowledged_by
 * @property string|null $acknowledge_note
 */
class ComplianceFinding extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<ComplianceFindingFactory> */
    use HasFactory;
    use HasSqid;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'category',
        'rule_code',
        'severity',
        'subject_type',
        'subject_id',
        'scope_date',
        'detected_value',
        'threshold_value',
        'dedup_key',
        'status',
        'first_detected_at',
        'last_detected_at',
        'resolved_at',
        'acknowledged_at',
        'acknowledged_by',
        'acknowledge_note',
    ];

    protected $casts = [
        'status' => ComplianceFindingStatus::class,
        'scope_date' => 'date',
        'detected_value' => 'integer',
        'threshold_value' => 'integer',
        'first_detected_at' => 'datetime',
        'last_detected_at' => 'datetime',
        'resolved_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    /**
     * Kein automatisches Änderungs-Logging (bewusst) — s. Klassen-Doc.
     * Die `audit()`-Methode des Traits bleibt für explizite Statuswechsel
     * nutzbar; nur die auto-registrierten created/updated/deleted-Listener
     * entfallen.
     */
    public static function bootAuditable(): void {
        // no-op
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function acknowledgedByUser(): BelongsTo {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function isResolved(): bool {
        return $this->status === ComplianceFindingStatus::Resolved;
    }
}
