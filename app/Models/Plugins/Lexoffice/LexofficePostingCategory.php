<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficePostingCategory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Plugins\Lexoffice;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/**
 * Buchungskategorie aus Lexoffice (MVP-905), Spiegel von `/posting-categories`
 * für die Ausgaben je Kategorie.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $external_id
 * @property string $name
 * @property string $kind income|outgo
 * @property string|null $group_name
 */
class LexofficePostingCategory extends Model {
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'external_id', 'name', 'kind', 'group_name'];
}
