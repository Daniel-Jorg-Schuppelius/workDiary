<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveExpenseRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\Expense\PaymentMethod;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveExpenseRequest extends FormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'expense_category_id' => \App\Models\ExpenseCategory::class,
        'customer_id' => \App\Models\Customer::class,
        'task_id' => \App\Models\Task::class,
        'attendance_id' => \App\Models\Attendance::class,
    ];

    public function authorize(): bool {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'date' => ['required', 'date'],
            'expense_category_id' => ['nullable', 'integer', Rule::exists('expense_categories', 'id')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'task_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')],
            'attendance_id' => ['nullable', 'integer', Rule::exists('attendances', 'id')],
            'vendor' => ['nullable', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:500'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'currency' => ['nullable', 'string', 'size:3'],
            'amount_net' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'amount_gross' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'billable' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void {
        $this->merge([
            'billable' => $this->boolean('billable'),
        ]);
    }
}
