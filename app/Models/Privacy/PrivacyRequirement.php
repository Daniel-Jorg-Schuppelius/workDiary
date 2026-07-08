<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyRequirement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Privacy;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/**
 * Konfigurierbarer Anforderungskatalog des Datenschutz-Compliance-Checks
 * (Nachtrag 043c): welche Prüfungen laufen, mit welchem Label/welcher
 * Kategorie. `check_type` verweist auf eine Prüf-Implementierung im
 * {@see \App\Services\Privacy\ComplianceAnalysisService}; deaktivierte
 * Einträge werden übersprungen. Branchenprofile liefern Vorlagen
 * (source=profile), der config-Default wird beim ersten Lauf materialisiert
 * (source=default).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $requirement_key
 * @property string $label
 * @property string|null $category
 * @property string $check_type
 * @property bool $active
 * @property array<string, mixed>|null $params
 * @property string $source
 */
class PrivacyRequirement extends Model {
    use BelongsToOrganization;

    protected $table = 'privacy_requirements';

    protected $fillable = [
        'organization_id',
        'requirement_key',
        'label',
        'category',
        'check_type',
        'active',
        'params',
        'source',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'active' => 'boolean',
        'params' => 'array',
    ];
}
