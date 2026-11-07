<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StoreSupportingDocumentUploadRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Invoicing;

use App\Http\Requests\BaseFormRequest;

/** Unterlage zur E-Rechnung hochladen (Feature 196); den Typ prüft der Dienst am Inhalt. */
class StoreSupportingDocumentUploadRequest extends BaseFormRequest {
    /** Größte Einzeldatei: die engste Plattformgrenze (10 MB für alle Anhänge) passt sonst schon allein nicht. */
    public const MAX_KB = 10240;

    /** @return array<string, array<int, string>> */
    public function rules(): array {
        return [
            'file' => ['required', 'file', 'max:' . self::MAX_KB, 'mimes:pdf,png,jpg,jpeg,csv,txt,xlsx,ods'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
