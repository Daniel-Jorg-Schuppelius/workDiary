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
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceMatchKind, IncomingInvoiceRecognition, IncomingInvoiceTransferStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use App\Models\Customer\Customer;
use App\Models\Document\Document;
use App\Models\Finance\IncomingInvoiceRetention;
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Support\ReferenceTokens;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Facades\Storage;

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
 * @property string|null $invoice_number_key
 * @property int|null $duplicate_of_id
 * @property int|null $duplicate_cleared_user_id
 * @property \Illuminate\Support\Carbon|null $duplicate_cleared_at
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
 * @property string|null $creditor_iban_confirmed_hash Blind-Index der bestätigten IBAN (Feature 208, M4)
 * @property string|null $discount_percent
 * @property int|null $discount_days
 * @property int|null $paid_in_run_id
 * @property \App\Enums\Invoicing\IncomingInvoiceTaxCase|null $tax_case
 * @property int|null $source_document_id
 * @property int|null $source_page_from
 * @property int|null $source_page_to
 * @property string|null $source_sha256
 * @property array<string, mixed>|null $split_proposal
 * @property array<string, mixed>|null $girocode
 * @property \Illuminate\Support\Carbon|null $girocode_confirmed_at
 * @property int|null $girocode_confirmed_by
 */
class IncomingEInvoice extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;
    use HasSqid;

    /** Eloquent würde zu incoming_e_invoices pluralisieren. */
    protected $table = 'incoming_einvoices';

    /**
     * Abgeschlossen ohne weitere Bearbeitung: abgelehnt oder als Sammel-PDF in
     * Teile aufgeteilt (Feature 208). Solche Eingänge stehen in keiner
     * Arbeitsliste, keiner Zählung und keinem Abgleich.
     *
     * @var list<string>
     */
    public const array CLOSED_STATUSES = ['rejected', 'split'];

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
        // MVP-1219: Belegvorlage, die beim Eingang getroffen hat.
        'template_id',
        // MVP-1621: Steuerfall von Hand; leer = aus Beleg und Lieferant.
        'tax_case',
        // MVP-1662: Teil einer Sammel-PDF — Original und Seitenbereich.
        'source_document_id', 'source_page_from', 'source_page_to', 'source_sha256',
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
        'duplicate_cleared_at' => 'datetime',
        'discount_percent' => 'decimal:2',
        'tax_case' => \App\Enums\Invoicing\IncomingInvoiceTaxCase::class,
        // Sammel-PDF und GiroCode (MVP-1662/1663); der GiroCode trägt eine IBAN und ist deshalb verschlüsselt.
        'source_page_from' => 'integer',
        'source_page_to' => 'integer',
        'split_proposal' => 'array',
        'girocode' => 'encrypted:array',
        'girocode_confirmed_at' => 'datetime',
    ];

    protected static function booted(): void {
        static::saving(static function (self $incoming): void {
            if (! $incoming->exists || $incoming->isDirty(['invoice_number', 'seller_vat_id', 'supplier_id', 'customer_id'])) {
                $incoming->refreshDuplicate();
            }
        });
    }

    /** Belegart-Hinweis der Erkennung (MVP-1216): reminder, delivery_note, order_confirmation, quote, shipping_label. */
    public function kindHint(): ?string {
        $hint = ((array) $this->summary)['kind_hint'] ?? null;

        return is_string($hint) && $hint !== '' ? $hint : null;
    }

    /**
     * Eingänge, auf die eine Mahnung verweist (MVP-1216): Nummern aus
     * `summary.reminder_for` gegen die Rechnungsnummern derselben Organisation.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public function reminderTargets(): \Illuminate\Database\Eloquent\Collection {
        $keys = array_values(array_filter(array_map(
            static fn (mixed $number): ?string => ReferenceTokens::key($number),
            (array) (((array) $this->summary)['reminder_for'] ?? []),
        )));
        if ($keys === []) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        return self::query()->withoutGlobalScopes()
            ->where('organization_id', $this->organization_id)
            ->whereKeyNot($this->getKey())
            ->whereIn('invoice_number_key', $keys)
            ->orderBy('id')
            ->get();
    }

    /** Vergleichsschlüssel eines Gegenparteinamens (MVP-1208): Kleinschreibung, Leerraum auf ein Leerzeichen. */
    public static function nameKey(?string $name): ?string {
        $key = \CommonToolkit\Helper\Data\StringHelper::normalizeColumnName($name);

        return $key === '' ? null : mb_substr($key, 0, 191);
    }

    /**
     * Möglicher Doppel-Eingang (MVP-1145): ein früherer, nicht abgelehnter
     * Eingang mit derselben Nummer und derselben Aussteller-Kennung bzw.
     * derselben Partei. Ohne beides kein Treffer — Nummern wie „1001“ gibt es
     * bei vielen Lieferanten.
     */
    public function refreshDuplicate(): void {
        $this->invoice_number_key = ReferenceTokens::key($this->invoice_number);
        $vat = trim((string) $this->seller_vat_id);
        if ($this->invoice_number_key === null || ($vat === '' && $this->supplier_id === null && $this->customer_id === null)) {
            $this->duplicate_of_id = null;

            return;
        }

        $this->duplicate_of_id = self::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $this->organization_id)
            ->where('invoice_number_key', $this->invoice_number_key)
            ->whereNotIn('status', self::CLOSED_STATUSES)
            ->when($this->exists, fn (Builder $query) => $query->where('id', '<', $this->getKey()))
            ->where(function (Builder $query) use ($vat): void {
                if ($vat !== '') {
                    $query->orWhere('seller_vat_id', $vat);
                }
                if ($this->supplier_id !== null) {
                    $query->orWhere('supplier_id', $this->supplier_id);
                }
                if ($this->customer_id !== null) {
                    $query->orWhere('customer_id', $this->customer_id);
                }
            })
            ->orderBy('id')
            ->value('id');
    }

    /** Sperrt die Übergabe, bis jemand „kein Doppel“ bestätigt oder der frühere Eingang abgelehnt ist. */
    public function isPossibleDuplicate(): bool {
        return $this->duplicate_of_id !== null
            && $this->duplicate_cleared_at === null
            && $this->duplicateOf?->status !== IncomingEInvoiceStatus::Rejected;
    }

    /** @return BelongsTo<self, $this> */
    public function duplicateOf(): BelongsTo {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

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
     * Steueraufschlüsselung je Satz: nach einer Erfassung im Werte-Dialog
     * deren Zeilen (`summary.manual`, auch keine), sonst die der E-Rechnung
     * bzw. der Erkennung. Eine Neuanalyse überschreibt die Erfassung nicht.
     *
     * @return list<array<string, mixed>> Zeilen mit `percent`, `net`, `tax` (E-Rechnung zusätzlich `category`)
     */
    public function taxBreakdown(): array {
        $summary = (array) $this->summary;
        $rows = isset($summary['manual'])
            ? data_get($summary, 'manual.tax_breakdown', [])
            : ($summary['tax_breakdown'] ?? []);

        return array_values(array_filter(is_array($rows) ? $rows : [], is_array(...)));
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

    /**
     * Sichtbar ist, was das Original im DMS sehen lässt (Arbeitsliste, Arbeitsvorrat).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder {
        return $query->whereIn('document_id', Document::query()->visibleTo($user)->select('id'));
    }

    /**
     * Reiter „Zuordnen“ (Feature 163, MVP-1110): noch ohne Partei.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAwaitingAssignment(Builder $query): Builder {
        return $query->whereNull('supplier_id')->whereNull('customer_id')
            ->whereNotIn('status', self::CLOSED_STATUSES);
    }

    /**
     * Reiter „Prüfen“: Partei gesetzt, Beleg noch offen oder mit Rückfrage.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAwaitingReview(Builder $query): Builder {
        return $query->where(static fn ($party) => $party->whereNotNull('supplier_id')->orWhereNotNull('customer_id'))
            ->whereIn('status', [IncomingEInvoiceStatus::Received->value, IncomingEInvoiceStatus::Question->value]);
    }

    /**
     * Übergaben an das Buchhaltungsziel in einem der Zustände.
     *
     * @param  Builder<self>  $query
     * @param  list<IncomingInvoiceTransferStatus>  $states
     * @return Builder<self>
     */
    public function scopeInTransfer(Builder $query, array $states): Builder {
        // Ein aufgeteilter Sammel-Eingang (Feature 208) wird nie übergeben; seine alten Übergabe-Einträge zählen nicht.
        return $query->where($query->qualifyColumn('status'), '!=', IncomingEInvoiceStatus::Split->value)->whereHas('transfers', static fn ($journal) => $journal->whereIn('status', array_map(static fn (IncomingInvoiceTransferStatus $s): string => $s->value, $states)));
    }

    /**
     * Möglicher Doppel-Eingang (MVP-1145): offen, solange weder bestätigt noch einer der beiden abgelehnt ist.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOpenDuplicates(Builder $query): Builder {
        return $query->whereNotNull('duplicate_of_id')->whereNull('duplicate_cleared_at')
            ->whereNotIn('status', self::CLOSED_STATUSES)
            ->whereHas('duplicateOf', static fn ($earlier) => $earlier->withoutGlobalScopes()->whereNotIn('status', self::CLOSED_STATUSES));
    }

    /**
     * Abgelehnt (MVP-1212): „Keine Rechnung“.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeRejected(Builder $query): Builder {
        return $query->where('status', IncomingEInvoiceStatus::Rejected->value);
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

    /** Name der Gegenpartei laut Beleg: Verkäufer beim Eingang, Käufer beim Ausgang. */
    public function counterpartyName(): ?string {
        $name = $this->direction === DocumentDirection::Outgoing ? $this->buyer_name : $this->seller_name;

        return is_string($name) && trim($name) !== '' ? $name : null;
    }

    /**
     * Offene Eingänge derselben Richtung, deren Gegenpartei laut Beleg denselben
     * Namen trägt (MVP-1208) — für ausdrückliche Sammelentscheidungen im Dialog,
     * nie für den automatischen Abgleich.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public function openOfSameParty(): \Illuminate\Database\Eloquent\Collection {
        $key = self::nameKey($this->counterpartyName());
        if ($key === null) {
            return new \Illuminate\Database\Eloquent\Collection();
        }
        $outgoing = $this->direction === DocumentDirection::Outgoing;
        $column = $outgoing ? 'buyer_name' : 'seller_name';

        return self::query()->withoutGlobalScopes()
            ->where('organization_id', $this->organization_id)
            ->where('direction', ($outgoing ? DocumentDirection::Outgoing : DocumentDirection::Incoming)->value)
            ->whereNull('supplier_id')->whereNull('customer_id')
            ->whereNull('transferred_at')
            ->whereNotIn('status', self::CLOSED_STATUSES)
            ->whereNotNull($column)
            ->whereKeyNot($this->getKey())
            ->orderBy('id')
            ->get()
            ->filter(static fn (self $other): bool => self::nameKey($other->{$column}) === $key)
            ->values();
    }

    /** @return BelongsTo<IncomingInvoiceTemplate, $this> Belegvorlage (MVP-1219) */
    public function template(): BelongsTo {
        return $this->belongsTo(IncomingInvoiceTemplate::class, 'template_id');
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo {
        return $this->belongsTo(Document::class);
    }

    /**
     * Abgelegtes Original (aktuelle Dokumentversion) für Nacherkennung,
     * Neuvalidierung und Anbieter-Import; null ohne Version oder Datei.
     *
     * @return array{contents: string, mime: ?string, path: string, name: string}|null
     */
    public function storedOriginal(): ?array {
        $version = $this->document?->currentVersion;
        if ($version === null) {
            return null;
        }
        $disk = Storage::disk((string) ($version->disk ?: 'local'));
        $path = (string) $version->path;
        if ($path === '' || ! $disk->exists($path)) {
            return null;
        }

        return [
            'contents' => (string) $disk->get($path),
            'mime' => $version->mime !== null ? (string) $version->mime : null,
            'path' => $disk->path($path),
            'name' => (string) $version->original_name,
        ];
    }

    /** @return HasMany<IncomingInvoiceRetention, $this> Einbehalte (MVP-953) */
    public function retentions(): HasMany {
        return $this->hasMany(IncomingInvoiceRetention::class, 'incoming_einvoice_id')->orderBy('id');
    }

    /** @return BelongsTo<Document, $this> Unverändertes Original einer Sammel-PDF, aus dem dieser Teil stammt (MVP-1662) */
    public function sourceDocument(): BelongsTo {
        return $this->belongsTo(Document::class, 'source_document_id');
    }

    /** Teil einer aufgeteilten Sammel-PDF (MVP-1662). */
    public function isSplitPart(): bool {
        return $this->source_document_id !== null;
    }

    /** Aufgeteilter Sammel-Eingang (MVP-1662): Nachweis, nicht mehr bearbeitbar. */
    public function isSplitBundle(): bool {
        return $this->status === IncomingEInvoiceStatus::Split;
    }

    /** Abgelehnt oder aufgeteilt: keine Zuordnung, keine Werte, keine Übergabe, keine Zahlung. */
    public function isClosed(): bool {
        return in_array($this->status->value, self::CLOSED_STATUSES, true);
    }

    /**
     * Original nur, wenn es noch den Hash des Eingangs trägt (Feature 208, N3):
     * eine später im DMS hochgeladene Version ist nicht die geprüfte Datei.
     *
     * @return array{contents: string, mime: ?string, path: string, name: string}|null
     */
    public function verifiedOriginal(): ?array {
        $original = $this->storedOriginal();
        if ($original === null || ! hash_equals($this->sha256, (string) \CommonToolkit\Helper\Data\CryptoHelper::hash($original['contents']))) {
            return null;
        }

        return $original;
    }

    /**
     * Ohne abgelehnte und aufgeteilte Eingänge.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOpenForWork(Builder $query): Builder {
        return $query->whereNotIn($query->qualifyColumn('status'), self::CLOSED_STATUSES);
    }

    /** Stand der Grenzerkennung einer Sammel-PDF (MVP-1662); null = keine Sammel-PDF-Prüfung. */
    public function splitState(): ?string {
        $state = ((array) $this->split_proposal)['state'] ?? null;

        return is_string($state) && $state !== '' ? $state : null;
    }

    /**
     * Gebucht im Sinne des Löschschutzes einer Sammel-PDF (MVP-1662): fachlich
     * oder zur Zahlung freigegeben, an die Buchhaltung übergeben oder in einem
     * Zahllauf. Bewusst weit — das Original muss bleiben, sobald ein Teil
     * Folgen in Buchhaltung oder Zahlung haben kann.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeBooked(Builder $query): Builder {
        return $query->where(static fn (Builder $booked) => $booked
            ->whereIn('status', [IncomingEInvoiceStatus::Approved->value, IncomingEInvoiceStatus::PaymentReleased->value])
            ->orWhereNotNull('transferred_at')
            ->orWhereNotNull('paid_in_run_id')
            ->orWhereHas('transfers', static fn ($journal) => $journal->whereIn('status', [IncomingInvoiceTransferStatus::Transferred->value, IncomingInvoiceTransferStatus::Linked->value])));
    }
}
