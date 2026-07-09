<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OperationsTask.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\Operations\{OperationsTaskSeverity, OperationsTaskStatus, OperationsTaskType};
use App\Models\Concerns\{Auditable, HasSqid};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Betriebsaufgabe im Admin-Aufgabencenter (Feature 041, MVP-058).
 * Wird ausschließlich über den OperationsAlertService erzeugt/aufgelöst
 * (idempotent via dedupe_key); Statuswechsel laufen über den Controller
 * und sind auditiert. BEWUSST ohne BelongsToOrganization-Global-Scope:
 * installationsweite Aufgaben (is_system) hängen an der Betreiber-Org,
 * Sichtbarkeit regelt der Controller.
 *
 * @property int $id
 * @property int $organization_id
 * @property bool $is_system
 * @property OperationsTaskType $type
 * @property OperationsTaskSeverity $severity
 * @property OperationsTaskStatus $status
 * @property string $dedupe_key
 * @property string $title_key
 * @property array<string, mixed>|null $params
 * @property string|null $link_route
 * @property array<string, mixed>|null $link_params
 * @property string|null $assigned_role
 * @property int|null $assigned_user_id
 * @property \Carbon\CarbonImmutable|null $snoozed_until
 * @property \Carbon\CarbonImmutable $first_seen_at
 * @property \Carbon\CarbonImmutable $last_seen_at
 * @property \Carbon\CarbonImmutable|null $resolved_at
 * @property int|null $acted_by_user_id
 * @property \Carbon\CarbonImmutable|null $acted_at
 * @property string|null $note
 */
class OperationsTask extends Model {
    use Auditable;
    use HasSqid;

    protected $table = 'operations_tasks';

    protected $fillable = [
        'organization_id',
        'is_system',
        'type',
        'severity',
        'status',
        'dedupe_key',
        'title_key',
        'params',
        'link_route',
        'link_params',
        'assigned_role',
        'assigned_user_id',
        'snoozed_until',
        'first_seen_at',
        'last_seen_at',
        'resolved_at',
        'acted_by_user_id',
        'acted_at',
        'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_system' => 'boolean',
        'type' => OperationsTaskType::class,
        'severity' => OperationsTaskSeverity::class,
        'status' => OperationsTaskStatus::class,
        'params' => 'array',
        'link_params' => 'array',
        'snoozed_until' => 'immutable_datetime',
        'first_seen_at' => 'immutable_datetime',
        'last_seen_at' => 'immutable_datetime',
        'resolved_at' => 'immutable_datetime',
        'acted_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void {
        $query->whereIn('status', [
            OperationsTaskStatus::Open->value,
            OperationsTaskStatus::Snoozed->value,
            OperationsTaskStatus::Delegated->value,
        ]);
    }

    public function title(): string {
        return (string) __($this->title_key, (array) ($this->params ?? []));
    }

    public function url(): ?string {
        if ($this->link_route === null || !\Illuminate\Support\Facades\Route::has($this->link_route)) {
            return null;
        }

        return route($this->link_route, (array) ($this->link_params ?? []));
    }
}
