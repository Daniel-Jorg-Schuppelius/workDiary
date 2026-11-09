<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveLegacySupportCustomerRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Http\Requests;

use App\Legacy\Models\LegacySupportCustomer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Kundenkonto des Support-Portals. Die Längen folgen den Spalten von `kp_user`
 * (Anmeldename und Passwort je 10 Zeichen), die Mindestlänge dem Altportal.
 */
class SaveLegacySupportCustomerRequest extends FormRequest {
    private const string SINGLE_LINE = 'not_regex:/[\x00-\x1F\x7F]/';

    public function authorize(): bool {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        /** @var LegacySupportCustomer|null $customer */
        $customer = $this->route('customer');

        return [
            // „self" kennzeichnet im Altportal die Bürokonten.
            'kunde' => ['required', 'string', 'min:4', 'max:255', self::SINGLE_LINE, Rule::notIn(['self'])],
            'usern' => ['required', 'string', 'min:4', 'max:10', self::SINGLE_LINE, Rule::unique('legacy.kp_user', 'usern')->ignore($customer?->id)],
            'passw' => [$this->isMethod('PUT') ? 'nullable' : 'required', 'string', 'min:4', 'max:10', 'confirmed', self::SINGLE_LINE],
        ];
    }
}
