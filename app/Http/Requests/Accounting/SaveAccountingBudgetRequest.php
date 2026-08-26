<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveAccountingBudgetRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Enums\User\Permission;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\CostCenter;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Accounting\AccountingBudgetService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Budget eines Kontos speichern (Feature 142, MVP-709): Jahreswert oder
 * zwölf Monatswerte. Beträge kommen als Text (DE- oder EN-Format), der
 * Service kanonisiert sie — hier wird nur die Form geprüft.
 */
class SaveAccountingBudgetRequest extends FormRequest {
    use DecodesSqidInputs;

    /**
     * DE-Format mit Tausenderpunkt nur zusammen mit Komma-Dezimalen (1.500,50)
     * oder schlichte Dezimalzahl (1500.50 / 1500,50 / 1500) — „12.000" allein
     * wäre zweideutig und wird abgelehnt. Der Service kanonisiert.
     */
    private const AMOUNT = 'regex:/^-?(\d{1,3}(\.\d{3})+,\d{1,2}|\d{1,15}([.,]\d{1,2})?)$/';

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'cost_center' => CostCenter::class,
    ];

    public function authorize(): bool {
        return Gate::allows(Permission::AccountingLedgerPrepare->value);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array {
        return [
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'cost_center' => ['nullable', 'integer', new ExistsInCurrentOrganization('cost_centers')],
            'mode' => ['required', 'string', 'in:' . AccountingBudgetService::MODE_YEAR . ',' . AccountingBudgetService::MODE_MONTHS],
            'year_amount' => ['nullable', 'string', self::AMOUNT],
            'months' => ['nullable', 'array', 'max:12'],
            'months.*' => ['nullable', 'string', self::AMOUNT],
            'note' => ['nullable', 'string', 'max:191'],
        ];
    }
}
