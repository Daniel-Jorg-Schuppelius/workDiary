<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UpdateCustomerDeadlineRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * Schritt an einer Kundenfrist (Feature 197, MVP-1632): erledigt (Notiz
 * optional), verlängert (neuer Termin und Grund), vorangefordert (§ 149 Abs. 4
 * AO: Datum der Anforderung und Termin), entfällt bzw. wieder öffnen (Grund).
 */
class UpdateCustomerDeadlineRequest extends BaseFormRequest {
    public const array ACTIONS = ['complete', 'extend', 'advance', 'waive', 'reopen'];

    /** @return array<string, mixed> */
    public function rules(): array {
        $action = $this->string('action')->toString();

        return [
            'action' => ['required', 'string', Rule::in(self::ACTIONS)],
            'text' => [in_array($action, ['extend', 'waive', 'reopen'], true) ? 'required' : 'nullable', 'string', 'max:1000'],
            'due_on' => [in_array($action, ['extend', 'advance'], true) ? 'required' : 'nullable', 'date_format:Y-m-d'],
            'requested_on' => [$action === 'advance' ? 'required' : 'nullable', 'date_format:Y-m-d'],
        ];
    }
}
