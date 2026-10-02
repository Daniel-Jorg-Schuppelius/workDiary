<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevOnlineTransfer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\DatevOnline\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Plugins\DatevOnline\Enums\{DatevTransferKind, DatevTransferStatus};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Journal der Übertragungen an DATEV (MVP-122): je Quelle und Art höchstens
 * eine Zeile — ein Beleg geht nie zweimal, ein fehlgeschlagener wird erneut
 * versucht.
 *
 * @property int $id
 * @property int $organization_id
 * @property DatevTransferKind $kind
 * @property string $source_type
 * @property int $source_id
 * @property string $datev_client_number
 * @property string|null $datev_reference
 * @property DatevTransferStatus $status
 * @property string|null $error
 * @property int $attempts
 * @property Carbon|null $transferred_at
 * @property Carbon|null $checked_at
 */
class DatevOnlineTransfer extends Model {
    use BelongsToOrganization;

    protected $table = 'datev_online_transfers';

    protected $fillable = [
        'organization_id', 'kind', 'source_type', 'source_id', 'datev_client_number', 'datev_reference',
        'status', 'error', 'attempts', 'transferred_at', 'checked_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => DatevTransferKind::class,
        'status' => DatevTransferStatus::class,
        'attempts' => 'integer',
        'transferred_at' => 'datetime',
        'checked_at' => 'datetime',
    ];

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo {
        return $this->morphTo();
    }
}
