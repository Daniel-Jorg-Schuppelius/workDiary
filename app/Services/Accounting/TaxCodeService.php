<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaxCodeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\Finance\TaxCodeDirection;
use App\Enums\Invoicing\IncomingInvoiceTaxCase;
use App\Models\Accounting\{AccountingAccount, AccountingEntryLine, AccountingPostingRule, AccountingTaxCode};
use App\Models\Platform\Organization;
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pflege der Steuerkennzeichen (Feature 125, MVP-1558).
 *
 * Satz, Richtung und Steuerkonto eines **bebuchten** Kennzeichens ändern sich
 * nur zum Stichtag: Die bisherige Fassung endet am Vortag, die neue beginnt —
 * Altbuchungen behalten, was für sie galt (Muster der Buchungsregeln). Ein
 * unbenutztes Kennzeichen wird direkt korrigiert. Stilllegen statt löschen.
 */
class TaxCodeService {
    /** Steuerkategorien (EN 16931) der Umsätze ohne Steuer: innergemeinschaftlich, Steuerschuld des Empfängers, Ausfuhr, nicht steuerbar, steuerfrei. */
    public const array ZERO_RATED_CATEGORIES = ['K', 'AE', 'G', 'O', 'E'];

    /**
     * @param  array{code: string, name: string, direction: TaxCodeDirection, rate: string, tax_account_id: ?int, input_tax_account_id?: ?int, tax_case?: ?IncomingInvoiceTaxCase, ustva_base_field: ?string, ustva_tax_field: ?string, ustva_input_tax_field?: ?string, datev_tax_key?: ?string, valid_from: CarbonImmutable}  $data
     *
     * @throws ValidationException
     */
    public function create(Organization $organization, array $data): AccountingTaxCode {
        $this->assertTaxAccount($organization, $data);

        $taken = AccountingTaxCode::query()
            ->where('organization_id', $organization->id)
            ->where('code', $data['code'])
            ->exists();
        if ($taken) {
            throw ValidationException::withMessages([
                'code' => (string) __('accounting.tax_codes.error.code_taken', ['code' => $data['code']]),
            ]);
        }

        return AccountingTaxCode::query()->create($this->attributes($data) + [
            'organization_id' => $organization->id,
            'code' => $data['code'],
            'valid_from' => $data['valid_from']->toDateString(),
            'is_active' => true,
        ]);
    }

    /**
     * @param  array{name: string, direction: TaxCodeDirection, rate: string, tax_account_id: ?int, input_tax_account_id?: ?int, tax_case?: ?IncomingInvoiceTaxCase, ustva_base_field: ?string, ustva_tax_field: ?string, ustva_input_tax_field?: ?string, datev_tax_key?: ?string, valid_from: CarbonImmutable}  $data
     *
     * @throws ValidationException
     */
    public function update(AccountingTaxCode $taxCode, array $data): AccountingTaxCode {
        $organization = Organization::query()->findOrFail($taxCode->organization_id);
        $this->assertTaxAccount($organization, $data);

        $changesPosting = ! Decimal::of((string) $taxCode->rate, 2)->equals(Decimal::of($data['rate'], 2))
            || $taxCode->direction !== $data['direction']
            || (int) $taxCode->tax_account_id !== (int) $data['tax_account_id']
            || (int) $taxCode->input_tax_account_id !== (int) ($data['input_tax_account_id'] ?? 0);

        // Name und Kennziffern sind Beschriftung; unbenutzt ist alles korrigierbar.
        if (! $changesPosting || ! $this->isInUse($taxCode)) {
            $taxCode->update($this->attributes($data));

            return $taxCode->refresh();
        }

        $validFrom = $data['valid_from'];
        if (! $validFrom->greaterThan(CarbonImmutable::parse($taxCode->valid_from))) {
            throw ValidationException::withMessages([
                'valid_from' => (string) __('accounting.tax_codes.error.valid_from_required', [
                    'date' => $taxCode->valid_from->format(\App\Support\Formats::date()),
                ]),
            ]);
        }

        return DB::transaction(function () use ($taxCode, $data, $validFrom): AccountingTaxCode {
            $taxCode->update(['valid_to' => $validFrom->subDay()->toDateString()]);

            $next = AccountingTaxCode::query()->create($this->attributes($data) + [
                'organization_id' => $taxCode->organization_id,
                'code' => $taxCode->code,
                'valid_from' => $validFrom->toDateString(),
                'is_active' => true,
            ]);

            // Regeln und Konten-Vorgaben meinen das Kennzeichen, nicht eine Fassung: Sie folgen der neuen.
            AccountingPostingRule::query()
                ->where('organization_id', $taxCode->organization_id)
                ->where('accounting_tax_code_id', $taxCode->id)
                ->where('is_active', true)
                ->get()
                ->each(fn (AccountingPostingRule $rule): bool => $rule->update(['accounting_tax_code_id' => $next->id]));
            AccountingAccount::query()
                ->where('organization_id', $taxCode->organization_id)
                ->where('default_tax_code_id', $taxCode->id)
                ->get()
                ->each(fn (AccountingAccount $account): bool => $account->update(['default_tax_code_id' => $next->id]));

            return $next;
        });
    }

    /** Stilllegen: neue Buchungen wählen es nicht mehr, Altbuchungen behalten ihren Bezug. */
    public function deactivate(AccountingTaxCode $taxCode): AccountingTaxCode {
        $taxCode->update(['is_active' => false]);

        return $taxCode->refresh();
    }

    public function isInUse(AccountingTaxCode $taxCode): bool {
        return AccountingEntryLine::query()->where('accounting_tax_code_id', $taxCode->id)->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array {
        return [
            'name' => $data['name'],
            'direction' => $data['direction'],
            'rate' => Decimal::of((string) $data['rate'], 2)->getValue(),
            'tax_account_id' => $data['tax_account_id'],
            // Vorsteuerkonto und Steuerfall nur bei Steuerschuld des Empfängers (MVP-1621).
            'input_tax_account_id' => $data['direction'] === TaxCodeDirection::SelfAssessed ? ($data['input_tax_account_id'] ?? null) : null,
            'tax_case' => $data['direction'] === TaxCodeDirection::SelfAssessed ? ($data['tax_case'] ?? null) : null,
            'tax_category' => $data['tax_category'] ?? null,
            'ustva_base_field' => $data['ustva_base_field'],
            'ustva_tax_field' => $data['ustva_tax_field'],
            'ustva_input_tax_field' => $data['direction'] === TaxCodeDirection::SelfAssessed ? ($data['ustva_input_tax_field'] ?? null) : null,
            'datev_tax_key' => $data['datev_tax_key'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function assertTaxAccount(Organization $organization, array $data): void {
        $accountId = $data['tax_account_id'] ?? null;
        $needsAccount = ! Decimal::of((string) $data['rate'], 2)->isZero();

        if ($accountId === null && $needsAccount) {
            throw ValidationException::withMessages([
                'tax_account' => (string) __('accounting.tax_codes.error.tax_account_required'),
            ]);
        }
        if ($accountId !== null && AccountingAccount::query()->where('organization_id', $organization->id)->whereKey($accountId)->doesntExist()) {
            throw ValidationException::withMessages([
                'tax_account' => (string) __('accounting.ledger.error.unknown_account'),
            ]);
        }

        if (($data['direction'] ?? null) !== TaxCodeDirection::SelfAssessed) {
            return;
        }
        $inputAccountId = $data['input_tax_account_id'] ?? null;
        if ($inputAccountId === null && $needsAccount) {
            throw ValidationException::withMessages([
                'input_tax_account' => (string) __('accounting.tax_codes.error.input_tax_account_required'),
            ]);
        }
        if ($inputAccountId !== null && AccountingAccount::query()->where('organization_id', $organization->id)->whereKey($inputAccountId)->doesntExist()) {
            throw ValidationException::withMessages([
                'input_tax_account' => (string) __('accounting.ledger.error.unknown_account'),
            ]);
        }
    }
}
