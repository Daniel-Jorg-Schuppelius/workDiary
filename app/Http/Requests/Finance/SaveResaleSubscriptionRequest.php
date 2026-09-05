<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveResaleSubscriptionRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Enums\Reselling\{BillingFrequency, RenewalMode, SubscriptionKind, SubscriptionProvider, SubscriptionStatus};
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\{Article, Customer, ForeignCustomer, LexofficeArticle};
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\{Rule, Validator};

/**
 * Abo im Reselling-Register anlegen/ändern (Feature 152). Genau ein Halter:
 * Kunde, Fremdkunde oder eigener Bestand — ein Abo ohne Halter ist erlaubt
 * (Import wartet auf Zuordnung), aber nie zwei Halter zugleich.
 */
class SaveResaleSubscriptionRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'customer_id' => Customer::class,
        'foreign_customer_id' => ForeignCustomer::class,
        'article_id' => Article::class,
        'lexoffice_article_id' => LexofficeArticle::class,
    ];

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array {
        return [
            'kind' => ['required', Rule::enum(SubscriptionKind::class)],
            'provider' => ['required', Rule::enum(SubscriptionProvider::class)],
            'label' => ['required', 'string', 'max:190'],
            'external_id' => ['nullable', 'string', 'max:120'],
            'external_order_id' => ['nullable', 'string', 'max:120'],
            'holder' => ['required', Rule::in(['customer', 'foreign', 'own', 'none'])],
            'customer_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('customers')],
            'foreign_customer_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('foreign_customers')],
            'article_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('articles')],
            'lexoffice_article_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('lexoffice_articles')],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after:starts_on'],
            'term_months' => ['required', 'integer', 'min:1', 'max:120'],
            'interval' => ['required', Rule::enum(BillingFrequency::class)],
            'renewal' => ['required', Rule::enum(RenewalMode::class)],
            'purchase_unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'sale_unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'status' => ['required', Rule::enum(SubscriptionStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $validator): void {
            $data = $this->validationData();
            $holder = (string) ($data['holder'] ?? 'none');
            if ($holder === 'customer' && empty($data['customer_id'])) {
                $validator->errors()->add('customer_id', (string) __('resale.error.customer_required'));
            }
            if ($holder === 'foreign' && empty($data['foreign_customer_id'])) {
                $validator->errors()->add('foreign_customer_id', (string) __('resale.error.foreign_required'));
            }
            if (($data['renewal'] ?? null) === RenewalMode::Cancel->value && empty($data['ends_on'])) {
                $validator->errors()->add('ends_on', (string) __('resale.error.ends_on_required'));
            }
        });
    }

    /**
     * Modellwerte aus der Eingabe: Halterwahl auf die Spalten abgebildet.
     *
     * @return array<string, mixed>
     */
    public function subscriptionAttributes(): array {
        $data = $this->validated();
        $holder = (string) $data['holder'];
        // Dialog: Kunde gewählt + Fremdkunde gewählt ⇒ Halter ist der Fremdkunde.
        if ($holder === 'customer' && ! empty($data['foreign_customer_id'])) {
            $holder = 'foreign';
        }

        return [
            'kind' => $data['kind'],
            'provider' => $data['provider'],
            'label' => trim((string) $data['label']),
            'external_id' => $this->nullable($data['external_id'] ?? null),
            'external_order_id' => $this->nullable($data['external_order_id'] ?? null),
            'customer_id' => $holder === 'customer' ? (int) $data['customer_id'] : null,
            'foreign_customer_id' => $holder === 'foreign' ? (int) $data['foreign_customer_id'] : null,
            'is_own_holding' => $holder === 'own',
            'article_id' => isset($data['article_id']) && $data['article_id'] !== '' ? (int) $data['article_id'] : null,
            'lexoffice_article_id' => isset($data['lexoffice_article_id']) && $data['lexoffice_article_id'] !== '' ? (int) $data['lexoffice_article_id'] : null,
            'quantity' => (int) $data['quantity'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $this->nullable($data['ends_on'] ?? null),
            'term_months' => (int) $data['term_months'],
            'interval' => $data['interval'],
            'renewal' => $data['renewal'],
            'purchase_unit_price' => $this->nullable($data['purchase_unit_price'] ?? null),
            'sale_unit_price' => $this->nullable($data['sale_unit_price'] ?? null),
            'status' => $data['status'],
            'notes' => $this->nullable($data['notes'] ?? null),
        ];
    }

    private function nullable(mixed $value): ?string {
        $value = is_string($value) ? trim($value) : $value;

        return $value === null || $value === '' ? null : (string) $value;
    }
}
