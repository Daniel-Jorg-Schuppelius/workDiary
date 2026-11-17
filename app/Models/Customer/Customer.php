<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Customer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Customer;

use App\Casts\{BicCast, GermanTaxNumberCast, IbanCast, MoneyCast, PercentageCast, VatNumberCast};
use App\Enums\Numbering\NumberScope;
use App\Enums\Project\ProjectStatus;
use App\Models\Asset\Asset;
use App\Models\Concerns\{Archivable, Auditable, BelongsToOrganization, GeneratesUniqueSlug, HasAttachments, HasClassifications, HasCommunicationNotes, HasContactAndBankDetails, HasCustomFields, HasPartyDisplayLabel, HasPhoneSearchKeys, HasSequentialNumber, HasSqid, HasStructuredContactName, HasTags, MatchesIdentifiers, Searchable};
use App\Models\Contacts\ContactAddress;
use App\Models\Contracts\{ContactDetailsHolder, ContactNameHolder, CustomFieldSubject};
use App\Models\Facility\{Room, Site};
use App\Models\Integration\ExternalReference;
use App\Models\Material\MaterialCostAllocation;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Services\Stammdaten\ContactPersons;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne, MorphMany};
use Illuminate\Support\Carbon;

/**
 * @property bool $is_collective
 * @property int $id
 * @property int|null $organization_id
 * @property string $name
 * @property string|null $slug
 * @property string|null $number
 * @property string|null $matchcode
 * @property-read string|null $accounting_number Nummer(n) in angebundenen Buchhaltungsprogrammen (MVP-1028)
 * @property string $number_source
 * @property string|null $company
 * @property string|null $vat_id
 * @property string|null $tax_number
 * @property string|null $register_number Handelsregister (BT-47 der E-Rechnung, MVP-1686)
 * @property bool $is_consumer Verbraucher statt Unternehmen: Leistungsort nach § 3a UStG (MVP-1688)
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
 * @property string|null $document_locale Belegsprache (NULL = Organisation; Feature 034, MVP-721)
 * @property string|null $color
 * @property \CommonToolkit\ValueObjects\Money|null $hourly_rate
 * @property \CommonToolkit\ValueObjects\Money|null $internal_rate
 * @property string|null $comment
 * @property string|null $invoice_text
 * @property string|null $bank_account_holder
 * @property string|null $bank_iban
 * @property string|null $bank_bic
 * @property string|null $bank_name
 * @property bool $billable
 * @property bool $exclude_from_reports
 * @property bool $no_bulk_mail
 * @property bool $survey_opt_out
 * @property string|null $buyer_reference
 * @property string|null $peppol_participant_id
 * @property string|null $peppol_scheme
 * @property \App\Enums\Invoicing\InvoiceDeliveryFormat|null $delivery_format
 * @property \App\Enums\Invoicing\EInvoiceRecipientProfile|null $einvoice_recipient_profile
 * @property bool|null $einvoice_attach_activity_report
 * @property int|null $einvoice_attachment_limit_mb
 * @property \App\Enums\Invoicing\ExternalDispatchRoute|null $einvoice_dispatch_route
 * @property int<0, max>|null $payment_terms_days
 * @property \CommonToolkit\ValueObjects\Percentage|null $skonto_percent
 * @property int<0, max>|null $skonto_days
 * @property string|null $debtor_no
 * @property Carbon|null $archived_at
 * @property Carbon|null $relationship_ended_on Ende der Geschäftsbeziehung — Anker der Aufbewahrung der Aufnahmeakte (Feature 200, § 8 Abs. 4 GwG)
 * @property int|null $created_by
 */
class Customer extends Model implements ContactDetailsHolder, ContactNameHolder, CustomFieldSubject {
    use Archivable;
    use Auditable;
    use BelongsToOrganization;
    use GeneratesUniqueSlug;
    use HasAttachments;
    use HasClassifications;

    use HasCommunicationNotes;
    use HasContactAndBankDetails;
    use HasCustomFields;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasPartyDisplayLabel;
    use HasPhoneSearchKeys;
    use HasSequentialNumber;
    use HasSqid;
    use HasStructuredContactName;
    use HasTags;
    use MatchesIdentifiers;
    use Searchable;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'number',
        'matchcode',
        'number_source',
        'company',
        'vat_id',
        'tax_number',
        // Registerangabe (BT-47): ersetzt bei § 13b die fehlende USt-IdNr. (MVP-1686).
        'register_number',
        // Leistungsempfänger schuldet die Steuer (§ 13b UStG, MVP-1622).
        'is_reverse_charge_recipient',
        // Verbraucher (MVP-1688): Leistungsort nach § 3a Abs. 1/4 statt Abs. 2 UStG.
        'is_consumer',
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
        // Belegsprache (Feature 034, MVP-721): NULL = wie Organisation.
        'document_locale',
        'color',
        'hourly_rate',
        'internal_rate',
        'billing_increment_minutes',
        'billing_grouping_gap_minutes',
        'travel_settings',
        'portal_settings',
        'comment',
        'invoice_text',
        'document_render_profile_id',
        'bank_account_holder',
        'bank_iban',
        'bank_bic',
        'bank_name',
        'billable',
        'billing_mode',
        'billing_cutover_on',
        'billing_cutover_from',
        'buyer_reference',
        // Peppol-Empfängerkennung (Feature 066, MVP-734): `<ICD>:<Kennung>`.
        'peppol_participant_id',
        'peppol_scheme',
        'delivery_format',
        // Empfängerprofil der E-Rechnung (Feature 196): null = Vorgabe der Organisation.
        'einvoice_recipient_profile',
        'einvoice_attach_activity_report',
        'einvoice_attachment_limit_mb',
        'einvoice_dispatch_route',
        // Zahlungsbedingungen als Vorgabe für neue Rechnungen (MVP-996).
        'payment_terms_days',
        'skonto_percent',
        'skonto_days',
        'debtor_no',
        'exclude_from_reports',
        // Rundschreiben-Opt-out (Feature 119, MVP-608) — getrennt von der
        // Auswertungs-Ausblendung, weil es eine andere Entscheidung ist.
        'no_bulk_mail',
        'survey_opt_out',
        'archived_at',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'contact_salutation' => \App\Enums\Stammdaten\ContactSalutation::class,
        // Bankdaten sind eine Projektion des primären Bankkontos, das seit je
        // verschlüsselt liegt — flach zurückgeschrieben hob das die
        // Verschlüsselung auf (Sicherheitsscan S-21). Spalten als text.
        'bank_iban' => IbanCast::class . ':encrypted',
        'bank_bic' => BicCast::class . ':encrypted',
        'bank_account_holder' => 'encrypted',
        // Kennungen: geschrieben kanonisch, gelesen Text (MVP-1512).
        'vat_id' => VatNumberCast::class,
        'tax_number' => GermanTaxNumberCast::class,
        'is_reverse_charge_recipient' => 'boolean',
        'is_consumer' => 'boolean',
        'billing_cutover_on' => 'date',
        'currency' => \CommonToolkit\Enums\CurrencyCode::class,
        'billable' => 'boolean',
        'exclude_from_reports' => 'boolean',
        'no_bulk_mail' => 'boolean',
        'survey_opt_out' => 'boolean',
        'billing_mode' => \App\Enums\Finance\BillingMode::class,
        // Kunden-Default fürs E-Rechnungs-Ausgabeformat (NULL = PDF).
        'delivery_format' => \App\Enums\Invoicing\InvoiceDeliveryFormat::class,
        'einvoice_recipient_profile' => \App\Enums\Invoicing\EInvoiceRecipientProfile::class,
        'einvoice_attach_activity_report' => 'boolean',
        'einvoice_attachment_limit_mb' => 'integer',
        'einvoice_dispatch_route' => \App\Enums\Invoicing\ExternalDispatchRoute::class,
        'payment_terms_days' => 'integer',
        'skonto_percent' => PercentageCast::class . ':2',
        'skonto_days' => 'integer',
        'archived_at' => 'datetime',
        'relationship_ended_on' => 'date',
        'hourly_rate' => MoneyCast::class . ':currency,2',
        'internal_rate' => MoneyCast::class . ':currency,2',
        'billing_increment_minutes' => 'integer',
        'billing_grouping_gap_minutes' => 'integer',
        'travel_settings' => 'array',
        // Portal-Sichtbarkeiten (MVP-511): NULL = alles deny (Default-Deny).
        'portal_settings' => 'array',
        'address_lat' => 'decimal:7',
        'address_lng' => 'decimal:7',
        'contact_persons' => 'array',
        // Sammelkontakt (MVP-1109); setzt nur App\Services\Stammdaten\CollectiveContacts.
        'is_collective' => 'boolean',
    ];

    protected static function booted(): void {
        self::registerSequentialNumberHook();

        // Belege hängen per ON DELETE CASCADE am Kunden und gingen sonst mit.
        static::deleting(static fn(self $customer) => app(\App\Services\Stammdaten\PartyDeletionGuard::class)->assertDeletable($customer));

        self::assignSlugWhenMissing(static fn(self $customer): string => self::uniqueSlug(
            (string) $customer->name,
            $customer->organization_id,
            $customer->exists ? $customer->id : null,
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
     * Kürzel, die als erstes Segment der Projekt-URL ("<kunde>/<projekt>")
     * schon belegt sind: der Platzhalter für Projekte ohne Kunden und feste
     * Pfade unter /projects. Ein Kunde „Intern" verlinkte sonst auf fremde
     * interne Projekte. Gate: ReservedCustomerSlugTest.
     */
    public const array RESERVED_SLUGS = ['intern', 'create', 'duplicates', 'times'];

    /**
     * Liefert einen Slug, der innerhalb der angegebenen Organisation
     * eindeutig ist (Sentinel "kunde" falls Name keinen Slug ergibt).
     */
    public static function uniqueSlug(string $name, ?int $organizationId, ?int $ignoreId = null): string {
        return self::resolveUniqueSlug($name, 'kunde', fn(string $slug): bool => in_array($slug, self::RESERVED_SLUGS, true) ||
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

    public function contactAddressKind(): string {
        return ContactAddress::KIND_BILLING;
    }

    /** @return MorphMany<\App\Models\Diary\OpenIssue, $this> */
    public function openIssues(): MorphMany {
        /** @var MorphMany<\App\Models\Diary\OpenIssue, $this> $relation */
        $relation = $this->morphMany(\App\Models\Diary\OpenIssue::class, 'subject')->latest('id');

        return $relation;
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
     *
     * billable bleibt ohne Config-Override NULL (erbt vom Kunden) — ein
     * explizites billable=1 würde nicht abrechenbare Kunden dauerhaft in
     * „Offene Zeiten" halten, weil der Billable-Sync Overrides nie anfasst.
     */
    public function defaultProjectOrCreate(): Project {
        $existing = $this->defaultProject();
        if ($existing instanceof Project) {
            return $existing;
        }

        $billable = config('project.default_project.billable');

        /** @var Project $project */
        $project = $this->projects()->create([
            'organization_id' => $this->organization_id,
            'name' => (string) config('project.default_project.name', 'Wartung'),
            'color' => (string) config('project.default_project.color', '#64748b'),
            'status' => ProjectStatus::Active->value,
            'is_default' => true,
            'billable' => $billable === null ? null : StringHelper::parseBool($billable) ?? false,
        ]);

        return $project;
    }

    /** @return MorphMany<ExternalReference, $this> */
    public function externalReferences(): MorphMany {
        return $this->morphMany(ExternalReference::class, 'referenceable');
    }

    /**
     * Nummer(n) des Kunden in angebundenen Buchhaltungsprogrammen, aus den
     * Referenzen der Plugins (MVP-1028) — für Anzeige, Abgleich und Dubletten.
     */
    public function getAccountingNumberAttribute(): ?string {
        $numbers = $this->externalReferences->pluck('external_number')->filter()->unique()->implode(', ');

        return $numbers !== '' ? $numbers : null;
    }

    /** @return HasOne<\App\Models\Billing\CustomerBillingAgreement, $this> */
    /**
     * Beitragskonto der Vereinsverwaltung (MVP-849) — höchstens eines je Kunde.
     *
     * @return HasOne<\App\Models\Club\ClubFeeAccount, $this>
     */
    public function feeAccount(): HasOne {
        return $this->hasOne(\App\Models\Club\ClubFeeAccount::class);
    }

    /** @return HasOne<\App\Models\Billing\CustomerBillingAgreement, $this> */
    public function billingAgreement(): HasOne {
        return $this->hasOne(\App\Models\Billing\CustomerBillingAgreement::class);
    }

    /** @return HasMany<MaterialCostAllocation, $this> */
    public function materialCostAllocations(): HasMany {
        return $this->hasMany(MaterialCostAllocation::class);
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

    /**
     * Auswertungssicht (Feature 002): org-weit ausgeblendete Kunden
     * (exclude_from_reports, z. B. Arbeitgeber mit separater Abrechnung)
     * herausfiltern. Stammdaten/Zeiterfassung bleiben unberührt.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleInReports(Builder $query): Builder {
        return $query->where('exclude_from_reports', false);
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
     * Adressen, unter denen der Kunde schreibt: Stammadresse, Ansprechpartner
     * und Portalzugänge — Grundlage der Absenderprüfung (KnownSenders).
     *
     * @return list<string>
     */
    public function knownEmailAddresses(): array {
        $known = [(string) $this->email];
        foreach (ContactPersons::normalizeAll($this->contact_persons ?? []) as $person) {
            $known[] = (string) ($person['email'] ?? '');
        }
        foreach (User::query()->withoutGlobalScopes()
            ->where('organization_id', $this->organization_id)
            ->where('customer_id', $this->getKey())
            ->pluck('email') as $portalEmail) {
            $known[] = (string) $portalEmail;
        }

        return array_values(array_filter($known, static fn (string $email): bool => trim($email) !== ''));
    }
}
