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
use App\Support\Locales;
use Illuminate\Database\Eloquent\Model;

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

    /**
     * Sprachen des öffentlichen Portals, Standardsprache zuerst. Ohne Auswahl
     * gibt es nur die Standardsprache und damit keine Umschaltung.
     *
     * @return list<string>
     */
    public function offeredLocales(): array {
        $default = Locales::isSupported((string) $this->default_locale) ? (string) $this->default_locale : (string) config('app.locale', 'de');
        $allowed = array_intersect(Locales::enabledCodes(), (array) ($this->allowed_locales ?? []));

        return array_values(array_unique([$default, ...$allowed]));
    }
}
