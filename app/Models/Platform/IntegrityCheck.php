<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrityCheck.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Security\IntegrityCheckStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plattformweite Integritäts-Timeline (Feature 095): Baseline-Erzeugungen
 * (`status=baseline`) und Prüfläufe in einer Tabelle; jede Zeile ist
 * Audit-Subjekt der audit_logs-Hash-Kette. Retention: Pruning im
 * {@see \App\Services\Release\CodeIntegrityService} (integrity.retention_months).
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon $ran_at
 * @property IntegrityCheckStatus $status
 * @property string|null $baseline_source
 * @property string|null $baseline_root
 * @property int $files_checked
 * @property int $added_count
 * @property int $modified_count
 * @property int $deleted_count
 * @property int $packages_changed_count
 * @property array<string, list<string>>|null $findings
 * @property string|null $findings_hash
 * @property int $duration_ms
 * @property string $triggered_by
 * @property int|null $created_by
 */
class IntegrityCheck extends Model {
    protected $fillable = [
        'ran_at',
        'status',
        'baseline_source',
        'baseline_root',
        'files_checked',
        'added_count',
        'modified_count',
        'deleted_count',
        'packages_changed_count',
        'findings',
        'findings_hash',
        'duration_ms',
        'triggered_by',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'ran_at' => 'datetime',
        'status' => IntegrityCheckStatus::class,
        'findings' => 'array',
        'files_checked' => 'integer',
        'added_count' => 'integer',
        'modified_count' => 'integer',
        'deleted_count' => 'integer',
        'packages_changed_count' => 'integer',
        'duration_ms' => 'integer',
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Summe aller Abweichungen (Dateien + Pakete). */
    public function deviationCount(): int {
        return $this->added_count + $this->modified_count + $this->deleted_count + $this->packages_changed_count;
    }
}
