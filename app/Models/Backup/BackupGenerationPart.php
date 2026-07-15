<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupGenerationPart.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Backup;

use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Teil einer Backup-Generation (Feature 017 Phase 32, MVP-361): ein
 * verschlüsseltes ~128-MiB-Segment. `uploaded_at` + `remote_ref` machen den
 * Upload idempotent wiederaufnehmbar; beide Hashes (Klartext/Ciphertext)
 * tragen die spätere Verifikation. Mandantengrenze: transitiv systemweit
 * über die Generation.
 *
 * @property int $id
 * @property int $generation_id
 * @property int $part_no
 * @property int $plain_size
 * @property int $cipher_size
 * @property string $plain_sha256
 * @property string $cipher_sha256
 * @property string|null $remote_ref
 * @property Carbon|null $uploaded_at
 */
class BackupGenerationPart extends Model {
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'generation_id',
        'part_no',
        'plain_size',
        'cipher_size',
        'plain_sha256',
        'cipher_sha256',
        'remote_ref',
        'uploaded_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'part_no' => 'integer',
        'plain_size' => 'integer',
        'cipher_size' => 'integer',
        'uploaded_at' => 'datetime',
    ];

    /** @return BelongsTo<BackupGeneration, $this> */
    public function generation(): BelongsTo {
        return $this->belongsTo(BackupGeneration::class, 'generation_id');
    }

    public function isUploaded(): bool {
        return $this->uploaded_at !== null && $this->remote_ref !== null;
    }
}
