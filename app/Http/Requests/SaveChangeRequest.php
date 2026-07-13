<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveChangeRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\User\UserRole;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

/**
 * Change anlegen (Feature 065, MVP-157): Sqid-Referenzen werden je
 * ZIELKLASSE dekodiert (DecodesSqidInputs), Org-Grenzen prüft
 * ExistsInCurrentOrganization. Rollback ist bei normal/emergency schon
 * hier Pflicht (der Service erzwingt es als zweite Linie inkl.
 * Template-Fallback); standard verlangt eine Vorlage — ob sie
 * FREIGEGEBEN ist, entscheidet ausschließlich ChangeService::submit().
 */
class SaveChangeRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'change_template_id' => \App\Models\ChangeTemplate::class,
        'problem_id' => \App\Models\Problem::class,
        'ticket_ids' => \App\Models\ServiceTicket::class,
        'asset_ids' => \App\Models\Asset::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        $internalRoles = array_values(array_filter(
            array_map(static fn(UserRole $r): string => $r->value, UserRole::cases()),
            static fn(string $role): bool => $role !== UserRole::Kunde->value,
        ));

        return [
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'change_type' => ['required', 'in:standard,normal,emergency'],
            'reason' => ['nullable', 'string', 'max:10000'],
            'scope' => ['nullable', 'string', 'max:10000'],
            'risk' => ['nullable', 'integer', 'between:1,3'],
            'impact' => ['nullable', 'integer', 'between:1,3'],
            'urgency' => ['nullable', 'integer', 'between:1,3'],
            'window_from' => ['nullable', 'date'],
            'window_to' => ['nullable', 'date', 'after_or_equal:window_from'],
            'implementation_plan' => ['nullable', 'string', 'max:20000'],
            'test_plan' => ['nullable', 'string', 'max:20000'],
            'rollback_plan' => ['nullable', 'string', 'max:20000', 'required_if:change_type,normal,emergency'],
            'change_template_id' => ['nullable', 'integer', 'required_if:change_type,standard', new ExistsInCurrentOrganization('change_templates')],
            'problem_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('problems')],
            'ticket_ids' => ['nullable', 'array', 'max:20'],
            'ticket_ids.*' => ['required', 'integer', new ExistsInCurrentOrganization('service_tickets')],
            'asset_ids' => ['nullable', 'array', 'max:50'],
            'asset_ids.*' => ['required', 'integer', new ExistsInCurrentOrganization('assets')],
            'approval_steps' => ['nullable', 'array', 'max:10'],
            'approval_steps.*.type' => ['required', 'in:user,role'],
            'approval_steps.*.user' => ['nullable', 'string'],
            'approval_steps.*.role' => ['nullable', 'string', Rule::in($internalRoles)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array {
        return [
            'rollback_plan.required_if' => (string) __('Normal- und Emergency-Changes brauchen einen Rollback-Plan.'),
            'change_template_id.required_if' => (string) __('Standard-Changes brauchen eine freigegebene Vorlage.'),
        ];
    }
}
