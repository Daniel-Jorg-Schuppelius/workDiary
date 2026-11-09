<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveLegacySupportOrderRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Http\Requests;

use App\Legacy\Models\{LegacySupportCustomer, LegacySupportOrder, LegacyUser};
use App\Support\Sqid;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Auftrag des Support-Portals aus der Mitarbeitersicht: Anlegen (POST) und Bearbeiten (PUT). */
class SaveLegacySupportOrderRequest extends FormRequest {
    private const string SINGLE_LINE = 'not_regex:/[\x00-\x1F\x7F]/';

    protected function prepareForValidation(): void {
        $this->merge(['kunde' => Sqid::decodeOrNumeric(LegacySupportCustomer::class, $this->input('kunde'))]);
    }

    public function authorize(): bool {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        $rules = [
            'kunde' => ['required', 'integer', Rule::exists('legacy.kp_user', 'id')->where(fn($query) => $query->where('id', '>=', LegacySupportCustomer::FIRST_CUSTOMER_ID))],
            'telefon' => ['nullable', 'string', 'max:30', self::SINGLE_LINE],
            'objekt' => ['nullable', 'string', 'max:255', self::SINGLE_LINE],
            'auftrag' => ['required', 'string', 'max:65535'],
        ];

        if (! $this->isMethod('PUT')) {
            return $rules;
        }

        /** @var LegacySupportOrder|null $order */
        $order = $this->route('order');
        $mechanics = LegacyUser::query()->where('id', '>', 3)->pluck('uname')->map(fn($name) => (string) $name)->all();
        if ($order !== null && $order->wer !== '') {
            $mechanics[] = (string) $order->wer;
        }

        return [
            ...$rules,
            'kname' => ['nullable', 'string', 'max:30', self::SINGLE_LINE],
            'termin' => ['nullable', 'date'],
            'wer' => ['nullable', 'string', 'max:30', Rule::in($mechanics)],
            'antwort' => ['nullable', 'string', 'max:65535'],
            'intern' => ['nullable', 'string', 'max:65535'],
            'dauer' => ['nullable', 'string', 'max:255', self::SINGLE_LINE],
            'status' => ['required', 'integer', Rule::in(LegacySupportOrder::STATUSES)],
        ];
    }
}
