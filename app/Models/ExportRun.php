<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExportRun.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Export\{ExportEntity, ExportFormat, ExportRunState};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Datentransfer — Export-Lauf.
 *
 * Spiegelbild zu {@see ImportRun}: persistiert jeden erzeugten Export
 * (Entität, Format, Filter, Zeilenzahl, Datei) für Verlauf, Audit und
 * erneuten Download.
 *
 * @property int $id
 * @property int $organization_id
 * @property ExportEntity $entity
 * @property ExportFormat $format
 * @property ExportRunState $state
 * @property array<string, mixed>|null $filters
 * @property string $output_filename
 * @property string $storage_path
 * @property int $rows_total
 * @property string|null $error_message
 * @property int|null $created_by_user_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class ExportRun extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'entity',
        'format',
        'state',
        'filters',
        'output_filename',
        'storage_path',
        'rows_total',
        'error_message',
        'created_by_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'entity' => ExportEntity::class,
        'format' => ExportFormat::class,
        'state' => ExportRunState::class,
        'filters' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
