<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurveyResponseSubmitted.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Survey;

use App\Models\Survey\SurveyInvitation;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Über diese Einladung wurde ein Fragebogen abgesendet (MVP-1600). Trägt nur
 * die Einladung, nie die Antwort — bei anonymen Umfragen gibt es keinen Weg
 * von der einen zur anderen, und das bleibt so.
 */
final class SurveyResponseSubmitted {
    use Dispatchable;

    public function __construct(public readonly SurveyInvitation $invitation) {}
}
