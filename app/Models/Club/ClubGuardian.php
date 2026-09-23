<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubGuardian.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\ClubGuardianPermission;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Vertretung eines Mitglieds (Sorgeberechtigte, MVP-842): explizite
 * Zuordnung mit erlaubten Handlungen, Gültigkeit und Widerruf. Ein Konto
 * kann mehrere Kinder betreuen; gemeinsame Adresse oder E-Mail allein
 * gewähren keine Vertretung. Widerruf löscht nicht — der Nachweis bleibt.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_member_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property int|null $user_id
 * @property list<string>|null $permissions
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_to
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by_user_id
 * @property string|null $note
 */
class ClubGuardian extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'club_member_id',
        'name',
        'email',
        'phone',
        'user_id',
        'permissions',
        'valid_from',
        'valid_to',
        'revoked_at',
        'revoked_by_user_id',
        'note',
    ];

    protected $casts = [
        'permissions' => 'array',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'revoked_at' => 'datetime',
    ];

    /** @return BelongsTo<ClubMember, $this> */
    public function member(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function revokedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    public function isRevoked(): bool {
        return $this->revoked_at !== null;
    }

    /** Nicht widerrufen und am Stichtag innerhalb der Gültigkeit. */
    public function isActiveOn(CarbonInterface $date): bool {
        $day = $date->copy()->startOfDay();

        return ! $this->isRevoked()
            && ($this->valid_from === null || $this->valid_from->lessThanOrEqualTo($day))
            && ($this->valid_to === null || $this->valid_to->greaterThanOrEqualTo($day));
    }

    public function allows(ClubGuardianPermission $permission): bool {
        return in_array($permission->value, $this->permissions ?? [], true);
    }

    /** @return list<ClubGuardianPermission> */
    public function permissionEnums(): array {
        $out = [];
        foreach ($this->permissions ?? [] as $value) {
            $enum = ClubGuardianPermission::tryFrom((string) $value);
            if ($enum !== null) {
                $out[] = $enum;
            }
        }

        return $out;
    }
}
