<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecipeMenu.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Recipes;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Menü-/Buffetzusammenstellung (MVP-455): bündelt Gerichte
 * (ProcedureTemplates mit Rezeptprofil); die Gästezahl skaliert den
 * aggregierten Materialbedarf über die veröffentlichten Rezeptstände.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property Carbon|null $event_date
 * @property int|null $guest_count
 * @property string|null $notes
 * @property int|null $created_by
 */
class RecipeMenu extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'name',
        'event_date',
        'guest_count',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'event_date' => 'date',
        'guest_count' => 'integer',
    ];

    /** @return HasMany<RecipeMenuItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(RecipeMenuItem::class, 'recipe_menu_id')->orderBy('sort_order');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }
}
