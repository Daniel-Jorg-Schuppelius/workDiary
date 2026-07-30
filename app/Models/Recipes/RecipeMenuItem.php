<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecipeMenuItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Recipes;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\ProcedureTemplate;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gericht in einem Menü (MVP-455): verweist auf das Rezept-Template — die
 * Aggregation löst zur Laufzeit die aktuell veröffentlichte Version auf,
 * Rezeptpositionen werden NIE dupliziert.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $recipe_menu_id
 * @property int $procedure_template_id
 * @property numeric-string $portions_per_guest
 * @property int $sort_order
 */
class RecipeMenuItem extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'recipe_menu_id',
        'procedure_template_id',
        'portions_per_guest',
        'sort_order',
    ];

    protected $casts = [
        'portions_per_guest' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    /** @return BelongsTo<RecipeMenu, $this> */
    public function menu(): BelongsTo {
        return $this->belongsTo(RecipeMenu::class, 'recipe_menu_id');
    }

    /** @return BelongsTo<ProcedureTemplate, $this> */
    public function template(): BelongsTo {
        return $this->belongsTo(ProcedureTemplate::class, 'procedure_template_id');
    }
}
