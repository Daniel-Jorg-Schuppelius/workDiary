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
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\LexofficeVoucher;
use App\Rules\ExistsInCurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;

/**
 * Eingangsbeleg pro rata zuteilen (Feature 152, MVP-762): Beleg als Sqid,
 * Anbieter ohne Domain-Reselling (Domain-Belege kommen nur über den Sync),
 * Anteil und Leistungsmonat.
 */
class ResalePurchaseStoreRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'voucher_id' => LexofficeVoucher::class,
    ];

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array {
        return [
            'voucher_id' => ['required', 'integer', new ExistsInCurrentOrganization('lexoffice_vouchers')],
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
            'voucher_id.required' => (string) __('resale.purchase.error.voucher'),
            'voucher_id.integer' => (string) __('resale.purchase.error.voucher'),
            'provider.not_in' => (string) __('resale.purchase_dialog.domain_provider'),
        ];
    }

    public function voucher(): LexofficeVoucher {
        return LexofficeVoucher::query()->findOrFail((int) $this->validated('voucher_id'));
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
}
