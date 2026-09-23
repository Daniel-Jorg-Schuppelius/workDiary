<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserWorkspace.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eigener Arbeitsbereich einer Person (Feature 082 Phase 2, MVP-731).
 *
 * Eine persönliche Zusammenstellung von Menüpunkten — rein kosmetisch, wie
 * die vordefinierten Fokus-Ansichten (D13): sie blendet aus, sie schaltet
 * nichts frei. Was in `items` stehen darf, entscheidet beim Speichern der
 * Server anhand dessen, was die Person laut NavGate ohnehin sehen darf.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property string $name
 * @property string|null $icon
 * @property int $sort
 * @property array<array-key, mixed>|null $items
 */
class UserWorkspace extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'user_id',
        'name',
        'icon',
        'sort',
        'items',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sort' => 'integer',
        'items' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /**
     * Arbeitsbereiche einer Person in Anzeigereihenfolge.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function forUser(Builder $query, User|int $user): void {
        $query->where('user_id', $user instanceof User ? $user->getKey() : $user)
            ->orderBy('sort')
            ->orderBy('name');
    }

    /**
     * Navigations-Schlüssel als Liste — auch wenn in der Spalte einmal etwas
     * anderes als eine Liste landen sollte (JSON ist nachgiebig, wir nicht).
     *
     * @return list<string>
     */
    public function keys(): array {
        $items = is_array($this->items) ? $this->items : [];

        return array_values(array_filter(
            array_map(static fn ($value): string => is_scalar($value) ? (string) $value : '', $items),
            static fn (string $value): bool => $value !== '',
        ));
    }
}
