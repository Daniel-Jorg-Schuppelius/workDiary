<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FireflyTransaction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Firefly\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Invoicing\{IncomingEInvoice, Invoice};
use App\Plugins\Firefly\Enums\{FireflyMatchStatus, FireflyTransactionKind};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gespiegelte Firefly-III-Bewegung (Feature 176) mit ihrer Zuordnung:
 * Eingang → Rechnung, Ausgang → Eingangsrechnung, jeweils über eine
 * Belegnummer im Verwendungszweck. WorkDiary bucht daraus nichts.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $remote_journal_id
 * @property string|null $remote_group_id
 * @property FireflyTransactionKind $kind
 * @property CarbonImmutable $booked_on
 * @property Money $booked_amount
 * @property CurrencyCode|null $currency
 * @property string|null $description
 * @property string|null $counterparty_name
 * @property string|null $account_name
 * @property list<string>|null $references
 * @property FireflyMatchStatus $match_status
 * @property int|null $invoice_id
 * @property int|null $incoming_einvoice_id
 * @property-read Invoice|null $invoice
 * @property-read IncomingEInvoice|null $incomingEinvoice
 */
class FireflyTransaction extends Model {
    use BelongsToOrganization;

    protected $table = 'firefly_transactions';

    protected $fillable = [
        'organization_id', 'remote_journal_id', 'remote_group_id', 'kind', 'booked_on', 'booked_amount', 'currency',
        'description', 'counterparty_name', 'account_name', 'references', 'match_status', 'invoice_id', 'incoming_einvoice_id',
    ];

    protected $casts = [
        'kind' => FireflyTransactionKind::class,
        'booked_on' => 'immutable_date',
        'booked_amount' => MoneyCast::class . ':currency,2',
        'currency' => CurrencyCode::class,
        'references' => 'array',
        'match_status' => FireflyMatchStatus::class,
    ];

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<IncomingEInvoice, $this> */
    public function incomingEinvoice(): BelongsTo {
        return $this->belongsTo(IncomingEInvoice::class);
    }

    /**
     * Verwendungszweck, Gegenpartei oder Belegnummer.
     *
     * @param  Builder<self>  $query
     */
    public static function whereMatching(Builder $query, string $term): void {
        $query->where(fn (Builder $q) => $q->whereLikeEscaped('description', $term)
            ->orWhereLikeEscaped('counterparty_name', $term)
            ->orWhereLikeEscaped('references', $term));
    }
}
