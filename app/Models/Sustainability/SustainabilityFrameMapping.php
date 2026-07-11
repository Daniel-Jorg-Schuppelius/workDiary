<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityFrameMapping.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Sustainability;

use App\Models\Concerns\HasSqid;
use Illuminate\Database\Eloquent\Model;

/**
 * Referenzmatrix (Feature 071, MVP-234 / P3+D8): Norm-/Rahmenbezüge als
 * DATEN mit frame + frame_version — erste gepflegte Version vsme-1.0;
 * esrs-2.0/iso14001-2026 folgen nach den Watchlist-Checks (W4/W6).
 * KEINE Konformitätszusage — dokumentiert Zuordnung + Datenstand.
 *
 * ACHTUNG: bewusst OHNE BelongsToOrganization-Scope — die globale Matrix
 * (organization_id NULL) gilt für alle Mandanten.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $frame
 * @property string $frame_version
 * @property string $section_code
 * @property string $section_label
 * @property string|null $mapping_note
 * @property bool $active
 */
class SustainabilityFrameMapping extends Model {
    use HasSqid;

    protected $fillable = ['organization_id', 'frame', 'frame_version', 'section_code', 'section_label', 'mapping_note', 'active'];

    /** @var array<string, string> */
    protected $casts = ['active' => 'boolean'];
}
