<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveUserBookmarkRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

class SaveUserBookmarkRequest extends BaseFormRequest {
    /** @return array<string, array<int, string>> */
    public function rules(): array {
        return [
            'label' => ['required', 'string', 'max:120'],
            // Nur http(s) oder site-relative Pfade — blockt javascript:/data:-
            // Schemata (die im href als stored XSS ausgeführt würden). Das Feld
            // wird ungeprüft in <a href> gerendert (Sidebar auf jeder Seite).
            'url' => ['required', 'string', 'max:2048', 'regex:#^(https?://|/)#i'],
            'icon' => ['nullable', 'string', 'max:32'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array {
        return [
            'url.regex' => __('Die URL muss mit http://, https:// oder / beginnen.'),
        ];
    }
}
