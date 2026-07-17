<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Customer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Numbering\NumberScope;
use App\Enums\Project\ProjectStatus;
use App\Models\Concerns\{Archivable, Auditable, BelongsToOrganization, GeneratesUniqueSlug, HasAttachments, HasClassifications, HasContactAndBankDetails, HasSequentialNumber, HasSqid, HasTags, Searchable};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphMany};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property string $name
 * @property string|null $slug
 * @property string|null $number
 * @property string|null $lexoffice_contact_number
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
 * @property string|null $hourly_rate
 * @property string|null $internal_rate
 * @property string|null $comment
 * @property string|null $invoice_text
 * @property string|null $bank_account_holder
 * @property string|null $bank_iban
 * @property string|null $bank_bic
 * @property string|null $bank_name
 * @property bool $billable
 * @property string|null $buyer_reference
 * @property string|null $debtor_no
 * @property Carbon|null $archived_at
 * @property int|null $created_by
 */
class Customer extends Model {
    use Archivable;
    use Auditable;
    use BelongsToOrganization;
    use GeneratesUniqueSlug;
    use HasAttachments;
    use HasClassifications;

    use HasContactAndBankDetails;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSequentialNumber;
    use HasSqid;
    use HasTags;
    use Searchable;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'number',
        'lexoffice_contact_number',
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
        'hourly_rate',
        'internal_rate',
        'billing_increment_minutes',
        'billing_grouping_gap_minutes',
        'travel_settings',
        'comment',
        'invoice_text',
        'invoice_template_id',
        'bank_account_holder',
        'bank_iban',
        'bank_bic',
        'bank_name',
        'billable',
        'billing_mode',
        'buyer_reference',
        'debtor_no',
        'archived_at',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'currency' => \CommonToolkit\Enums\CurrencyCode::class,
        'billable' => 'boolean',
        'billing_mode' => \App\Enums\Finance\BillingMode::class,
        'archived_at' => 'datetime',
        'hourly_rate' => 'decimal:2',
        'internal_rate' => 'decimal:2',
        'billing_increment_minutes' => 'integer',
        'billing_grouping_gap_minutes' => 'integer',
        'travel_settings' => 'array',
        'address_lat' => 'decimal:7',
        'address_lng' => 'decimal:7',
        'contact_persons' => 'array',
    ];

    protected static function booted(): void {
        self::registerSequentialNumberHook();

        static::saving(function (self $customer): void {
            if ($customer->slug === null || $customer->slug === '') {
                $customer->slug = self::uniqueSlug(
                    (string) $customer->name,
                    $customer->organization_id,
                    $customer->exists ? $customer->id : null,
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
     * eindeutig ist (Sentinel "kunde" falls Name keinen Slug ergibt).
     */
    public static function uniqueSlug(string $name, ?int $organizationId, ?int $ignoreId = null): string {
        return self::resolveUniqueSlug($name, 'kunde', fn(string $slug): bool =>
            // TENANT-BYPASS: ohne Global Scope, weil $organizationId explizit übergeben wird;
            // der explizite where('organization_id', ...) erhält die Mandantengrenze.
            static::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('slug', $slug)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists());
    }

    protected static function numberScope(): NumberScope {
        return NumberScope::Customer;
    }

    protected static function numberFallback(): string {
        return 'K-0001';
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return MorphMany<CommunicationNote, $this> */
    public function communicationNotes(): MorphMany {
        /** @var MorphMany<CommunicationNote, $this> $relation */
        $relation = $this->morphMany(CommunicationNote::class, 'notable')->latest('occurred_at');

        return $relation;
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

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany {
        return $this->hasMany(Project::class);
    }

    /** @return HasMany<ForeignCustomer, $this> */
    public function foreignCustomers(): HasMany {
        return $this->hasMany(ForeignCustomer::class)->orderBy('name');
    }

    /** @return HasMany<Site, $this> */
    public function sites(): HasMany {
        return $this->hasMany(Site::class)->orderBy('name');
    }

    /** @return HasMany<Asset, $this> */
    public function assets(): HasMany {
        return $this->hasMany(Asset::class)->orderBy('name');
    }

    /** @return HasMany<Room, $this> */
    public function rooms(): HasMany {
        return $this->hasMany(Room::class)->orderBy('name');
    }

    public function defaultProject(): ?Project {
        return $this->projects()->where('is_default', true)->first();
    }

    /**
     * Standardprojekt des Kunden oder lazy anlegen (vom CustomerObserver bei `created`, auch als UI-/Service-Fallback).
     */
    public function defaultProjectOrCreate(): Project {
        $existing = $this->defaultProject();
        if ($existing instanceof Project) {
            return $existing;
        }

        /** @var Project $project */
        $project = $this->projects()->create([
            'organization_id' => $this->organization_id,
            'name' => (string) config('project.default_project.name', 'Wartung'),
            'color' => (string) config('project.default_project.color', '#64748b'),
            'status' => ProjectStatus::Active->value,
            'is_default' => true,
            'billable' => (bool) config('project.default_project.billable', true),
            'global_activities' => true,
        ]);

        return $project;
    }

    /** @return MorphMany<ExternalReference, $this> */
    public function externalReferences(): MorphMany {
        return $this->morphMany(ExternalReference::class, 'referenceable');
    }

    public function hasProjects(): bool {
        return $this->projects()->exists();
    }

    public function hasNonDefaultProjects(): bool {
        return $this->projects()->where('is_default', false)->exists();
    }

    /** @return list<string> */
    protected function searchableColumns(): array {
        return ['name', 'number', 'company', 'email'];
    }

    /**
     * Kunden mit mindestens einem abrechenbaren, noch nicht exportierten Zeiteintrag.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithUnexportedBillable(Builder $query): Builder {
        return $query->whereHas('projects.timeEntries', function (Builder $q): void {
            $q->where('billable', true)->where('exported', false);
        });
    }
}
