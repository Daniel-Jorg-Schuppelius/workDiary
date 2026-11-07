<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StoreSupportingDocumentLinkRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Invoicing;

use App\Http\Requests\BaseFormRequest;

/** Link auf eine extern abgelegte Unterlage (BT-124, Feature 196). */
class StoreSupportingDocumentLinkRequest extends BaseFormRequest {
    /** @return array<string, array<int, string>> */
    public function rules(): array {
        return [
            'url' => ['required', 'string', 'max:2048', 'url:https'],
            'description' => ['required', 'string', 'max:255'],
        ];
    }
}
