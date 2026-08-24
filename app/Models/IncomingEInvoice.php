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

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
 * @property string $status
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
    use HasSqid;

    /** Eloquent würde zu incoming_e_invoices pluralisieren. */
    protected $table = 'incoming_einvoices';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_QUESTION = 'question';

    public const STATUS_PAYMENT_RELEASED = 'payment_released';

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
    ];

    /** @var array<string, string> */
    protected $casts = [
        'received_at' => 'datetime',
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

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo {
        return $this->belongsTo(Document::class);
    }

    /** Display-Label statt rohem Statuscode (Konvention: Codes nie roh in Views). */
    public function statusLabel(): string {
        return match ($this->status) {
            self::STATUS_RECEIVED => (string) __('Empfangen'),
            self::STATUS_APPROVED => (string) __('Fachlich freigegeben'),
            self::STATUS_REJECTED => (string) __('Abgelehnt'),
            self::STATUS_QUESTION => (string) __('Rückfrage'),
            self::STATUS_PAYMENT_RELEASED => (string) __('Zahlung freigegeben'),
            default => (string) $this->status,
        };
    }
}
