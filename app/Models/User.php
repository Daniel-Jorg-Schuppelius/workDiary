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

use App\Legacy\Models\LegacyUser;
use App\Legacy\Support\LegacyRoleResolver;
use App\Models\Concerns\HasAttachments;
use App\Services\Sickness\ContinuedPaymentService;
use App\Support\Sickness\ContinuedPaymentStatus;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property int|null $legacy_user_id
 * @property bool $must_change_password
 * @property array<string, mixed>|null $preferences
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasAttachments, HasFactory, HasRoles, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_USER = 'user';

    public const ROLE_CALLCENTER = 'callcenter';

    public const ROLE_BUCHHALTUNG = 'buchhaltung';

    public function isAdmin(): bool
    {
        if ($this->hasRole(self::ROLE_ADMIN)) {
            return true;
        }

        return LegacyRoleResolver::isAdmin($this);
    }

    /**
     * Darf Diary-Einträge im Namen anderer Benutzer anlegen.
     */
    public function canCreateEntriesForOthers(): bool
    {
        return $this->isAdmin() || $this->hasRole(self::ROLE_BUCHHALTUNG);
    }

    /**
     * Darf Buchhaltungs-/Abrechnungsdaten verwalten (z. B. Kunden, Lexoffice-Sync).
     */
    public function canManageBilling(): bool
    {
        return $this->isAdmin() || $this->hasRole(self::ROLE_BUCHHALTUNG);
    }

    protected $fillable = [
        'organization_id',
        'name',
        'email',
        'password',
        'legacy_user_id',
        'must_change_password',
        'hourly_rate',
        'internal_rate',
        'home_address',
        'home_lat',
        'home_lng',
        'preferences',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'must_change_password' => 'boolean',
        'hourly_rate' => 'decimal:2',
        'internal_rate' => 'decimal:2',
        'home_lat' => 'decimal:7',
        'home_lng' => 'decimal:7',
        'preferences' => 'array',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsToMany<Qualification, $this> */
    public function qualifications(): BelongsToMany
    {
        return $this->belongsToMany(Qualification::class, 'user_qualifications')
            ->withPivot(['valid_from', 'valid_until'])
            ->withTimestamps();
    }

    /** @return HasMany<DiaryEntry, $this> */
    public function diaryEntries(): HasMany
    {
        return $this->hasMany(DiaryEntry::class);
    }

    /** @return HasMany<OnCallShift, $this> */
    public function onCallShifts(): HasMany
    {
        return $this->hasMany(OnCallShift::class);
    }

    /** @return HasMany<EmergencyAssignment, $this> */
    public function emergencyAssignments(): HasMany
    {
        return $this->hasMany(EmergencyAssignment::class);
    }

    /** @return HasMany<Vacation, $this> */
    public function vacations(): HasMany
    {
        return $this->hasMany(Vacation::class);
    }

    /** @return HasMany<SickLeave, $this> */
    public function sickLeaves(): HasMany
    {
        return $this->hasMany(SickLeave::class);
    }

    public function currentSicknessStatus(?CarbonInterface $on = null): ContinuedPaymentStatus
    {
        /** @var ContinuedPaymentService $svc */
        $svc = app(ContinuedPaymentService::class);

        return $svc->statusFor($this, $on);
    }

    /** @return HasMany<PushSubscription, $this> */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /** @return HasMany<Timesheet, $this> */
    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class);
    }

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function workSchedule(?CarbonInterface $on = null): ?WorkSchedule
    {
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

    public function legacyUser(): ?LegacyUser
    {
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
    public function avatar(): ?Attachment
    {
        return $this->attachmentByMeta(Attachment::META_AVATAR);
    }

    /**
     * Persönliche Präferenzen gemerged mit den Defaults aus
     * config/personalization.php. Liefert immer ein vollständig
     * gefülltes Array; leere Felder bedeuten "Default verwenden".
     *
     * @return array<string, mixed>
     */
    public function preferences(): array
    {
        /** @var array<string, mixed> $defaults */
        $defaults = (array) config('personalization.defaults', []);
        /** @var array<string, mixed> $stored */
        $stored = (array) ($this->preferences ?? []);

        return array_replace($defaults, $stored);
    }
}
