<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningXapiDocument.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;

/**
 * State- und Profil-Dokument des kleinen LRS (xAPI 1.0.3, Document APIs).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $kind
 * @property string|null $activity_id
 * @property string $agent_hash
 * @property string|null $registration
 * @property string $document_id
 * @property string $lookup_hash
 * @property string $content
 * @property string $content_type
 * @property string $etag
 */
class LearningXapiDocument extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    public const KIND_STATE = 'state';

    public const KIND_AGENT_PROFILE = 'agent_profile';

    protected $fillable = [
        'organization_id',
        'kind',
        'activity_id',
        'agent_hash',
        'registration',
        'document_id',
        'lookup_hash',
        'content',
        'content_type',
        'etag',
    ];
}
