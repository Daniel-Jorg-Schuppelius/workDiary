<?php

namespace App\Models;

use App\Models\Legacy\LegacyUser;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable {
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'legacy_user_id',
    ];

    protected function casts(): array {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function diaryEntries(): HasMany {
        return $this->hasMany(DiaryEntry::class);
    }

    public function legacyUser(): ?LegacyUser {
        if (! $this->legacy_user_id) {
            return null;
        }

        return LegacyUser::find($this->legacy_user_id);
    }
}
