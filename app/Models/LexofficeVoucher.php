<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeVoucher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lokaler Cache eines Lexoffice-Belegs (voucherlist-Eintrag).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $external_id
 * @property ?string $contact_external_id
 * @property ?int $customer_id
 * @property ?int $supplier_id
 * @property ?string $voucher_type
 * @property ?string $voucher_status
 * @property ?string $voucher_number
 * @property ?Carbon $voucher_date
 * @property ?Carbon $due_date
 * @property ?Carbon $paid_date
 * @property ?string $voucher_text
 * @property ?string $recipient_name
 * @property ?Carbon $service_starts_on
 * @property ?Carbon $service_ends_on
 * @property ?Carbon $lines_synced_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, LexofficeVoucherLine> $lines
 * @property \CommonToolkit\ValueObjects\Money|null $total_amount
 * @property \CommonToolkit\ValueObjects\Money|null $open_amount
 * @property \CommonToolkit\ValueObjects\Money|null $net_amount
 * @property \CommonToolkit\Enums\CurrencyCode $currency
 * @property bool $archived
 * @property ?array<string, mixed> $payload
 * @property ?string $file_path
 * @property ?Carbon $file_materialized_at
 * @property ?Carbon $synced_at
 */
class LexofficeVoucher extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'external_id',
        'contact_external_id',
        'customer_id',
        'supplier_id',
        'voucher_type',
        'voucher_status',
        'voucher_number',
        'voucher_date',
        'due_date',
        'paid_date',
        'voucher_text',
        'recipient_name',
        'service_starts_on',
        'service_ends_on',
        'lines_synced_at',
        'total_amount',
        'open_amount',
        'net_amount',
        'currency',
        'archived',
        'payload',
        'synced_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'currency' => \CommonToolkit\Enums\CurrencyCode::class,
        'customer_id' => 'integer',
        'supplier_id' => 'integer',
        'voucher_date' => 'date',
        'due_date' => 'date',
        'paid_date' => 'date',
        'service_starts_on' => 'date',
        'service_ends_on' => 'date',
        'lines_synced_at' => 'datetime',
        'total_amount' => MoneyCast::class . ':currency,2',
        'open_amount' => MoneyCast::class . ':currency,2',
        // Nur der Nettobetrag der voucherlist-Belege ist NICHT enthalten — er
        // wird per Detailabruf nachgeladen (siehe LexofficeVoucherNetAmount).
        'net_amount' => MoneyCast::class . ':currency,2',
        'archived' => 'boolean',
        'payload' => 'array',
        'synced_at' => 'datetime',
        'file_materialized_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Leistungszeitraum laut Rechnung (Lexoffice shippingConditions): Beginn
     * ist bei Leistungsdatum und -zeitraum gesetzt, Ende nur beim Zeitraum.
     * Das Register ordnet danach zu — nicht nach dem Rechnungsdatum.
     */
    public function serviceStart(): ?\Carbon\CarbonImmutable {
        return $this->service_starts_on === null ? null : \Carbon\CarbonImmutable::instance($this->service_starts_on);
    }

    /** Leistungsmonate aus dem Zeitraum (gerundet; Einzeldatum = null). */
    public function serviceMonths(): ?int {
        if ($this->service_starts_on === null || $this->service_ends_on === null) {
            return null;
        }
        $months = (int) round($this->service_starts_on->diffInMonths($this->service_ends_on->copy()->addDay()));

        return $months > 0 ? $months : null;
    }

    /** Anzeige „dd.mm.yyyy – dd.mm.yyyy" bzw. nur der Beginn. */
    public function servicePeriodLabel(): ?string {
        if ($this->service_starts_on === null) {
            return null;
        }
        $label = $this->service_starts_on->format('d.m.Y');

        return $this->service_ends_on === null ? $label : $label . ' – ' . $this->service_ends_on->format('d.m.Y');
    }

    /**
     * Belegtext ohne die Standardfloskeln (Titel, „stellen wir Ihnen wie folgt
     * in Rechnung", „Vielen Dank für die gute Zusammenarbeit") — übrig bleibt,
     * was der Reseller je Beleg ergänzt hat, beim Endkunden z. B.
     * „Microsoft Dienste - Klimpel Bäder" oder „(M365 Haus24)".
     */
    public function voucherTextHint(): ?string {
        $text = trim((string) $this->voucher_text);
        if ($text === '') {
            return null;
        }
        $segments = preg_split('/(?<=[.!?])\s+|\R+/u', $text) ?: [];
        $kept = [];
        foreach ($segments as $segment) {
            $segment = trim($segment, " \t()");
            if ($segment === '' || preg_match('/^(Rechnung|Gutschrift|Invoice|Credit Note)$/iu', $segment) === 1) {
                continue;
            }
            if (preg_match('/Lieferungen\/Leistungen|in Rechnung|Zusammenarbeit|Vielen Dank|Thank you|Zahlbar|zahlbar|Zahlungsziel/iu', $segment) === 1) {
                continue;
            }
            $kept[] = $segment;
        }
        $hint = trim(implode(' · ', $kept));

        return $hint !== '' && mb_strlen($hint) <= 120 ? $hint : null;
    }

    /**
     * Direktlink in die Lexoffice-Oberfläche (dokumentierter Permalink für
     * Rechnungen/Gutschriften; Buchungsbelege haben keinen).
     */
    public function lexofficePermalink(): ?string {
        $type = match ((string) $this->voucher_type) {
            'invoice' => 'invoices',
            'creditnote' => 'credit-notes',
            'downpaymentinvoice' => 'down-payment-invoices',
            default => null,
        };

        return $type === null || $this->external_id === '' ? null : 'https://app.lexoffice.de/permalink/' . $type . '/view/' . $this->external_id;
    }

    /**
     * Positionen aus dem Belegspiegel (Feature 152, MVP-760) — nur für
     * Lexoffice-eigene Rechnungen nach dem Positions-Sync gefüllt.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<LexofficeVoucherLine, $this>
     */
    public function lines(): \Illuminate\Database\Eloquent\Relations\HasMany {
        return $this->hasMany(LexofficeVoucherLine::class, 'voucher_id')->orderBy('position');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder {
        return $query->where('archived', false);
    }
}
