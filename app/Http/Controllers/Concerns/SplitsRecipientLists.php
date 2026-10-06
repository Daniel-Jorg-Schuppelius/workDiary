<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SplitsRecipientLists.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Versanddialoge nehmen mehrere Adressen in einem Feld entgegen (Komma,
 * Semikolon, Leerraum). Aufgeteilt wird hier vor der Validierung — das frühere
 * Inline-Skript im Dialog lief nie, weil der Dialog als Fragment nachgeladen
 * wird (Konsolidierungs-Audit 2026-10, k4-03).
 */
trait SplitsRecipientLists {
    /** @param list<string> $fields */
    protected function splitRecipientLists(Request $request, array $fields = ['to', 'cc', 'bcc']): void {
        $merged = [];
        foreach ($fields as $field) {
            $addresses = [];
            foreach (Arr::wrap($request->input($field)) as $raw) {
                array_push($addresses, ...(preg_split('/[,;\s]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY) ?: []));
            }
            $merged[$field] = $addresses;
        }
        $request->merge($merged);
    }
}
