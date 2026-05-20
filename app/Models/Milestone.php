<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Milestone.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\MilestoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $project_id
 * @property int|null $created_by
 * @property string $title
 * @property string|null $description
 * @property Carbon|null $due_date
 * @property bool $is_completed
 * @property int|null $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Milestone extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<MilestoneFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'project_id',
        'created_by',
        'title',
        'description',
        'due_date',
        'is_completed',
        'position',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'due_date' => 'date',
        'is_completed' => 'boolean',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany {
        return $this->hasMany(Task::class);
    }

    public function statusLabel(): string {
        return $this->is_completed ? __('Erledigt') : __('Offen');
    }

    public function statusTone(): string {
        return $this->is_completed ? 'success' : 'neutral';
    }
}
