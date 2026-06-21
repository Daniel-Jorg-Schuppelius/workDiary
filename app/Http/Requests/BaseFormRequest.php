<?php
/*
 * Created on   : Sun Jun 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BaseFormRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Basisklasse für FormRequests, deren Autorisierung im Controller über
 * Policies/Gate erfolgt (der Request selbst lässt durch). Requests mit eigener
 * Autorisierungslogik überschreiben authorize() weiterhin.
 */
abstract class BaseFormRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }
}
