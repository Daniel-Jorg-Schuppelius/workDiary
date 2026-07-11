<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceRateSchedule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\AssetFinance;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\IncomingEInvoice;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ratenplan-Zeile (MVP-272/274): Soll-Rate mit Fälligkeit; Ist nur als
 * Referenz auf die Eingangsrechnung (D11) — hier wird nichts gebucht.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $asset_finance_contract_id
 * @property \Illuminate\Support\Carbon $due_on
 * @property numeric-string $amount
 * @property string $status
 */
class AssetFinanceRateSchedule extends Model {
    use BelongsToOrganization;
    use HasSqid;

    public const STATUSES = ['planned', 'paid', 'overdue'];

    protected $fillable = [
        'organization_id', 'asset_finance_contract_id', 'due_on', 'amount',
        'status', 'incoming_einvoice_id', 'paid_at', 'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'due_on' => 'date',
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /** @param Builder<self> $query */
    public function scopePlanned(Builder $query): void {
        $query->where('status', 'planned');
    }

    /** @return BelongsTo<AssetFinanceContract, $this> */
    public function contract(): BelongsTo {
        return $this->belongsTo(AssetFinanceContract::class, 'asset_finance_contract_id');
    }

    /** @return BelongsTo<IncomingEInvoice, $this> */
    public function incomingEInvoice(): BelongsTo {
        return $this->belongsTo(IncomingEInvoice::class, 'incoming_einvoice_id');
    }
}
