<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : User.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\User\{CompensationModel, UserRole};
use App\Legacy\LegacyBridge;
use App\Legacy\Models\LegacyUser;
use App\Models\Concerns\{Auditable, HasAttachments, HasEffectivePermissions, HasPreferences, HasSqid, InteractsWithTwoFactor, InteractsWithWorkSchedule, Searchable};
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\Data\{CryptoHelper, PhoneNumberHelper};
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphMany};
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property string $name
 * @property string|null $personnel_number
 * @property \CommonToolkit\ValueObjects\Money|null $payroll_hourly_wage
 * @property string|null $tax_identification_number
 * @property string|null $social_security_number
 * @property string|null $cti_extension
 * @property string|null $cti_extension_hash
 * @property Carbon|null $date_of_birth
 * @property string|null $health_insurance
 * @property string|null $tax_class
 * @property string|null $child_allowances
 * @property bool $church_tax
 * @property Carbon|null $employment_start_date
 * @property Carbon|null $employment_end_date
 * @property \App\Enums\User\EmploymentType|null $employment_type
 * @property string|null $first_name
 * @property string|null $middle_names
 * @property string|null $last_name
 * @property string|null $phone
 * @property string|null $mobile
 * @property string|null $fax
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property int|null $legacy_user_id
 * @property bool $is_new_system
 * @property bool $is_platform_admin
 * @property bool $must_change_password
 * @property \CommonToolkit\ValueObjects\Money|null $hourly_rate
 * @property \CommonToolkit\ValueObjects\Money|null $internal_rate
 * @property string|null $home_address
 * @property string|null $home_lat
 * @property string|null $home_lng
 * @property array<string, mixed>|null $preferences
 * @property Carbon|null $deactivated_at
 * @property string|null $portal_pending_email
 * @property Carbon|null $portal_pending_email_requested_at
 * @property Carbon|null $left_at
 * @property Carbon|null $anonymized_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SpatiePermission> $permissions
 * @property \CommonToolkit\ValueObjects\Money|null $flat_amount
 * @property \CommonToolkit\ValueObjects\Money|null $compensation_rate
 *
 * Fachfremde Methoden-Cluster liegen in Concerns (Refactoring Welle 2, B6b):
 * {@see HasPreferences} (Präferenz-Bag/Locale/Zeitzone/Arbeitsmodus),
 * {@see InteractsWithTwoFactor} (2FA-Helfer),
 * {@see HasEffectivePermissions} (Gruppen-/Rollen-Rechte) und
 * {@see InteractsWithWorkSchedule} (Arbeitszeit/Gleitzeit/Lohnfortzahlung).
 * Auth-/Rollenlogik, Relations und Casts bleiben bewusst hier — das Modell
 * trägt aus Sicherheitsgründen KEINEN OrganizationScope (tenant-audit).
 */
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements \Illuminate\Contracts\Translation\HasLocalePreference {
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasAttachments, HasFactory, HasRoles, HasSqid, Notifiable;
    use HasEffectivePermissions;
    use HasPreferences;
    use InteractsWithTwoFactor;
    use InteractsWithWorkSchedule;
    use Searchable;

    public function isAdmin(): bool {
        // Plattform-Betreiber ist in jedem Org-Kontext Admin (behält seinen
        // Policy-Bypass auch in einer per Switch aktivierten Fremd-Org).
        if ($this->isGlobalAdmin()) {
            return true;
        }

        if ($this->hasRole(UserRole::Admin->value)) {
            return true;
        }

        return LegacyBridge::isLegacyAdmin($this);
    }

    /**
     * Globaler Plattform-Betreiber (Cross-Tenant). NUR diese Kennung darf den
     * Organisations-Kontext wechseln — ein org-lokaler Admin (admin-Rolle mit
     * team_id = eigene Org) bleibt auf seine Organisation beschränkt. Das Flag
     * ist bewusst NICHT in $fillable und wird ausschließlich über Installer,
     * app:admin --platform oder Seeder gesetzt.
     */
    public function isGlobalAdmin(): bool {
        return (bool) $this->is_platform_admin;
    }

    /** @return HasMany<\App\Models\Auth\TwoFactorCredential, $this> */
    public function twoFactorCredentials(): HasMany {
        return $this->hasMany(\App\Models\Auth\TwoFactorCredential::class)->orderBy('id');
    }

    /**
     * Darf Diary-Einträge im Namen anderer Benutzer anlegen.
     */
    public function canCreateEntriesForOthers(): bool {
        return $this->isAdmin() || $this->hasRole(UserRole::Buchhaltung->value);
    }

    /**
     * Darf Buchhaltungs-/Abrechnungsdaten verwalten (z. B. Kunden, Lexoffice-Sync).
     */
    public function canManageBilling(): bool {
        return $this->isAdmin() || $this->hasRole(UserRole::Buchhaltung->value);
    }

    /**
     * Darf alle Legacy-Daten (Diary, Bereitschaft, Notdienst, Urlaub, Archiv,
     * Callcenter, Wochenkalender) lesend einsehen — unabhängig vom eigenen User.
     * Schreibrechte sind davon NICHT betroffen (siehe `isAdmin()` / Policies).
     */
    public function canViewAllLegacyData(): bool {
        return LegacyBridge::isLegacyAdmin($this) || $this->hasRole(UserRole::Buchhaltung->value);
    }

    /**
     * Darf Gleitzeit-Konten anderer Mitarbeiter einsehen (Admin + Buchhaltung).
     * Mitarbeiter ohne dieses Recht sehen ausschließlich ihre eigenen Zeiten.
     */
    public function canViewOthersFlex(): bool {
        return $this->isAdmin() || $this->hasRole(UserRole::Buchhaltung->value);
    }

    /**
     * Existiert dieser User im Legacy-System? Kein Live-DB-Check; reines
     * Flag auf Basis der bereits aufgelösten legacy_user_id.
     */
    public function existsInLegacy(): bool {
        return $this->legacy_user_id !== null;
    }

    /**
     * Ist dieser User aktiv im neuen System freigeschaltet?
     * Schatten-Accounts aus reinem Legacy-Login bleiben false und können
     * daher nicht über kompromittierte Legacy-Passwörter ins neue System.
     */
    public function existsInNewSystem(): bool {
        return (bool) $this->is_new_system;
    }

    /**
     * Darf der User den Legacy-Bereich der Anwendung nutzen?
     * Admins haben immer Zugriff.
     */
    public function canAccessLegacy(): bool {
        return $this->isAdmin() || $this->existsInLegacy();
    }

    /**
     * Darf der User den neuen Bereich der Anwendung nutzen?
     * Admins haben immer Zugriff.
     */
    public function canAccessNew(): bool {
        return $this->isAdmin() || $this->existsInNewSystem();
    }

    /** Konto zentral deaktiviert (Offboarding via Verzeichnisdienst, Feature 057). */
    public function isDeactivated(): bool {
        return $this->deactivated_at !== null;
    }

    /**
     * Darf sich dieses Konto überhaupt anmelden? Zentraler Sperr-Punkt, der in
     * beiden Auth-Providern geprüft wird — ein deaktiviertes Konto ist überall
     * gesperrt (neu, legacy, Portal).
     */
    public function canLogin(): bool {
        return ! $this->isDeactivated();
    }

    /**
     * Setzt die eigene CTI-Durchwahl (Opt-in fürs Anrufer-Pop-up, MVP-118).
     * Speichert die E164-Normalform verschlüsselt und pflegt den SHA-256-Hash
     * für den Rückwärts-Lookup. Leere/ungültige Eingabe hebt das Opt-in auf
     * (beide Felder auf null). Persistiert NICHT selbst — der Aufrufer speichert.
     */
    public function setCtiExtension(?string $raw): void {
        $e164 = $raw !== null && trim($raw) !== '' ? PhoneNumberHelper::toE164(trim($raw), 'DE') : null;

        if ($e164 === null) {
            $this->cti_extension = null;
            $this->cti_extension_hash = null;

            return;
        }

        $this->cti_extension = $e164;
        $this->cti_extension_hash = CryptoHelper::hash($e164, HashAlgorithm::SHA256);
    }

    /** Hat der Nutzer eine Durchwahl hinterlegt (→ Anrufer-Pop-up aktiv)? */
    public function hasCtiOptIn(): bool {
        return $this->cti_extension_hash !== null;
    }

    protected $fillable = [
        'organization_id',
        'customer_id',
        'name',
        'personnel_number',
        'payroll_hourly_wage',
        'tax_identification_number',
        'social_security_number',
        'date_of_birth',
        'health_insurance',
        'tax_class',
        'child_allowances',
        'church_tax',
        'employment_start_date',
        'employment_end_date',
        'employment_type',
        'compensation_model',
        'flat_amount',
        'flat_interval',
        'compensation_rate',
        'first_name',
        'middle_names',
        'last_name',
        'phone',
        'mobile',
        'fax',
        'email',
        'password',
        'legacy_user_id',
        'is_new_system',
        'deactivated_at',
        'left_at',
        'must_change_password',
        'hourly_rate',
        'internal_rate',
        'home_address',
        'home_lat',
        'home_lng',
        'preferences',
        'calendar_feed_token',
        'cti_extension',
        'cti_extension_hash',
        // Portal-Einladung (MVP-510): nur der Token-HASH, nie der Klartext.
        'portal_invite_token_hash',
        'portal_invite_expires_at',
        'portal_invited_at',
        'portal_pending_email',
        'portal_pending_email_requested_at',
        // Stellvertretung für Genehmigungen bei Abwesenheit (MVP-523).
        'deputy_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'two_factor_secret' => 'encrypted',
        'two_factor_recovery_codes' => 'encrypted:array',
        'two_factor_confirmed_at' => 'datetime',
        // Sensible Personenkennungen at-rest verschlüsselt (Spalten als text).
        'tax_identification_number' => 'encrypted',
        'social_security_number' => 'encrypted',
        'must_change_password' => 'boolean',
        'is_new_system' => 'boolean',
        'is_platform_admin' => 'boolean',
        'deactivated_at' => 'datetime',
        'left_at' => 'date',
        // Anonymisierungs-Marker (Feature 130): Konto PII-reduziert, Nachweise bleiben.
        'anonymized_at' => 'datetime',
        // Break-Glass (Feature 057): bewusst NICHT fillable — nur Admin-Aktion.
        'sso_exempt' => 'boolean',
        'payroll_hourly_wage' => MoneyCast::class . ':currency,2',
        'date_of_birth' => 'date',
        'child_allowances' => 'decimal:2',
        'church_tax' => 'boolean',
        'employment_start_date' => 'date',
        'employment_end_date' => 'date',
        'employment_type' => \App\Enums\User\EmploymentType::class,
        'compensation_model' => CompensationModel::class,
        'flat_amount' => MoneyCast::class . ':currency,2',
        'flat_interval' => \App\Enums\User\FlatInterval::class,
        'compensation_rate' => MoneyCast::class . ':currency,2',
        'hourly_rate' => MoneyCast::class . ':currency,2',
        'internal_rate' => MoneyCast::class . ':currency,2',
        'home_lat' => 'decimal:7',
        'home_lng' => 'decimal:7',
        'preferences' => 'array',
        // Eigene CTI-Durchwahl (Opt-in) at-rest verschlüsselt (Spalte als text);
        // Lookup läuft über cti_extension_hash (SHA-256 der E164-Form).
        'cti_extension' => 'encrypted',
        'portal_invite_expires_at' => 'datetime',
        'portal_invited_at' => 'datetime',
        'portal_pending_email_requested_at' => 'datetime',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }

    /**
     * EXPLIZITE, opt-in Mandanten-Einschränkung. Das User-Modell trägt aus
     * Sicherheitsgründen bewusst KEINEN globalen OrganizationScope
     * (Authenticatable-/Org-Wechsel-Sonderfall, tenant-audit-2026.md §Allow-List) —
     * deshalb muss jede org-Daten-Query (Mitglieder-/Assignee-/Report-Picker)
     * die Mandantengrenze selbst ziehen. Diese beiden Scopes sind der EINE
     * kanonische Weg dafür; rohes `User::query()` in Org-Kontexten ist per
     * Architektur-Gate `Tests\Unit\Architecture\UserOrgScopingRuleTest` verboten.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeForOrganization(Builder $query, Organization|int|null $organization): Builder {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        return $query->where($this->getTable() . '.organization_id', $organizationId);
    }

    /**
     * Beschränkt die Query auf die aktuell gebundene Organisation
     * (`app('currentOrganization')`) — spiegelt die Semantik des
     * {@see \App\Models\Scopes\OrganizationScope} für Modelle mit Trait, ohne
     * einen globalen Scope zu booten. Defensiv: ist keine Organisation gebunden
     * (Konsole/Queue/Plattform-Admin ohne Org-Kontext), bleibt die Query
     * unverändert — identisch zum No-op des globalen Scopes. Im Web-/API-Stack
     * bindet {@see \App\Http\Middleware\SetOrganizationContext} die Organisation
     * stets, sodass Picker im Org-Kontext hart gescopt sind.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeInCurrentOrganization(Builder $query): Builder {
        if (! app()->bound('currentOrganization')) {
            return $query;
        }

        $organization = app('currentOrganization');
        if ($organization instanceof Organization) {
            return $query->where($this->getTable() . '.organization_id', $organization->id);
        }

        return $query;
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /**
     * SSO-Kontoverknüpfungen (Feature 057, OIDC/SAML) — Identität je
     * Verbindung ist (iss, sub) bzw. (IdP, NameID), nie die E-Mail.
     *
     * @return HasMany<SsoIdentity, $this>
     */
    public function ssoIdentities(): HasMany {
        return $this->hasMany(SsoIdentity::class);
    }

    /**
     * Strukturierte Adressen des Mitarbeiters (polymorph, konsistent mit
     * Customer/Supplier). In der Regel genügt eine primäre Adresse.
     *
     * @return MorphMany<ContactAddress, $this>
     */
    public function addresses(): MorphMany {
        return $this->morphMany(ContactAddress::class, 'addressable');
    }

    /**
     * Bankverbindungen des Mitarbeiters (polymorph). Lokal/push-führend.
     *
     * @return MorphMany<ContactBankAccount, $this>
     */
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

    /**
     * Voller Name aus den strukturierten Bestandteilen
     * (Vorname, weitere Vornamen, Nachname). Fällt auf den Anzeigenamen
     * `name` zurück, wenn keine Bestandteile erfasst sind.
     */
    public function fullName(): string {
        $parts = array_filter(
            [$this->first_name, $this->middle_names, $this->last_name],
            static fn(?string $v): bool => $v !== null && trim($v) !== ''
        );

        return $parts === [] ? $this->name : implode(' ', $parts);
    }

    /**
     * Ist dieser Account ein Customer-Portal-User?
     * Wahr genau dann, wenn `customer_id` gesetzt ist. Diese Methode ist die
     * einzige verlaessliche Quelle fuer die Trennung Portal vs. intern; sie
     * wird sowohl vom CustomerUserProvider als Whitelist als auch vom
     * LegacyUserProvider als Blacklist verwendet.
     */
    public function isCustomer(): bool {
        return $this->customer_id !== null;
    }

    /**
     * Gruppen, in denen der User Mitglied ist. Effektive Permissions
     * werden bei der Auswertung (siehe {@see effectivePermissionNames()})
     * über alle Gruppen summiert.
     *
     * @return BelongsToMany<UserGroup, $this>
     */
    public function userGroups(): BelongsToMany {
        return $this->belongsToMany(UserGroup::class, 'user_user_group')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    /**
     * Operative Arbeits-Teams, in denen der Benutzer Mitglied ist.
     *
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany {
        return $this->belongsToMany(Team::class, 'team_user')
            ->withPivot(['is_lead', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Stellvertretung für Genehmigungen bei Abwesenheit (MVP-523).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this>
     */
    public function deputy(): \Illuminate\Database\Eloquent\Relations\BelongsTo {
        return $this->belongsTo(self::class, 'deputy_user_id');
    }

    /**
     * Teams, die dieser Benutzer leitet (als lead_user_id hinterlegt).
     *
     * @return HasMany<Team, $this>
     */
    public function ledTeams(): HasMany {
        return $this->hasMany(Team::class, 'lead_user_id');
    }

    /** @return BelongsToMany<Qualification, $this> */
    public function qualifications(): BelongsToMany {
        return $this->belongsToMany(Qualification::class, 'user_qualifications')
            ->withPivot(['valid_from', 'valid_until'])
            ->withTimestamps();
    }

    /** @return HasMany<DiaryEntry, $this> */
    public function diaryEntries(): HasMany {
        return $this->hasMany(DiaryEntry::class);
    }

    /** @return HasMany<UserBookmark, $this> */
    public function bookmarks(): HasMany {
        return $this->hasMany(UserBookmark::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<UserDashboardWidget, $this> */
    public function dashboardWidgets(): HasMany {
        return $this->hasMany(UserDashboardWidget::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<UserFilterPreset, $this> */
    public function filterPresets(): HasMany {
        return $this->hasMany(UserFilterPreset::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<OnCallShift, $this> */
    public function onCallShifts(): HasMany {
        return $this->hasMany(OnCallShift::class);
    }

    /** @return HasMany<EmergencyAssignment, $this> */
    public function emergencyAssignments(): HasMany {
        return $this->hasMany(EmergencyAssignment::class);
    }

    /** @return HasMany<Vacation, $this> */
    public function vacations(): HasMany {
        return $this->hasMany(Vacation::class);
    }

    /** @return HasMany<SickLeave, $this> */
    public function sickLeaves(): HasMany {
        return $this->hasMany(SickLeave::class);
    }

    /** @return HasMany<PushSubscription, $this> */
    public function pushSubscriptions(): HasMany {
        return $this->hasMany(PushSubscription::class);
    }

    /** @return HasMany<Timesheet, $this> */
    public function timesheets(): HasMany {
        return $this->hasMany(Timesheet::class);
    }

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany {
        return $this->hasMany(TimeEntry::class);
    }

    /** @return HasMany<FlexEligibility, $this> */
    public function flexEligibilities(): HasMany {
        return $this->hasMany(FlexEligibility::class);
    }

    public function legacyUser(): ?LegacyUser {
        return LegacyBridge::userFor($this);
    }

    /**
     * Avatar des Nutzers als polymorpher Attachment-Eintrag
     * (meta_type='avatar'). Liefert null, wenn kein Bild gesetzt ist;
     * Views müssen dann auf einen Initialen-Fallback ausweichen.
     */
    public function avatar(): ?Attachment {
        return $this->attachmentByMeta(Attachment::META_AVATAR);
    }

    /**
     * Externe(r) Mitarbeiter(in): wird pauschal oder nach Zeitaufwand vergütet
     * (nicht über die deutsche Lohnabrechnung). compensation_model = null gilt
     * als interner Payroll-Mitarbeiter (Bestandsverhalten).
     */
    public function isExternal(): bool {
        return $this->compensation_model instanceof CompensationModel
            && $this->compensation_model->isExternal();
    }

    /** @return list<string> */
    protected function searchableColumns(): array {
        return ['name', 'email'];
    }
}
