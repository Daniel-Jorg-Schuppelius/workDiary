<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Supplier.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Numbering\NumberScope;
use App\Models\Concerns\{Archivable, Auditable, BelongsToOrganization, GeneratesUniqueSlug, HasAttachments, HasContactAndBankDetails, HasPartyDisplayLabel, HasPhoneSearchKeys, HasSequentialNumber, HasSqid, HasTags, Searchable};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphMany};
use Illuminate\Support\Carbon;

/**
 * Lieferant: Geschäftspartner, von dem wir Waren/Leistungen beziehen.
 * Spiegelt {@see Customer}, jedoch ohne Abrechnungs-/Stundensatzfelder.
 * Mappt im Lexoffice-Kontakt-Sync auf `roles.vendor`.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $name
 * @property string|null $slug
 * @property string|null $number
 * @property string|null $vendor_number
 * @property string $number_source
 * @property string|null $company
 * @property string|null $vat_id
 * @property string|null $tax_number
 * @property string|null $contact_name
 * @property array<int, array{name?: string, email?: string, phone?: string, primary?: bool}>|null $contact_persons
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
 * @property Carbon|null $archived_at
 * @property int|null $created_by
 */
class Supplier extends Model {
    use Archivable;
    use Auditable;
    use BelongsToOrganization;
    use GeneratesUniqueSlug;
    use HasAttachments;

    use HasContactAndBankDetails;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasPartyDisplayLabel;
    use HasPhoneSearchKeys;
    use HasSequentialNumber;
    use HasSqid;
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
        'contact_name',
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
        'archived_at',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'currency' => \CommonToolkit\Enums\CurrencyCode::class,
        'active' => 'boolean',
        'archived_at' => 'datetime',
        'address_lat' => 'decimal:7',
        'address_lng' => 'decimal:7',
        'contact_persons' => 'array',
    ];

    protected static function booted(): void {
        self::registerSequentialNumberHook();

        static::saving(function (self $supplier): void {
            if ($supplier->slug === null || $supplier->slug === '') {
                $supplier->slug = self::uniqueSlug(
                    (string) $supplier->name,
                    $supplier->organization_id,
                    $supplier->exists ? $supplier->id : null,
                );
            }
        });
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
     * {@see \App\Services\SupplierDuplicateFinder} (der Datensatz mit der
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

    /** @return MorphMany<ContactAddress, $this> */
    public function addresses(): MorphMany {
        return $this->morphMany(ContactAddress::class, 'addressable');
    }

    /** @return MorphMany<ContactBankAccount, $this> */
    public function bankAccounts(): MorphMany {
        return $this->morphMany(ContactBankAccount::class, 'accountable');
    }

    public function primaryAddress(): ?ContactAddress {
        return $this->addresses()->where('is_primary', true)->first()
            ?? $this->addresses()->first();
    }

    public function primaryBankAccount(): ?ContactBankAccount {
        return $this->bankAccounts()->where('is_primary', true)->first()
            ?? $this->bankAccounts()->first();
    }

    /** @return list<string> */
    protected function searchableColumns(): array {
        return ['name', 'number', 'company', 'email'];
    }
}
