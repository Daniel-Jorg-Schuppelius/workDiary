<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceEndProcess.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\AssetFinance;

use App\Enums\AssetFinance\AssetFinanceEndKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rückgabe-/Ende-Prozess (MVP-276): Zustand, Kilometer/Betriebsstunden,
 * Schäden, Nachberechnung und Entscheidung (Rückgabe/Kauf/Verlängerung/
 * Ersatzinvestition) mit DMS-Nachweisen (Attachments).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $asset_finance_contract_id
 * @property AssetFinanceEndKind $kind
 * @property string $status
 */
class AssetFinanceEndProcess extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;
    use HasSqid;

    public const STATUSES = ['draft', 'in_progress', 'completed'];

    protected $fillable = [
        'organization_id', 'asset_finance_contract_id', 'kind', 'status',
        'condition_note', 'meter_value', 'operating_hours', 'damages',
        'accessories', 'follow_up_amount', 'new_ends_on', 'decided_by',
        'decided_at', 'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => AssetFinanceEndKind::class,
        'meter_value' => 'decimal:4',
        'operating_hours' => 'decimal:2',
        'follow_up_amount' => 'decimal:2',
        'new_ends_on' => 'date',
        'decided_at' => 'datetime',
    ];

    /** @return BelongsTo<AssetFinanceContract, $this> */
    public function contract(): BelongsTo {
        return $this->belongsTo(AssetFinanceContract::class, 'asset_finance_contract_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
