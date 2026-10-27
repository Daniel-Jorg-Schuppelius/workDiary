<?php
/*
 * Created on   : Tue Oct 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BulkAssignResaleHolderRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Reselling;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Http\Requests\Finance\Concerns\ResolvesResaleHolder;
use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Reselling\ResaleSubscription;
use App\Rules\ExistsInCurrentOrganization;
use App\Support\Sqid;
use Illuminate\Validation\{Rule, Validator};

/**
 * Sammelzuordnung des Halters aus der Abo-Liste (Feature 152, Nachbesserung
 * 2026-10-27): ein Halter für alle gewählten Abos; Modus und Halterprüfung
 * wie der Inbox-Dialog, der nur Firmen laut Anbieter kennt.
 */
class BulkAssignResaleHolderRequest extends BaseFormRequest {
    use DecodesSqidInputs;
    use ResolvesResaleHolder;

    public const MAX_IDS = 200;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'customer_id' => Customer::class,
        'foreign_customer_id' => ForeignCustomer::class,
    ];

    protected function prepareForValidation(): void {
        // Ausgeblendete Schritte des Dialogs tragen keine Entscheidung.
        $mode = (string) $this->input('mode');
        $this->merge([
            'customer_id' => in_array($mode, ['customer', 'partner'], true) ? $this->input('customer_id') : null,
            'foreign_customer_id' => $mode === 'foreign' ? $this->input('foreign_customer_id') : null,
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array {
        return [
            'ids' => ['required', 'array', 'max:' . self::MAX_IDS],
            'ids.*' => ['string', 'max:64'],
            'mode' => ['required', Rule::in(['customer', 'partner', 'foreign', 'own'])],
            'customer_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('customers')],
            'foreign_customer_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('foreign_customers')],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $validator): void {
            $data = $this->validationData();
            $this->validateHolder($validator, (string) ($data['mode'] ?? ''), $data);
        });
    }

    public function mode(): string {
        return (string) $this->validated('mode');
    }

    /** @return list<int> */
    public function subscriptionIds(): array {
        /** @var list<string> $ids */
        $ids = $this->validated('ids');

        return array_values(array_filter(array_map(static fn(string $sqid): ?int => Sqid::decode(ResaleSubscription::class, $sqid), $ids)));
    }

    /**
     * @return array{customer: Customer|null, foreign: ForeignCustomer|null, own: bool}
     */
    public function holder(): array {
        return $this->resolveHolder($this->mode(), $this->validated());
    }

    public function remember(): bool {
        return $this->boolean('remember');
    }
}
