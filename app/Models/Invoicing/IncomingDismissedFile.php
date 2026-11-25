<?php
/*
 * Created on   : Wed Nov 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingDismissedFile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Invoicing;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Von Hand als „keine Rechnung“ gelöschte Datei (MVP-1808): dieselbe Datei
 * nimmt das Rechnungspostfach nicht wieder auf, bis der Eintrag gelöscht oder
 * die Datei von Hand hochgeladen wird.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $sha256
 * @property string|null $file_name
 * @property int $hits
 * @property \Illuminate\Support\Carbon|null $last_matched_at
 * @property int|null $created_by
 */
class IncomingDismissedFile extends Model {
    use BelongsToOrganization;
    use HasSqid;

    /** @var list<string> */
    protected $fillable = ['organization_id', 'sha256', 'file_name', 'hits', 'last_matched_at', 'created_by'];

    /** @var array<string, string> */
    protected $casts = [
        'hits' => 'integer',
        'last_matched_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }
}
