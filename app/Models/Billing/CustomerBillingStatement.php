<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerBillingStatement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Billing;

use App\Casts\MoneyCast;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Monats-Abrechnungsblock eines Kundenkontos (Feature 098), Excel-Analogie:
 * gross_value=„Gesamt", payments_total=„Abgerechnet", carry_in=„Vormonat",
 * balance=„Offen". Lock friert den Zeilen-Snapshot in `totals` ein
 * (inkl. entry_ids) — Vorbild month_closures.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $customer_billing_agreement_id
 * @property int $year
 * @property int $month
 * @property int $total_minutes
 * @property \CommonToolkit\ValueObjects\Money|null $gross_value
 * @property \CommonToolkit\ValueObjects\Money|null $payments_total
 * @property \CommonToolkit\ValueObjects\Money|null $carry_in
 * @property \CommonToolkit\ValueObjects\Money|null $balance
 * @property bool $locked
 * @property Carbon|null $locked_at
 * @property int|null $locked_by_user_id
 * @property array<string, mixed>|null $totals
 * @property Carbon|null $computed_at
 * @property int|null $retainer_invoice_id
 * @property int|null $lexoffice_voucher_id
 */
class CustomerBillingStatement extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'customer_billing_agreement_id',
        'year',
        'month',
        'total_minutes',
        'gross_value',
        'payments_total',
        'carry_in',
        'balance',
        'locked',
        'locked_at',
        'locked_by_user_id',
        'totals',
        'computed_at',
        'retainer_invoice_id',
        'lexoffice_voucher_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'total_minutes' => 'integer',
        // Tabelle ohne Währungsspalte — Cast fällt auf die Standardwährung
        // zurück; der Statement-Service normiert auf die Agreement-Währung.
        'gross_value' => MoneyCast::class,
        'payments_total' => MoneyCast::class,
        'carry_in' => MoneyCast::class,
        'balance' => MoneyCast::class,
        'locked' => 'boolean',
        'locked_at' => 'datetime',
        'totals' => 'array',
        'computed_at' => 'datetime',
    ];

    /** @return BelongsTo<CustomerBillingAgreement, $this> */
    public function agreement(): BelongsTo {
        return $this->belongsTo(CustomerBillingAgreement::class, 'customer_billing_agreement_id');
    }

    /** @return BelongsTo<User, $this> */
    public function lockedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'locked_by_user_id');
    }

    /**
     * An Lexoffice übergebene Monatspauschale dieses Monats (Retainer-Modus,
     * Feature 098) — Idempotenz-Anker gegen Doppelbelege.
     *
     * @return BelongsTo<\App\Models\Invoice, $this>
     */
    public function retainerInvoice(): BelongsTo {
        return $this->belongsTo(\App\Models\Invoice::class, 'retainer_invoice_id');
    }

    /**
     * Direkt in Lexoffice geführte Pauschalrechnung dieses Monats — der
     * Gegenpart zu {@see retainerInvoice()} für Bestände, die workDiary nicht
     * selbst gepusht hat. Beides gesetzt kann nicht vorkommen (Push prüft).
     *
     * @return BelongsTo<\App\Models\LexofficeVoucher, $this>
     */
    public function lexofficeVoucher(): BelongsTo {
        return $this->belongsTo(\App\Models\LexofficeVoucher::class, 'lexoffice_voucher_id');
    }

    /** Ist für diesen Monat überhaupt eine Pauschalrechnung hinterlegt? */
    public function hasRetainerCharge(): bool {
        return $this->retainer_invoice_id !== null || $this->lexoffice_voucher_id !== null;
    }

    /** Erster Tag des Statement-Monats (lokale Anzeige-Zeitzone). */
    public function periodStart(): Carbon {
        return Carbon::parse(\sprintf('%04d-%02d-01', $this->year, $this->month), \App\Support\Tz::current())->startOfDay();
    }

    public function periodLabel(): string {
        return $this->periodStart()->translatedFormat('F Y');
    }
}
