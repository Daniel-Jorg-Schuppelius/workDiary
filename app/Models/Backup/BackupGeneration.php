<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupGeneration.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Backup;

use App\Enums\Backup\{BackupGenerationStatus, BackupRetentionClass};
use App\Models\Concerns\{Auditable, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Backup-Generation (Feature 017 Phase 32, MVP-361): ein vollständiger
 * verschlüsselter Snapshot der Installation. SYSTEMWEIT (kein
 * organization_id); der Nachweis überlebt die Trennung der Verbindung
 * (connection_id nullOnDelete). Ohne gültiges, signiertes Commit-Manifest
 * gilt eine Generation als NICHT restorable — Retention löscht nur
 * vollständig verifizierte, nicht gehaltene Generationen und nie die
 * letzte als restorable bestätigte.
 *
 * @property int $id
 * @property int|null $connection_id
 * @property string $snapshot_uuid
 * @property BackupRetentionClass $retention_class
 * @property BackupGenerationStatus $status
 * @property string|null $remote_prefix
 * @property int|null $plain_size
 * @property int|null $cipher_size
 * @property int $part_count
 * @property string|null $manifest_sha256
 * @property string|null $commit_remote_ref
 * @property string|null $key_envelope
 * @property string|null $recovery_envelope
 * @property string|null $app_version
 * @property bool $legal_hold
 * @property Carbon|null $started_at
 * @property Carbon|null $committed_at
 * @property Carbon|null $last_verified_at
 * @property Carbon|null $restore_tested_at
 * @property int|null $restore_rpo_seconds
 * @property int|null $restore_rto_seconds
 * @property string|null $last_error
 * @property-read \Illuminate\Database\Eloquent\Collection<int, BackupGenerationPart> $parts
 */
class BackupGeneration extends Model {
    use Auditable;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'connection_id',
        'snapshot_uuid',
        'retention_class',
        'status',
        'remote_prefix',
        'plain_size',
        'cipher_size',
        'part_count',
        'manifest_sha256',
        'commit_remote_ref',
        'key_envelope',
        'recovery_envelope',
        'app_version',
        'legal_hold',
        'started_at',
        'committed_at',
        'last_verified_at',
        'restore_tested_at',
        'restore_rpo_seconds',
        'restore_rto_seconds',
        'last_error',
    ];

    /** Envelopes (verschlüsselte Datenschlüssel) nie in Serialisierungen. */
    protected $hidden = [
        'key_envelope',
        'recovery_envelope',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'retention_class' => BackupRetentionClass::class,
        'status' => BackupGenerationStatus::class,
        'plain_size' => 'integer',
        'cipher_size' => 'integer',
        'part_count' => 'integer',
        'legal_hold' => 'boolean',
        'started_at' => 'datetime',
        'committed_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'restore_tested_at' => 'datetime',
        'restore_rpo_seconds' => 'integer',
        'restore_rto_seconds' => 'integer',
    ];

    /** @return BelongsTo<BackupTargetConnection, $this> */
    public function connection(): BelongsTo {
        return $this->belongsTo(BackupTargetConnection::class, 'connection_id');
    }

    /** @return HasMany<BackupGenerationPart, $this> */
    public function parts(): HasMany {
        return $this->hasMany(BackupGenerationPart::class, 'generation_id');
    }

    /** Restorable = Commit liegt vor und die Verifikation ist nicht gebrochen. */
    public function isRestorable(): bool {
        return in_array($this->status, [BackupGenerationStatus::Committed, BackupGenerationStatus::Verified], true);
    }

    /** Retention darf nur verifizierte, nicht gehaltene Generationen löschen. */
    public function isDeletableByRetention(): bool {
        return !$this->legal_hold && $this->status->isDeletableByRetention();
    }
}
