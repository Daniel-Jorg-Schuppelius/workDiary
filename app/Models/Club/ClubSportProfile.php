<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubSportProfile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\{ClubResultFormat, ClubSportFamily};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sportartenprofil (Feature 159, MVP-852): Sportart als Konfiguration —
 * Familie, Positionen, Kadergrößen, Ergebnisformat, Einzel/Doppel,
 * Stichtag der Altersklasse, Disziplinen (855) und Ressourcentypen (853).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property ClubSportFamily $family
 * @property list<array{code: string, label: string}>|null $positions
 * @property int|null $squad_size_field
 * @property int|null $squad_size_bench
 * @property ClubResultFormat $result_format
 * @property bool $has_doubles
 * @property string|null $age_cutoff
 * @property list<array{code: string, label: string, unit?: string|null, lower_is_better?: bool}>|null $disciplines
 * @property list<string>|null $resource_types
 * @property string|null $notes
 * @property bool $is_active
 */
class ClubSportProfile extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'name', 'family', 'positions', 'squad_size_field', 'squad_size_bench', 'result_format',
        'has_doubles', 'age_cutoff', 'disciplines', 'resource_types', 'notes', 'is_active',
    ];

    protected $casts = [
        'family' => ClubSportFamily::class,
        'positions' => 'array',
        'squad_size_field' => 'integer',
        'squad_size_bench' => 'integer',
        'result_format' => ClubResultFormat::class,
        'has_doubles' => 'boolean',
        'disciplines' => 'array',
        'resource_types' => 'array',
        'is_active' => 'boolean',
    ];

    /** @return HasMany<ClubGroup, $this> */
    public function groups(): HasMany {
        return $this->hasMany(ClubGroup::class, 'club_sport_profile_id');
    }

    /** @return HasMany<ClubDepartment, $this> */
    public function departments(): HasMany {
        return $this->hasMany(ClubDepartment::class, 'club_sport_profile_id');
    }

    /** @return list<string> */
    public function positionCodes(): array {
        return array_map(static fn(array $position): string => (string) $position['code'], $this->positions ?? []);
    }

    public function positionLabel(?string $code): ?string {
        if ($code === null) {
            return null;
        }
        foreach ($this->positions ?? [] as $position) {
            if ((string) $position['code'] === $code) {
                return (string) $position['label'];
            }
        }

        return $code;
    }

    public function hasPairings(): bool {
        return $this->family === ClubSportFamily::Racket;
    }
}
