<?php

namespace App\Models;

use App\Models\Legacy\LegacyUser;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable {
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasApiTokens, HasRoles, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_USER = 'user';
    public const ROLE_CALLCENTER = 'callcenter';
    public const ROLE_BUCHHALTUNG = 'buchhaltung';

    public function isAdmin(): bool {
        if ($this->hasRole(self::ROLE_ADMIN)) {
            return true;
        }

        return \App\Support\LegacyRoleResolver::isAdmin($this);
    }

    /**
     * Darf Diary-Einträge im Namen anderer Benutzer anlegen.
     */
    public function canCreateEntriesForOthers(): bool {
        return $this->isAdmin() || $this->hasRole(self::ROLE_BUCHHALTUNG);
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'legacy_user_id',
        'must_change_password',
    ];

    protected function casts(): array {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function diaryEntries(): HasMany {
        return $this->hasMany(DiaryEntry::class);
    }

    public function onCallShifts(): HasMany {
        return $this->hasMany(OnCallShift::class);
    }

    public function emergencyAssignments(): HasMany {
        return $this->hasMany(EmergencyAssignment::class);
    }

    public function pushSubscriptions(): HasMany {
        return $this->hasMany(PushSubscription::class);
    }

    public function legacyUser(): ?LegacyUser {
        if (! $this->legacy_user_id) {
            return null;
        }

        return LegacyUser::find($this->legacy_user_id);
    }
}
