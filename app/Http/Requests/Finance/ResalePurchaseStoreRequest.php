<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResalePurchaseStoreRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Enums\Reselling\SubscriptionProvider;
use App\Http\Requests\BaseFormRequest;
use App\Models\Organization;
use App\Services\Reselling\Purchase\{PurchaseDocument, PurchaseDocuments};
use Carbon\CarbonImmutable;
use Illuminate\Validation\{Rule, Validator};

/**
 * Eingangsbeleg pro rata zuteilen (Feature 152, MVP-762; Review 2026-09-11):
 * Beleg als Quellschlüssel ({@see PurchaseDocument::$key} — bestehende
 * Lexoffice-Sqids bleiben gültig), Anbieter ohne Domain-Reselling
 * (Domain-Belege kommen nur über den Sync), Anteil und Leistungsmonat.
 */
class ResalePurchaseStoreRequest extends BaseFormRequest {
    private ?PurchaseDocument $document = null;

    private bool $documentResolved = false;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array {
        return [
            'document' => ['required', 'string', 'max:80'],
            'provider' => ['required', Rule::enum(SubscriptionProvider::class), Rule::notIn([SubscriptionProvider::DomainReselling->value])],
            'net_amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'month' => ['required', 'date_format:Y-m'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array {
        return [
            'document.required' => (string) __('resale.purchase.error.voucher'),
            'document.string' => (string) __('resale.purchase.error.voucher'),
            'document.max' => (string) __('resale.purchase.error.voucher'),
            'provider.not_in' => (string) __('resale.purchase_dialog.domain_provider'),
        ];
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('document')) {
                return;
            }
            if ($this->resolveDocument() === null) {
                $validator->errors()->add('document', (string) __('resale.purchase_document.error_unknown'));
            }
        });
    }

    /** Beleg aus dem Quellschlüssel — nach bestandener Validierung immer vorhanden. */
    public function document(): PurchaseDocument {
        $document = $this->resolveDocument();
        if ($document === null) {
            throw new \LogicException('ResalePurchaseStoreRequest: der Beleg wurde nicht validiert.');
        }

        return $document;
    }

    public function provider(): SubscriptionProvider {
        return SubscriptionProvider::from((string) $this->validated('provider'));
    }

    public function netAmount(): float {
        return (float) $this->validated('net_amount');
    }

    /** Erster Tag des Leistungsmonats. */
    public function month(): CarbonImmutable {
        return CarbonImmutable::createFromFormat('!Y-m', (string) $this->validated('month')) ?: CarbonImmutable::now()->startOfMonth();
    }

    /** Die Quelle, deren Schlüssel es ist, liefert den Beleg; null wenn unbekannt, nicht zuteilbar oder nicht aus der aktiven Organisation. */
    private function resolveDocument(): ?PurchaseDocument {
        if (! $this->documentResolved) {
            $this->documentResolved = true;
            $organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;
            $key = $this->input('document');
            if ($organization instanceof Organization && is_scalar($key) && trim((string) $key) !== '') {
                $this->document = app(PurchaseDocuments::class)->byKey($organization, trim((string) $key));
            }
        }

        return $this->document;
    }
}
