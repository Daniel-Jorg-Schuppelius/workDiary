<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EventCategory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\EventCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $slug
 * @property string|null $color
 * @property string|null $description
 * @property bool $requires_certificate
 * @property int|null $certificate_valid_months
 * @property array<int, int>|null $reminder_offsets  Minuten vor Event-Start
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EventCategory extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<EventCategoryFactory> */
    use HasFactory;

    protected $table = 'event_categories';

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'color',
        'description',
        'requires_certificate',
        'certificate_valid_months',
        'reminder_offsets',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'requires_certificate' => 'boolean',
        'is_active' => 'boolean',
        'reminder_offsets' => 'array',
    ];

    /** @return HasMany<Event, $this> */
    public function events(): HasMany {
        return $this->hasMany(Event::class, 'category_id');
    }

    /** @param Builder<EventCategory> $query */
    public function scopeActive(Builder $query): void {
        $query->where('is_active', true);
    }
}
