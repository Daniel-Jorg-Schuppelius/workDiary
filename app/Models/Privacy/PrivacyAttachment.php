<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyAttachment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Privacy;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Polymorpher Anhang einer Datenschutz-Fallakte (Betroffenenanfrage/Vorfall).
 *
 * @property int $organization_id
 * @property string $path
 */
class PrivacyAttachment extends Model {
    use BelongsToOrganization;

    protected $table = 'privacy_attachments';

    protected $fillable = [
        'organization_id',
        'attachable_type',
        'attachable_id',
        'filename',
        'path',
        'size',
        'mime',
        'valid_until',
        'uploaded_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'valid_until' => 'date',
    ];

    /** @return MorphTo<Model, $this> */
    public function attachable(): MorphTo {
        return $this->morphTo();
    }
}
