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
use App\Http\Requests\Finance\Concerns\ResolvesResaleHolder;
use App\Models\{Article, Customer, ForeignCustomer, LexofficeArticle};
use App\Models\Reselling\ResaleSubscription;
use App\Rules\ExistsInCurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Validation\{Rule, Validator};

/**
 * Abo im Reselling-Register anlegen/ändern (Feature 152). Genau ein Halter:
 * Kunde, Fremdkunde oder eigener Bestand — ein Abo ohne Halter ist erlaubt
 * (Import wartet auf Zuordnung), aber nie zwei Halter zugleich.
 *
 * Gesperrte Felder (Review 2026-09-10, B7/B9/A14): Domain-Abos behalten
 * Anbieter und Kennung (der Domain-Sync führt sie), importierte Abos ihre
 * Kennung (sonst Duplikat beim Re-Import), Abtretungen Produkt, Anbieter und
 * Rhythmus des Vertrags. „DomainReselling" ist von Hand nie wählbar.
 */
class SaveResaleSubscriptionRequest extends BaseFormRequest {
    use DecodesSqidInputs;
    use ResolvesResaleHolder;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'customer_id' => Customer::class,
        'foreign_customer_id' => ForeignCustomer::class,
        'article_id' => Article::class,
        'lexoffice_article_id' => LexofficeArticle::class,
    ];

    /**
     * Welche Felder der Dialog nicht ändern darf — vom Abo selbst bestimmt.
     *
     * @return array{provider: bool, external_id: bool, product: bool, interval: bool}
     */
    public static function lockedFieldsFor(?ResaleSubscription $subscription): array {
        if ($subscription === null) {
            return ['provider' => false, 'external_id' => false, 'product' => false, 'interval' => false];
        }

        return [
            'provider' => $subscription->isDomain() || $subscription->isAssignment(),
            'external_id' => $subscription->isDomain() || $subscription->isImported(),
            'product' => $subscription->isAssignment(),
            'interval' => $subscription->isAssignment(),
        ];
    }

    protected function subscription(): ?ResaleSubscription {
        $subscription = $this->route('subscription');

        return $subscription instanceof ResaleSubscription ? $subscription : null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array {
        $locked = self::lockedFieldsFor($this->subscription());
        $providers = array_map(static fn(SubscriptionProvider $p): string => $p->value, SubscriptionProvider::cases());

        return [
            'kind' => ['required', Rule::enum(SubscriptionKind::class)],
            // Domains kommen nur über den Domain-Sync; ein Datei-/Hand-Abo würde er nachts beenden.
            'provider' => $locked['provider'] ? ['nullable', Rule::in($providers)] : ['required', Rule::enum(SubscriptionProvider::class), Rule::notIn([SubscriptionProvider::DomainReselling->value])],
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
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'term_months' => ['required', 'integer', 'min:1', 'max:120'],
            'interval' => $locked['interval'] ? ['nullable', Rule::enum(BillingFrequency::class)] : ['required', Rule::enum(BillingFrequency::class)],
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
            $subscription = $this->subscription();
            $this->validateHolder($validator, (string) ($data['holder'] ?? 'none'), $data, $subscription?->foreign_customer_id);
            if (($data['renewal'] ?? null) === RenewalMode::Cancel->value && empty($data['ends_on'])) {
                $validator->errors()->add('ends_on', (string) __('resale.error.ends_on_required'));
            }
            if ($subscription !== null && ! $validator->errors()->hasAny(['quantity', 'starts_on', 'ends_on'])) {
                $this->validateAssignedQuantity($validator, $subscription, (int) $data['quantity'], (string) $data['starts_on'], $data['ends_on'] ?? null);
            }
        });
    }

    /**
     * Abtretungen kennen (B9): eine Abtretung darf nur den Rest des Vertrags
     * über ihre ganze Laufzeit nehmen; ein Vertrag nie unter die Spitze
     * seiner Abtretungen fallen.
     */
    private function validateAssignedQuantity(Validator $validator, ResaleSubscription $subscription, int $quantity, string $startsOn, mixed $endsOn): void {
        $from = CarbonImmutable::parse($startsOn);
        $to = is_string($endsOn) && $endsOn !== '' ? CarbonImmutable::parse($endsOn) : null;
        if ($subscription->isAssignment()) {
            $parent = $subscription->parent()->with('assignments')->first();
            if ($parent === null) {
                return;
            }
            $parent->setRelation('assignments', $parent->assignments->reject(static fn(ResaleSubscription $a): bool => $a->id === $subscription->id)->values());
            $available = $parent->quantity - $parent->assignedQuantityBetween($from, $to);
            if ($quantity > $available) {
                $validator->errors()->add('quantity', (string) __('resale.transfer.error.quantity', ['available' => max(0, $available)]));
            }

            return;
        }
        $subscription->load('assignments');
        if ($subscription->assignments->isEmpty()) {
            return;
        }
        $assigned = $subscription->assignedQuantityBetween($from, $to);
        if ($quantity < $assigned) {
            $validator->errors()->add('quantity', (string) __('resale.holder_error.quantity_below_assigned', ['assigned' => $assigned]));
        }
    }

    /**
     * Modellwerte aus der Eingabe: Halterwahl auf die Spalten abgebildet,
     * gesperrte Felder vom Abo bzw. vom Vertrag der Abtretung.
     *
     * @return array<string, mixed>
     */
    public function subscriptionAttributes(): array {
        $data = $this->validated();
        $subscription = $this->subscription();
        $locked = self::lockedFieldsFor($subscription);
        $parent = $subscription?->isAssignment() === true ? $subscription->parent : null;
        $holder = $this->resolveHolder((string) $data['holder'], $data);

        $attributes = [
            'kind' => $data['kind'],
            'provider' => $data['provider'] ?? null,
            'label' => trim((string) $data['label']),
            'external_id' => $this->nullable($data['external_id'] ?? null),
            'external_order_id' => $this->nullable($data['external_order_id'] ?? null),
            'customer_id' => $holder['foreign'] === null ? $holder['customer']?->id : null,
            'foreign_customer_id' => $holder['foreign']?->id,
            'is_own_holding' => $holder['own'],
            'article_id' => isset($data['article_id']) && $data['article_id'] !== '' ? (int) $data['article_id'] : null,
            'lexoffice_article_id' => isset($data['lexoffice_article_id']) && $data['lexoffice_article_id'] !== '' ? (int) $data['lexoffice_article_id'] : null,
            'quantity' => (int) $data['quantity'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $this->nullable($data['ends_on'] ?? null),
            'term_months' => (int) $data['term_months'],
            'interval' => $data['interval'] ?? null,
            'renewal' => $data['renewal'],
            'purchase_unit_price' => $this->nullable($data['purchase_unit_price'] ?? null),
            'sale_unit_price' => $this->nullable($data['sale_unit_price'] ?? null),
            'status' => $data['status'],
            'notes' => $this->nullable($data['notes'] ?? null),
        ];
        if ($subscription !== null) {
            $source = $parent ?? $subscription;
            if ($locked['provider']) {
                $attributes['provider'] = $source->provider;
            }
            if ($locked['external_id']) {
                $attributes['external_id'] = $subscription->external_id;
            }
            if ($locked['product']) {
                $attributes['article_id'] = $source->article_id;
                $attributes['lexoffice_article_id'] = $source->lexoffice_article_id;
            }
            if ($locked['interval']) {
                $attributes['interval'] = $source->interval;
            }
        }

        return $attributes;
    }

    private function nullable(mixed $value): ?string {
        $value = is_string($value) ? trim($value) : $value;

        return $value === null || $value === '' ? null : (string) $value;
    }
}
