<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Survey.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Survey;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fragebogen (Feature 090): wiederverwendbar, org-gescopt, ohne
 * Marketing-Automation.
 *
 * `anonymous` ist eine Speicher-Eigenschaft: Antworten anonymer Umfragen
 * tragen keinen Einladungsbezug — nachträglich umschaltbar wäre das eine
 * Lüge gegenüber den bisherigen Teilnehmern, deshalb friert die erste
 * Einladung die Einstellung ein (Service-Wächter).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $title
 * @property string|null $purpose
 * @property bool $active
 * @property bool $anonymous
 * @property bool $trigger_on_ticket_close
 * @property int|null $created_by
 */
class Survey extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<\Database\Factories\Survey\SurveyFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'title', 'purpose', 'active', 'anonymous',
        'trigger_on_ticket_close', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'active' => 'boolean',
        'anonymous' => 'boolean',
        'trigger_on_ticket_close' => 'boolean',
    ];

    /** @return HasMany<SurveyQuestion, $this> */
    public function questions(): HasMany {
        return $this->hasMany(SurveyQuestion::class)->orderBy('position');
    }

    /** @return HasMany<SurveyInvitation, $this> */
    public function invitations(): HasMany {
        return $this->hasMany(SurveyInvitation::class);
    }

    /** @return HasMany<SurveyResponse, $this> */
    public function responses(): HasMany {
        return $this->hasMany(SurveyResponse::class);
    }
}
