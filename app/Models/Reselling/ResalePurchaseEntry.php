<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResalePurchaseEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Reselling;

use App\Casts\MoneyCast;
use App\Enums\Reselling\SubscriptionProvider;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Domain\DomainAccountingEntry;
use App\Models\{Organization, User};
use App\Services\Reselling\Purchase\{PurchaseDocument, PurchaseDocuments};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Einkaufsbeleg-Zeile (Feature 152, MVP-762): Ist-Einkauf einer Periode
 * aus Eingangsbeleg, Domain-Buchung oder Handeingabe. Der Eingangsbeleg
 * hängt als Morph (`document_type/document_id`, Review 2026-09-11) an einer
 * Quelle der {@see PurchaseDocuments} — Lexoffice-Spiegel, Ausgabe oder
 * Eingangs-E-Rechnung; eine anbieterspezifische Spalte gibt es nicht mehr.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $subscription_id
 * @property int|null $period_id
 * @property SubscriptionProvider $provider
 * @property string $source
 * @property string|null $document_type
 * @property int|null $document_id
 * @property int|null $domain_accounting_entry_id
 * @property string|null $document_number
 * @property CarbonImmutable $entry_date
 * @property string|null $description
 * @property Money $net_amount
 * @property CurrencyCode $currency
 * @property string $raw_hash
 * @property int|null $created_by_user_id
 */
class ResalePurchaseEntry extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const SOURCE_VOUCHER = 'voucher';
    public const SOURCE_DOMAIN = 'domain_accounting';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_PROVIDER_INVOICE = 'provider_invoice';

    protected $table = 'resale_purchase_entries';

    protected $fillable = [
        'organization_id',
        'subscription_id',
        'period_id',
        'provider',
        'source',
        'document_type',
        'document_id',
        'domain_accounting_entry_id',
        'document_number',
        'entry_date',
        'description',
        'net_amount',
        'currency',
        'raw_hash',
        'created_by_user_id',
    ];

    protected $casts = [
        'provider' => SubscriptionProvider::class,
        'document_id' => 'integer',
        'entry_date' => 'immutable_date',
        'currency' => CurrencyCode::class,
        'net_amount' => MoneyCast::class . ':currency,2',
    ];

    private ?PurchaseDocument $purchaseDocument = null;

    private bool $purchaseDocumentResolved = false;

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<ResaleSubscription, $this> */
    public function subscription(): BelongsTo {
        return $this->belongsTo(ResaleSubscription::class, 'subscription_id');
    }

    /** @return BelongsTo<ResalePeriod, $this> */
    public function period(): BelongsTo {
        return $this->belongsTo(ResalePeriod::class, 'period_id');
    }

    /**
     * Eingangsbeleg der Zeile (Lexoffice-Beleg, Ausgabe, Eingangs-E-Rechnung).
     *
     * @return MorphTo<Model, $this>
     */
    public function document(): MorphTo {
        return $this->morphTo('document', 'document_type', 'document_id');
    }

    /** @return BelongsTo<DomainAccountingEntry, $this> */
    public function domainAccountingEntry(): BelongsTo {
        return $this->belongsTo(DomainAccountingEntry::class, 'domain_accounting_entry_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function sourceLabel(): string {
        return (string) __('resale.purchase.source.' . $this->source);
    }

    /**
     * Belegbezug: Morph-Typ und ID; [null, null] ohne Beleg (Anbieterrechnung, Domain-Buchung, Handeingabe).
     *
     * @return array{0: string|null, 1: int|null}
     */
    public function documentReference(): array {
        if ($this->document_type !== null && $this->document_id !== null) {
            return [(string) $this->document_type, (int) $this->document_id];
        }

        return [null, null];
    }

    /** Vorgeladenen Beleg anhängen ({@see PurchaseDocuments::preload()}). */
    public function attachDocument(?PurchaseDocument $document): void {
        $this->purchaseDocument = $document;
        $this->purchaseDocumentResolved = true;
    }

    /** Beleg aus der Quelle des Morph-Typs — vorgeladen, sonst einzeln; null ohne Quelle/Beleg. */
    public function purchaseDocument(): ?PurchaseDocument {
        if (! $this->purchaseDocumentResolved) {
            $this->purchaseDocument = app(PurchaseDocuments::class)->forEntry($this);
            $this->purchaseDocumentResolved = true;
        }

        return $this->purchaseDocument;
    }

    /** Anzeige des Belegs: Kennung laut Quelle, sonst gespeicherte Belegnummer. */
    public function documentLabel(): ?string {
        $document = $this->purchaseDocument();
        if ($document !== null) {
            return $document->reference();
        }

        return $this->document_number;
    }
}
