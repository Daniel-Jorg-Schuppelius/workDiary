<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisDeadlineTemplate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Crisis;

use App\Models\Concerns\HasSqid;
use Illuminate\Database\Eloquent\Model;

/**
 * Fristen-Template (Feature 070, Flexibilitätsplan D9): Katalogdaten je
 * Krisen-Kategorie — org NULL = globaler Default (Seeder), Org-Zeilen
 * überschreiben. Eine Gesetzesänderung ist Datenpflege, kein Release.
 *
 * ACHTUNG: bewusst OHNE BelongsToOrganization-Scope — globale Defaults
 * (organization_id NULL) müssen für alle Mandanten lesbar bleiben; die
 * Auflösung filtert explizit (CrisisDeadlineService).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $category
 * @property string $label
 * @property int|null $offset_hours
 * @property string|null $source
 * @property bool $active
 */
class CrisisDeadlineTemplate extends Model {
    use HasSqid;

    protected $fillable = ['organization_id', 'category', 'label', 'offset_hours', 'source', 'active'];

    /** @var array<string, string> */
    protected $casts = ['offset_hours' => 'integer', 'active' => 'boolean'];
}
