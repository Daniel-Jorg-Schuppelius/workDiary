<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RestoreTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Backup\RestoreTestResult;
use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Database\Factories\RestoreTestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Protokollierter Restore-Test (Feature 017, §6.3).
 *
 * Plattformweit (kein organization_id) — analog {@see BackupHeartbeat}: der
 * Restore-Vorgang findet außerhalb des Mandanten-Kontexts statt. Bewusst in der
 * Allow-List von TenantTraitCoverageTest geführt (siehe
 * ../WorkDiary-Architecture/security/tenant-audit-2026.md).
 *
 * Dies ist ein nachvollziehbares REGISTER — die eigentliche, automatisierte
 * Wiederherstellung ist out-of-scope (Eintrag erfolgt manuell oder per Skript).
 *
 * @property int $id
 * @property string $source
 * @property CarbonImmutable $tested_on
 * @property RestoreTestResult $result
 * @property string|null $scope
 * @property int|null $restored_size_bytes
 * @property int|null $duration_minutes
 * @property string|null $notes
 * @property CarbonImmutable|null $next_due_on
 * @property int|null $performed_by_user_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class RestoreTest extends Model {
    /** @use HasFactory<RestoreTestFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'source',
        'tested_on',
        'result',
        'scope',
        'restored_size_bytes',
        'duration_minutes',
        'notes',
        'next_due_on',
        'performed_by_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'tested_on' => 'immutable_date',
        'result' => RestoreTestResult::class,
        'restored_size_bytes' => 'integer',
        'duration_minutes' => 'integer',
        'next_due_on' => 'immutable_date',
    ];

    /** @return BelongsTo<User, $this> */
    public function performedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }
}
