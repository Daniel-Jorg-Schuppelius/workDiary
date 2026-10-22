<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Supplier.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Supplier;

use App\Enums\Numbering\NumberScope;
use App\Models\Concerns\{Archivable, Auditable, BelongsToOrganization, GeneratesUniqueSlug, HasAttachments, HasCommunicationNotes, HasContactAndBankDetails, HasPartyDisplayLabel, HasPhoneSearchKeys, HasSequentialNumber, HasSqid, HasStructuredContactName, HasTags, Searchable};
use App\Models\Contacts\ContactAddress;
use App\Models\Contracts\{ContactDetailsHolder, ContactNameHolder};
use App\Models\Integration\ExternalReference;
use App\Models\Platform\User;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphMany};
use Illuminate\Support\Carbon;

/**
 * Lieferant: Geschäftspartner, von dem wir Waren/Leistungen beziehen.
 * Spiegelt {@see Customer}, jedoch ohne Abrechnungs-/Stundensatzfelder.
 * Mappt im Lexoffice-Kontakt-Sync auf `roles.vendor`.
 *
 * @property bool $is_collective
 * @property int $id
 * @property int|null $organization_id
 * @property string $name
 * @property string|null $slug
 * @property string|null $number
 * @property string|null $vendor_number
 * @property string $number_source
 * @property string|null $company
 * @property string|null $vat_id
 * @property \Illuminate\Support\Carbon|null $self_billing_agreed_on
 * @property string|null $tax_number
 * @property string|null $contact_name Ansprechpartner zur Anzeige, gebildet aus den Teilen (MVP-1136)
 * @property \App\Enums\Stammdaten\ContactSalutation|null $contact_salutation
 * @property string|null $contact_title
 * @property string|null $contact_first_name
 * @property string|null $contact_middle_names
 * @property string|null $contact_last_name
 * @property array<int, array{salutation?: ?string, title?: ?string, first_name?: ?string, middle_names?: ?string, last_name?: ?string, name?: ?string, email?: ?string, phone?: ?string, primary?: bool}>|null $contact_persons
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $mobile
 * @property string|null $fax
 * @property string|null $homepage
 * @property string|null $address
 * @property string|null $address_street
 * @property string|null $address_zip
 * @property string|null $address_city
 * @property string|null $country
 * @property \CommonToolkit\Enums\CurrencyCode $currency
 * @property string|null $timezone
 * @property string|null $color
 * @property string|null $comment
 * @property string|null $bank_account_holder
 * @property string|null $bank_iban
 * @property string|null $bank_bic
 * @property string|null $bank_name
 * @property bool $active
 * @property bool $exclude_from_reports
 * @property Carbon|null $archived_at
 * @property int|null $created_by
 */
class Supplier extends Model implements ContactDetailsHolder, ContactNameHolder {
    use Archivable;
    use Auditable;
    use BelongsToOrganization;
    use GeneratesUniqueSlug;
    use HasAttachments;
    use HasCommunicationNotes;

    use HasContactAndBankDetails;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasPartyDisplayLabel;

    use HasPhoneSearchKeys;
    use HasSequentialNumber;
    use HasSqid;
    use HasStructuredContactName;
    use HasTags;
    use Searchable;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'number',
        'vendor_number',
        'number_source',
        'company',
        'vat_id',
        'tax_number',
        'self_billing_agreed_on',
        'contact_name',
        'contact_salutation',
        'contact_title',
        'contact_first_name',
        'contact_middle_names',
        'contact_last_name',
        'contact_persons',
        'email',
        'phone',
        'mobile',
        'fax',
        'homepage',
        'address',
        'address_street',
        'address_zip',
        'address_city',
        'address_lat',
        'address_lng',
        'country',
        'currency',
        'timezone',
        'color',
        'comment',
        'bank_account_holder',
        'bank_iban',
        'bank_bic',
        'bank_name',
        'active',
        'exclude_from_reports',
        'archived_at',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'contact_salutation' => \App\Enums\Stammdaten\ContactSalutation::class,
        // Bankdaten sind eine Projektion des primären Bankkontos, das seit je
        // verschlüsselt liegt — flach zurückgeschrieben hob das die
        // Verschlüsselung auf (Sicherheitsscan S-21). Spalten als text.
        'bank_iban' => 'encrypted',
        'bank_bic' => 'encrypted',
        'bank_account_holder' => 'encrypted',
        'currency' => \CommonToolkit\Enums\CurrencyCode::class,
        'active' => 'boolean',
        'exclude_from_reports' => 'boolean',
        'archived_at' => 'datetime',
        'self_billing_agreed_on' => 'date',
        'address_lat' => 'decimal:7',
        'address_lng' => 'decimal:7',
        'contact_persons' => 'array',
        // Sammelkontakt (MVP-1109); setzt nur App\Services\Stammdaten\CollectiveContacts.
        'is_collective' => 'boolean',
    ];

    protected static function booted(): void {
        self::registerSequentialNumberHook();

        // Bestellungen und Nachweise hängen per ON DELETE CASCADE am Lieferanten und gingen sonst mit.
        static::deleting(static fn(self $supplier) => app(\App\Services\Stammdaten\PartyDeletionGuard::class)->assertDeletable($supplier));

        self::assignSlugWhenMissing(static fn(self $supplier): string => self::uniqueSlug(
            (string) $supplier->name,
            $supplier->organization_id,
            $supplier->exists ? $supplier->id : null,
        ));
    }

    /**
     * Archivieren/Entarchivieren als eigene Audit-Events loggen (GoBD).
     *
     * @param  array<string, mixed>  $changes
     */
    protected function resolveAuditEvent(string $event, array $changes): string {
        return $this->mapArchivedAtAuditEvent($event, $changes);
    }

    /**
     * Liefert einen Slug, der innerhalb der angegebenen Organisation
     * eindeutig ist (Sentinel "lieferant" falls Name keinen Slug ergibt).
     */
    public static function uniqueSlug(string $name, ?int $organizationId, ?int $ignoreId = null): string {
        return self::resolveUniqueSlug($name, 'lieferant', fn(string $slug): bool =>
            // TENANT-BYPASS: Slug-Eindeutigkeit ohne Global Scope prüfen, weil
            // $organizationId explizit übergeben wird. Der explizite
            // where('organization_id', ...) erhält die Mandantengrenze.
            static::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('slug', $slug)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists());
    }

    protected static function numberScope(): NumberScope {
        return NumberScope::Supplier;
    }

    protected static function numberFallback(): string {
        return 'L-0001';
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Bestellungen bei diesem Lieferanten — Grundlage der Ziel-Heuristik des
     * {@see \App\Services\Stammdaten\SupplierDuplicateFinder} (der Datensatz mit der
     * Einkaufs-Historie gewinnt beim Zusammenführen).
     *
     * @return HasMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): HasMany {
        return $this->hasMany(PurchaseOrder::class);
    }

    /** @return MorphMany<ExternalReference, $this> */
    public function externalReferences(): MorphMany {
        return $this->morphMany(ExternalReference::class, 'referenceable');
    }

    public function contactAddressKind(): string {
        return ContactAddress::KIND_BILLING;
    }

    /** @return list<string> */
    protected function searchableColumns(): array {
        return ['name', 'number', 'company', 'email'];
    }

    /**
     * Ohne Sammelkontakt (MVP-1109): für Abgleich, Vorschläge und Stammdaten-Push.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithoutCollective(Builder $query): Builder {
        return $query->where($query->qualifyColumn('is_collective'), false);
    }

    /**
     * In den Lieferantenauswertungen ausgeblendet (MVP-1121), etwa Finanzamt,
     * Krankenkasse oder Berufsgenossenschaft: Steuern und Abgaben sind keine
     * Einkäufe und verzerren Konzentration und Segmente.
     *
     * @return list<int>
     */
    public static function excludedFromReportsIds(): array {
        return array_values(array_map(
            static fn($id): int => (int) $id,
            static::query()->where('exclude_from_reports', true)->pluck('id')->all(),
        ));
    }
}
