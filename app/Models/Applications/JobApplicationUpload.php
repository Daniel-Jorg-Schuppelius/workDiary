<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobApplicationUpload.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Applications;

use App\Casts\ByteSizeCast;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quarantänisierte, öffentlich hochgeladene Bewerbungsunterlage (MVP-437).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $job_application_id
 * @property string $storage_disk
 * @property string $storage_key
 * @property string $original_name
 * @property string $mime
 * @property \CommonToolkit\ValueObjects\ByteSize|null $size_bytes
 * @property string $sha256
 * @property string $scan_status
 */
class JobApplicationUpload extends Model {
    use BelongsToOrganization;
    use HasSqid;

    public const SCAN_PENDING = 'pending';
    public const SCAN_CLEAN = 'clean';
    public const SCAN_REJECTED = 'rejected';

    protected $fillable = [
        'organization_id', 'job_application_id', 'storage_disk', 'storage_key',
        'original_name', 'mime', 'size_bytes', 'sha256', 'scan_status',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'size_bytes' => ByteSizeCast::class,
    ];

    /** @return BelongsTo<JobApplication, $this> */
    public function application(): BelongsTo {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    /** Erst nach erfolgreichem Scan für HR/DMS freigegeben. */
    public function isReleased(): bool {
        return $this->scan_status === self::SCAN_CLEAN;
    }
}
