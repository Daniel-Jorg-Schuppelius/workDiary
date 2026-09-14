<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCmi5Unit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use ELearningToolkit\Cmi5\{LaunchMethod, MoveOn};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine AU eines cmi5-Kurses. `publisher_id` stammt aus der Kursstruktur,
 * `activity_id` vergibt das LMS (cmi5 9.4) und steht in der Start-URL.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_cmi5_package_id
 * @property string $publisher_id
 * @property string $activity_id
 * @property string $title
 * @property string $url
 * @property string $move_on
 * @property string|null $mastery_score
 * @property string $launch_method
 * @property string|null $launch_parameters
 * @property string|null $entitlement_key
 * @property int $position
 * @property-read LearningCmi5Package|null $package
 */
class LearningCmi5Unit extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'learning_cmi5_package_id',
        'publisher_id',
        'activity_id',
        'title',
        'url',
        'move_on',
        'mastery_score',
        'launch_method',
        'launch_parameters',
        'entitlement_key',
        'position',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'mastery_score' => 'decimal:4',
        'position' => 'integer',
    ];

    /** @return BelongsTo<LearningCmi5Package, $this> */
    public function package(): BelongsTo {
        return $this->belongsTo(LearningCmi5Package::class, 'learning_cmi5_package_id');
    }

    public function moveOn(): MoveOn {
        return MoveOn::tryFrom($this->move_on) ?? MoveOn::NotApplicable;
    }

    public function launchMethod(): LaunchMethod {
        return LaunchMethod::tryFrom($this->launch_method) ?? LaunchMethod::AnyWindow;
    }

    public function masteryScore(): ?float {
        return $this->mastery_score !== null ? (float) $this->mastery_score : null;
    }

    /** Liegt die AU extern? Sonst kommt sie aus dem entpackten Kurs. */
    public function isExternal(): bool {
        return preg_match('/^[a-z][a-z0-9+.\-]*:/iD', $this->url) === 1;
    }
}
