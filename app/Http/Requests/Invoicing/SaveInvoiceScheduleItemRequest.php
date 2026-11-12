<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveInvoiceScheduleItemRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Invoicing;

use App\Http\Requests\BaseFormRequest;

/** Position eines Abrechnungsplans (MVP-415); Kategorien ohne Steuer tragen 0 % (MVP-1623). */
class SaveInvoiceScheduleItemRequest extends BaseFormRequest {
    use \App\Http\Requests\Concerns\ZeroesRateOfUntaxedCategory;

    /** @return array<string, array<int, string>> */
    public function rules(): array {
        return [
            'description' => ['required', 'string', 'max:1000'],
            // MVP-1054: Titel und Text ohne Menge und Preis.
            'line_kind' => ['nullable', 'in:item,title,text'],
            'quantity' => ['exclude_if:line_kind,title,text', 'required', 'numeric', 'min:0.001', 'max:9999999'],
            'unit' => ['nullable', 'string', 'max:32'],
            'unit_price' => ['exclude_if:line_kind,title,text', 'required', 'numeric', 'min:0', 'max:9999999'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100', 'prohibits:discount_amount'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'tax_category' => ['nullable', 'in:S,AE,Z,E,G,K,O'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
            // MVP-1053: Arbeitsanteil nach § 35a EStG, wandert in die erzeugten Rechnungen.
            'labour_share_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
