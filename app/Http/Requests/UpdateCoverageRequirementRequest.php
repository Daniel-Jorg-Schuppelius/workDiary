<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UpdateCoverageRequirementRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

/**
 * Bearbeiten einer Bedarfsregel — identische Validierung, Autorisierung und
 * Sqid-Dekodierung wie beim Anlegen (PUT ersetzt den Datensatz vollständig).
 */
class UpdateCoverageRequirementRequest extends StoreCoverageRequirementRequest {
}
