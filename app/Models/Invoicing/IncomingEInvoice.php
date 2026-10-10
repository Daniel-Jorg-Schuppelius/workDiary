<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingEInvoice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Invoicing;

use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceMatchKind, IncomingInvoiceRecognition};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use App\Models\Customer\Customer;
use App\Models\Document\Document;
use App\Models\Finance\IncomingInvoiceRetention;
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Eingehende E-Rechnung im Prüfbereich (Feature 066, MVP-165/167):
 * Hash-Nachweis + Herkunft + Freigabe-Workflow; das unveränderte
 * Original liegt als Document im DMS.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $document_id
 * @property string $sha256
 * @property string $source
 * @property string|null $invoice_number
 * @property string|null $seller_name
 * @property string|null $seller_vat_id
 * @property \Illuminate\Support\Carbon|null $issue_date
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property \CommonToolkit\Enums\CurrencyCode|null $currency
 * @property \CommonToolkit\ValueObjects\Money|null $amount_net
 * @property \CommonToolkit\ValueObjects\Money|null $amount_tax
 * @property \CommonToolkit\ValueObjects\Money|null $amount_gross
 * @property \Illuminate\Support\Carbon $received_at
 * @property IncomingEInvoiceStatus $status
 * @property DocumentDirection $direction
 * @property DocumentKind $kind
 * @property IncomingInvoiceRecognition $recognition
 * @property string|null $buyer_name
 * @property string|null $buyer_vat_id
 * @property string|null $sender_email
 * @property string|null $source_reference
 * @property int|null $supplier_id
 * @property int|null $customer_id
 * @property IncomingInvoiceMatchKind|null $match_kind
 * @property int|null $matched_user_id
 * @property \Illuminate\Support\Carbon|null $matched_at
 * @property int|null $decided_by
 * @property \Illuminate\Support\Carbon|null $decided_at
 * @property string|null $decision_note
 * @property array<string, mixed>|null $summary
 * @property \Illuminate\Support\Carbon|null $transferred_at
 * @property int|null $transferred_by
 * @property string|null $creditor_iban
 * @property string|null $creditor_bic
 * @property \Illuminate\Support\Carbon|null $creditor_iban_confirmed_at
 * @property int|null $creditor_iban_confirmed_by
 * @property float|null $discount_percent
 * @property int|null $discount_days
 * @property int|null $paid_in_run_id
 */
class IncomingEInvoice extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;
    use HasSqid;

    /** Eloquent würde zu incoming_e_invoices pluralisieren. */
    protected $table = 'incoming_einvoices';

    /** Wie die Spaltenvorgaben — sonst fehlen die Werte bis zum nächsten Laden. */
    protected $attributes = [
        'direction' => 'incoming',
        'kind' => 'invoice',
        'recognition' => 'structured',
    ];

    protected $fillable = [
        'organization_id', 'document_id', 'sha256', 'source', 'received_at',
        'status', 'decided_by', 'decided_at', 'decision_note', 'summary',
        'transferred_at', 'transferred_by',
        // MVP-544: aus `summary` denormalisiert, damit der Belegfluss sortieren
        // und summieren kann. Führend bleibt das geparste Original.
        'invoice_number', 'seller_name', 'seller_vat_id', 'issue_date', 'due_date',
        'currency', 'amount_net', 'amount_tax', 'amount_gross',
        // MVP-609: Zahlungsdaten für den Zahlungsvorschlag.
        'creditor_iban', 'creditor_bic', 'discount_percent', 'discount_days',
        'paid_in_run_id',
        // MVP-1107: Rechnungspostfach.
        'direction', 'kind', 'recognition', 'buyer_name', 'buyer_vat_id',
        'sender_email', 'source_reference',
        // MVP-1108: Gegenpartei.
        'supplier_id', 'customer_id', 'match_kind', 'matched_user_id', 'matched_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'received_at' => 'datetime',
        'status' => IncomingEInvoiceStatus::class,
        'direction' => DocumentDirection::class,
        'kind' => DocumentKind::class,
        'recognition' => IncomingInvoiceRecognition::class,
        'match_kind' => IncomingInvoiceMatchKind::class,
        'matched_at' => 'datetime',
        'decided_at' => 'datetime',
        'summary' => 'array',
        'transferred_at' => 'datetime',
        'issue_date' => 'date',
        'due_date' => 'date',
        'currency' => \CommonToolkit\Enums\CurrencyCode::class,
        'amount_net' => \App\Casts\MoneyCast::class . ':currency,2',
        'amount_tax' => \App\Casts\MoneyCast::class . ':currency,2',
        'amount_gross' => \App\Casts\MoneyCast::class . ':currency,2',
        // Bankverbindung des Lieferanten wie alle IBAN-Spalten at-rest verschlüsselt
        // (Vollscan 2026-08-23, E5); einziger Lesepfad ist der Attributzugriff.
        'creditor_iban' => 'encrypted',
        'creditor_bic' => 'encrypted',
        'creditor_iban_confirmed_at' => 'datetime',
    ];

    /**
     * Spaltenwerte aus einem `summary`-Array (MVP-544). Eine Stelle für
     * Neuanlage und Nachzug, damit Spalten und JSON nicht auseinanderlaufen.
     *
     * @param  array<string, mixed>  $summary
     * @return array<string, string|null>
     */
    public static function columnsFromSummary(array $summary): array {
        $text = static function (mixed $value, int $max): ?string {
            $value = is_scalar($value) ? trim((string) $value) : '';

            return $value === '' ? null : mb_substr($value, 0, $max);
        };

        return [
            'invoice_number' => $text($summary['number'] ?? null, 64),
            'seller_name' => $text($summary['seller'] ?? null, 191),
            'seller_vat_id' => $text($summary['seller_vat'] ?? null, 32),
            'buyer_name' => $text($summary['buyer'] ?? null, 191),
            'buyer_vat_id' => $text($summary['buyer_vat'] ?? null, 32),
            'issue_date' => $text($summary['issue_date'] ?? null, 10),
            'due_date' => $text($summary['due_date'] ?? null, 10),
            'currency' => $text($summary['currency'] ?? null, 3),
            'amount_net' => is_numeric($summary['net'] ?? null) ? (string) $summary['net'] : null,
            'amount_tax' => is_numeric($summary['tax'] ?? null) ? (string) $summary['tax'] : null,
            'amount_gross' => is_numeric($summary['gross'] ?? null) ? (string) $summary['gross'] : null,
            'creditor_iban' => $text($summary['creditor_iban'] ?? null, 40),
            'creditor_bic' => $text($summary['creditor_bic'] ?? null, 20),
            'discount_percent' => is_numeric($summary['discount_percent'] ?? null) ? (string) $summary['discount_percent'] : null,
            'discount_days' => is_numeric($summary['discount_days'] ?? null) ? (string) $summary['discount_days'] : null,
        ];
    }

    /**
     * Nur Eingangsbelege. Ausgangsbelege aus dem Postfach (Rechnungskopien,
     * Gutschriftverfahren) sind keine Verbindlichkeit und gehören nie in
     * Zahlung, Einbehalt, Liquidität oder Buchungsadapter.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePurchases(Builder $query): Builder {
        return $query->where($query->qualifyColumn('direction'), DocumentDirection::Incoming->value);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function matchedUser(): BelongsTo {
        return $this->belongsTo(User::class, 'matched_user_id');
    }

    /** @return HasMany<IncomingEInvoiceTransfer, $this> Übergaben je Buchhaltungsziel (MVP-1111) */
    public function transfers(): HasMany {
        return $this->hasMany(IncomingEInvoiceTransfer::class, 'incoming_einvoice_id');
    }

    /** Lieferant (Eingang) bzw. Kunde (Ausgang); null = noch zuzuordnen. */
    public function counterparty(): Supplier|Customer|null {
        return $this->supplier ?? $this->customer;
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo {
        return $this->belongsTo(Document::class);
    }

    /** @return HasMany<IncomingInvoiceRetention, $this> Einbehalte (MVP-953) */
    public function retentions(): HasMany {
        return $this->hasMany(IncomingInvoiceRetention::class, 'incoming_einvoice_id')->orderBy('id');
    }
}
