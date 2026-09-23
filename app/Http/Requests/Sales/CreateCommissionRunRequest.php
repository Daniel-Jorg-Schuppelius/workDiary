<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CreateCommissionRunRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests;

use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Validation\Rule;

/**
 * Abrechnungslauf anlegen (Feature 146, MVP-729). Ein Lauf rechnet genau eine
 * Waehrung ab — Provisionen werden nie umgerechnet.
 */
class CreateCommissionRunRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'period' => ['nullable', 'string', 'max:20'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'currency' => ['required', Rule::in(array_map(static fn (CurrencyCode $c): string => $c->value, CurrencyCode::cases()))],
        ];
    }
}
