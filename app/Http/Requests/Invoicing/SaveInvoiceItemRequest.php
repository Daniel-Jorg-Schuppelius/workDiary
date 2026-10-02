<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveInvoiceItemRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\Invoicing;

use App\Http\Requests\BaseFormRequest;

class SaveInvoiceItemRequest extends BaseFormRequest {
    use \App\Http\Requests\Concerns\DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'article_id' => \App\Models\Article\Article::class,
        'article_variant_id' => \App\Models\Article\ArticleVariant::class,
    ];

    /** @return array<string, array<int, string|\Illuminate\Contracts\Validation\ValidationRule>> */
    public function rules(): array {
        return [
            // Optionaler Artikelbezug (Feature 140, Umsatz je Produkt).
            'article_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('articles')],
            // Feature 160: Variante optional; sie muss zum Artikel gehören (Prüfung im Controller).
            'article_variant_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('article_variants')],
            'description' => ['required', 'string', 'max:1000'],
            'service_date' => ['nullable', 'date'],
            // Leistungszeitraum (Feature 152): optional; Ende nie vor Beginn, kein Ende ohne Beginn.
            'service_from' => ['nullable', 'date_format:Y-m-d', 'required_with:service_to'],
            'service_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:service_from'],
            // MVP-1054: Titel und Text tragen weder Menge noch Preis.
            'line_kind' => ['nullable', 'in:item,title,text'],
            'quantity' => ['exclude_if:line_kind,title,text', 'required', 'numeric', 'min:0', 'max:999999999.999'],
            'unit' => ['nullable', 'string', 'max:32'],
            'unit_price' => ['exclude_if:line_kind,title,text', 'required', 'numeric', 'min:0', 'max:99999999.9999'],
            // MVP-416: Positionsrabatt — Prozent XOR fester Betrag.
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100', 'prohibits:discount_amount'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'position' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            // Phase 23 (MVP-240): Positions-Steuersatz + EN-16931-Kategorie.
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'tax_category' => ['nullable', 'in:S,AE,Z,E,G,K,O'],
            // MVP-1053: Arbeitsanteil nach § 35a EStG.
            'labour_share_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
