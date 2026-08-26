<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveInvoiceItemRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

class SaveInvoiceItemRequest extends BaseFormRequest {
    use \App\Http\Requests\Concerns\DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'article_id' => \App\Models\Article::class,
    ];

    /** @return array<string, array<int, string|\Illuminate\Contracts\Validation\ValidationRule>> */
    public function rules(): array {
        return [
            // Optionaler Artikelbezug (Feature 140, Umsatz je Produkt).
            'article_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('articles')],
            'description' => ['required', 'string', 'max:1000'],
            'service_date' => ['nullable', 'date'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:32'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            // MVP-416: Positionsrabatt — Prozent XOR fester Betrag.
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100', 'prohibits:discount_amount'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'position' => ['nullable', 'integer', 'min:0'],
            // Phase 23 (MVP-240): Positions-Steuersatz + EN-16931-Kategorie.
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'tax_category' => ['nullable', 'in:S,AE,Z,E,G,K,O'],
        ];
    }
}
