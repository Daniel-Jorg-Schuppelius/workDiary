<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DriverLicenseCheck.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dokumentierte Führerschein-Sichtprüfung (MVP-417, Halterhaftung § 21 StVG):
 * wer hat wann wessen Führerschein gesehen, Klassen, Gültigkeit, nächste
 * Fälligkeit. Bewusst KEIN Führerschein-Foto (Datensparsamkeit) — die
 * dokumentierte Sichtprüfung genügt.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property int|null $checked_by
 * @property Carbon $checked_at
 * @property string|null $license_classes
 * @property Carbon|null $license_valid_until
 * @property Carbon $next_due_on
 * @property string|null $note
 */
class DriverLicenseCheck extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'user_id',
        'checked_by',
        'checked_at',
        'license_classes',
        'license_valid_until',
        'next_due_on',
        'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'checked_at' => 'date',
        'license_valid_until' => 'date',
        'next_due_on' => 'date',
        'note' => 'encrypted',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function checker(): BelongsTo {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
