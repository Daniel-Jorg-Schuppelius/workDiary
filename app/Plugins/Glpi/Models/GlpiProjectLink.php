<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GlpiProjectLink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Glpi\Models;

use App\Enums\Integration\TaskImportLinkStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Plugins\Support\TaskSync\{IsTaskImportLink, TaskImportLink};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Zuordnung einer GLPI-Quelle (Entität, stabile ID) auf ein WorkDiary-Projekt
 * (Feature 174): erst nach Vorschau und Bestätigung wird importiert.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $source_project_id
 * @property string|null $source_project_key
 * @property string|null $source_project_name
 * @property int $project_id
 * @property TaskImportLinkStatus $status
 * @property string|null $paused_reason
 * @property CarbonImmutable|null $import_confirmed_at
 * @property int|null $confirmed_by
 * @property CarbonImmutable|null $checkpoint_at
 * @property CarbonImmutable|null $last_full_sync_at
 * @property CarbonImmutable|null $last_run_at
 * @property bool|null $is_last_run_complete
 * @property array<string, int>|null $last_run_counters
 * @property string|null $last_error
 * @property int|null $created_by
 */
class GlpiProjectLink extends Model implements TaskImportLink {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;
    use IsTaskImportLink;

    protected $table = 'glpi_project_links';

    protected $fillable = ['organization_id', 'source_project_id', 'source_project_key', 'source_project_name', 'project_id', 'status', 'created_by'];
}
