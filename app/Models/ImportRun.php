<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportRun.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Import\{ImportEntity, ImportRunState};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * MVP-049 — CSV-Import-Lauf.
 *
 * @property int $id
 * @property int $organization_id
 * @property ImportEntity $entity
 * @property ImportRunState $state
 * @property string $input_filename
 * @property string $input_hash
 * @property string $storage_path
 * @property string $delimiter
 * @property string $encoding
 * @property int $rows_total
 * @property int $rows_created
 * @property int $rows_updated
 * @property int $rows_skipped
 * @property int $rows_failed
 * @property array<int, array<string, mixed>>|null $preview
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property int|null $created_by_user_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ImportRunError> $errors
 */
class ImportRun extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'entity',
        'state',
        'input_filename',
        'input_hash',
        'storage_path',
        'delimiter',
        'encoding',
        'rows_total',
        'rows_created',
        'rows_updated',
        'rows_skipped',
        'rows_failed',
        'preview',
        'started_at',
        'finished_at',
        'created_by_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'entity' => ImportEntity::class,
        'state' => ImportRunState::class,
        'preview' => 'array',
        'started_at' => 'immutable_datetime',
        'finished_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<ImportRunError, $this> */
    public function errors(): HasMany {
        return $this->hasMany(ImportRunError::class);
    }
}
