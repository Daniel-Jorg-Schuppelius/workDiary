<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuthorizesCarrier.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Wer etwas an einen Träger hängt (Notiz, offener Punkt, Dokument, Formular,
 * Prozedurlauf), muss den Träger sehen dürfen. Die Prüfung saß nur im Dialog
 * und in den Listen, nicht am Schreibweg (Sicherheitsaudit 2026-10-04, authz-b-6).
 */
trait AuthorizesCarrier {
    protected function authorizeCarrier(Model $carrier): void {
        // Träger ohne Policy regelt allein das Fachrecht des Kindes.
        if (Gate::getPolicyFor($carrier) !== null) {
            Gate::authorize('view', $carrier);
        }
    }
}
