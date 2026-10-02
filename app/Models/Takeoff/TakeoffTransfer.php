<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TakeoffTransfer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Takeoff;

use App\Enums\Takeoff\TakeoffTransferKind;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Übernahme eines Aufmaßes (MVP-1059): Art und Zielbeleg (Angebot, Rechnung;
 * beim LV-Leistungsstand ohne einzelnen Zielbeleg).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $takeoff_id
 * @property TakeoffTransferKind $kind
 * @property string|null $target_type
 * @property int|null $target_id
 * @property int|null $created_by
 */
class TakeoffTransfer extends Model {
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'takeoff_id', 'kind', 'target_type', 'target_id', 'created_by'];

    /** @var array<string, string> */
    protected $casts = ['kind' => TakeoffTransferKind::class];

    /** @return BelongsTo<Takeoff, $this> */
    public function takeoff(): BelongsTo {
        return $this->belongsTo(Takeoff::class);
    }

    /** @return MorphTo<Model, $this> */
    public function target(): MorphTo {
        return $this->morphTo();
    }
}
