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

use App\Enums\User\UserRole;
use App\Legacy\Models\LegacyUser;
use App\Legacy\Support\LegacyRoleResolver;
use App\Models\Concerns\{HasAttachments, HasSqid};
use App\Services\Sickness\ContinuedPaymentService;
use App\Support\Sickness\ContinuedPaymentStatus;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphMany};
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\{Carbon, Collection};
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property string $name
 * @property string|null $personnel_number
 * @property string|null $payroll_hourly_wage
 * @property string|null $tax_identification_number
 * @property string|null $social_security_number
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
 * @property bool $must_change_password
 * @property string|null $hourly_rate
 * @property string|null $internal_rate
 * @property string|null $home_address
 * @property string|null $home_lat
 * @property string|null $home_lng
 * @property array<string, mixed>|null $preferences
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 */
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable {
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasAttachments, HasFactory, HasRoles, HasSqid, Notifiable;

    public function isAdmin(): bool {
        if ($this->hasRole(UserRole::Admin->value)) {
            return true;
        }

        return LegacyRoleResolver::isAdmin($this);
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
        return LegacyRoleResolver::isAdmin($this) || $this->hasRole(UserRole::Buchhaltung->value);
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
        'must_change_password',
        'hourly_rate',
        'internal_rate',
        'home_address',
        'home_lat',
        'home_lng',
        'preferences',
        'calendar_feed_token',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'must_change_password' => 'boolean',
        'is_new_system' => 'boolean',
        'payroll_hourly_wage' => 'decimal:2',
        'date_of_birth' => 'date',
        'child_allowances' => 'decimal:2',
        'church_tax' => 'boolean',
        'employment_start_date' => 'date',
        'employment_end_date' => 'date',
        'employment_type' => \App\Enums\User\EmploymentType::class,
        'hourly_rate' => 'decimal:2',
        'internal_rate' => 'decimal:2',
        'home_lat' => 'decimal:7',
        'home_lng' => 'decimal:7',
        'preferences' => 'array',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
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
        $parts = array_filter([$this->first_name, $this->middle_names, $this->last_name],
            static fn(?string $v): bool => $v !== null && trim($v) !== '');

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
     * Teams, die dieser Benutzer leitet (als lead_user_id hinterlegt).
     *
     * @return HasMany<Team, $this>
     */
    public function ledTeams(): HasMany {
        return $this->hasMany(Team::class, 'lead_user_id');
    }

    /**
     * Alle Permission-Namen, die der User effektiv besitzt:
     *   - direkte Permissions am User,
     *   - Permissions via eigene Rollen,
     *   - Permissions via Gruppen-Mitgliedschaften (eigene Permissions der
     *     Gruppe und Permissions der Rollen, die der Gruppe zugewiesen sind).
     *
     * Wird sowohl von Policies (über {@see hasEffectivePermission()}) als
     * auch von der Admin-UI für die Anzeige verwendet.
     *
     * @return Collection<int, string>
     */
    public function effectivePermissionNames(): Collection {
        /** @var Collection<int, SpatiePermission> $direct */
        $direct = $this->getAllPermissions();
        $names = $direct->pluck('name');

        $this->loadMissing(['userGroups.permissions', 'userGroups.roles.permissions']);

        foreach ($this->userGroups as $group) {
            /** @var Collection<int, SpatiePermission> $groupPermissions */
            $groupPermissions = $group->getAllPermissions();
            foreach ($groupPermissions as $permission) {
                $names->push($permission->name);
            }
        }

        return $names->unique()->values();
    }

    /**
     * Schnelle Prüfung, ob der User die übergebene Permission effektiv
     * besitzt — wird vom Gate::before-Hook in AuthServiceProvider
     * aufgerufen, damit `$user->can('xy')` auch Gruppen-Permissions
     * berücksichtigt.
     */
    public function hasEffectivePermission(string $permission): bool {
        try {
            if ($this->hasPermissionTo($permission)) {
                return true;
            }
        } catch (PermissionDoesNotExist) {
            return false;
        }

        $this->loadMissing(['userGroups.permissions', 'userGroups.roles.permissions']);

        foreach ($this->userGroups as $group) {
            try {
                if ($group->hasPermissionTo($permission)) {
                    return true;
                }
            } catch (PermissionDoesNotExist) {
                return false;
            }
        }

        return false;
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

    public function currentSicknessStatus(?CarbonInterface $on = null): ContinuedPaymentStatus {
        /** @var ContinuedPaymentService $svc */
        $svc = app(ContinuedPaymentService::class);

        return $svc->statusFor($this, $on);
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

    public function workSchedule(?CarbonInterface $on = null): ?WorkSchedule {
        $on = $on ? $on->copy()->startOfDay() : now()->startOfDay();

        return WorkSchedule::query()
            ->where('user_id', $this->id)
            ->where('valid_from', '<=', $on)
            ->where(function ($q) use ($on) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $on);
            })
            ->orderByDesc('valid_from')
            ->first();
    }

    /** @return HasMany<FlexEligibility, $this> */
    public function flexEligibilities(): HasMany {
        return $this->hasMany(FlexEligibility::class);
    }

    /**
     * Ist der User am angegebenen Stichtag (Default: heute) für die
     * Gleitzeit-Erfassung freigeschaltet? Stützt sich auf
     * {@see FlexEligibility}: jede Lücke zwischen Perioden bedeutet
     * explizit "nicht berechtigt", auch wenn vor- oder nachher Perioden
     * existieren.
     */
    public function isFlexEligible(?CarbonInterface $on = null): bool {
        $on = $on ? $on->copy()->startOfDay() : now()->startOfDay();

        return FlexEligibility::query()
            ->where('user_id', $this->id)
            ->where('valid_from', '<=', $on)
            ->where(function ($q) use ($on): void {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $on);
            })
            ->exists();
    }

    public function legacyUser(): ?LegacyUser {
        if (! $this->legacy_user_id) {
            return null;
        }

        return LegacyUser::find($this->legacy_user_id);
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
     * Persönliche Präferenzen gemerged mit den Defaults aus
     * config/personalization.php. Liefert immer ein vollständig
     * gefülltes Array; leere Felder bedeuten "Default verwenden".
     *
     * @return array<string, mixed>
     */
    public function preferences(): array {
        /** @var array<string, mixed> $defaults */
        $defaults = (array) config('personalization.defaults', []);
        /** @var array<string, mixed> $stored */
        $stored = (array) ($this->preferences ?? []);

        return array_replace($defaults, $stored);
    }
}
