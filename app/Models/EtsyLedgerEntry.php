<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyLedgerEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/**
 * Spiegelzeile des Etsy-Payment-Ledgers (Feature 101, MVP-498): Gebühren,
 * Auszahlungen, Erstattungen für die Auswertung/Zahlungszuordnung.
 * `amount`/`balance` kommen von Etsy als PLAIN INTEGER in der kleinsten
 * Währungseinheit (kein Money-Objekt, W0 §6 — Spec nennt keine Divisor-
 * Semantik, „in pennies" laut Payment-Doku) und werden bewusst ROH
 * gespeichert; die Anzeige teilt durch 100. `receipt_id` wird über den
 * Payment-Batch-Abruf nachgezogen (reference_type=payment).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $ledger_entry_id
 * @property string|null $ledger_type
 * @property int $amount
 * @property int $balance
 * @property string|null $currency
 * @property string|null $description
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property int|null $receipt_id
 * @property \Illuminate\Support\Carbon|null $posted_at
 */
class EtsyLedgerEntry extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'ledger_entry_id',
        'ledger_type',
        'amount',
        'balance',
        'currency',
        'description',
        'reference_type',
        'reference_id',
        'receipt_id',
        'posted_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'ledger_entry_id' => 'integer',
        'amount' => 'integer',
        'balance' => 'integer',
        'receipt_id' => 'integer',
        'posted_at' => 'datetime',
    ];
}
