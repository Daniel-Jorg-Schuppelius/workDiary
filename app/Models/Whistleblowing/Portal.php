<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Portal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Whistleblowing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Organisationsbezogene Portal-Konfiguration. `public_slug` ist bewusst NICHT
 * der organizations.slug – das Portal kann so unabhaengig rotiert/deaktiviert
 * werden (Abschnitt 9.1).
 */
class Portal extends Model {
    use BelongsToOrganization;

    protected $table = 'whistleblowing_portals';

    protected $fillable = [
        'organization_id',
        'public_slug',
        'is_enabled',
        'allow_anonymous',
        'allow_confidential',
        'allowed_locales',
        'default_locale',
        'intro_text',
        'privacy_text_version',
        'external_channels',
        'retention_months',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_enabled' => 'boolean',
        'allow_anonymous' => 'boolean',
        'allow_confidential' => 'boolean',
        'allowed_locales' => 'array',
        'external_channels' => 'array',
        'retention_months' => 'integer',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }
}
