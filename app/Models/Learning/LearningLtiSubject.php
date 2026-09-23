<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningLtiSubject.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Models\Communication\ExternalParticipant;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Person, die eine fremde Plattform per LTI startet (Feature 149): `sub`
 * der Plattform als Abdruck, zugeordnet zu einem externen Teilnehmer.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_lti_platform_id
 * @property string $subject_hash
 * @property int|null $external_participant_id
 */
class LearningLtiSubject extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'learning_lti_platform_id',
        'subject_hash',
        'external_participant_id',
    ];

    /** @return BelongsTo<LearningLtiPlatform, $this> */
    public function platform(): BelongsTo {
        return $this->belongsTo(LearningLtiPlatform::class, 'learning_lti_platform_id');
    }

    /** @return BelongsTo<ExternalParticipant, $this> */
    public function externalParticipant(): BelongsTo {
        return $this->belongsTo(ExternalParticipant::class, 'external_participant_id');
    }
}
