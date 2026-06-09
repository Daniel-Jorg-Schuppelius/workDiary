<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Attachment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Whistleblowing;

use App\Enums\Whistleblowing\AttachmentScanStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Whistleblowing\Casts\CaseEncrypted;
use App\Models\Whistleblowing\Concerns\ProvidesCaseDek;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Meldeanhang. Eigenes Modell (NICHT das allgemeine attachments-Modell), liegt
 * auf dem privaten `whistleblowing`-Disk unter zufaelligem storage_key. Der
 * Originalname ist mit dem Fall-DEK verschluesselt.
 *
 * @property string|null $original_name_ciphertext Klartext beim Lesen/Setzen
 */
class Attachment extends Model implements ProvidesCaseDek {
    use BelongsToOrganization;

    protected $table = 'whistleblowing_attachments';

    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id',
        'case_id',
        'message_id',
        'uploaded_by_type',
        'storage_key',
        'original_name_ciphertext',
        'mime_detected',
        'size',
        'sha256',
        'scan_status',
        'metadata_scrubbed',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'scan_status' => AttachmentScanStatus::class,
        'metadata_scrubbed' => 'boolean',
        'size' => 'integer',
        'original_name_ciphertext' => CaseEncrypted::class,
    ];

    public function caseDek(): ?string {
        return $this->case?->caseDek();
    }

    /** @return BelongsTo<WhistleblowingCase, $this> */
    public function case(): BelongsTo {
        return $this->belongsTo(WhistleblowingCase::class, 'case_id');
    }
}
