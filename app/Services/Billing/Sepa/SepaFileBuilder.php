<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SepaFileBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Sepa;

use App\Enums\Finance\{MandateKind, PaymentRunKind};
use App\Models\Finance\{PaymentRun, PaymentRunItem};
use App\Services\Finance\FinancialFormatsSupport;
use App\Support\Setting;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Erzeugt die SEPA-XML eines Zahllaufs (Feature 120, MVP-609).
 *
 * Die Klassen des privaten Pakets `php-financial-formats` werden erst NACH dem
 * Verfügbarkeits-Guard und vollqualifiziert instanziiert (AGENTS.md §9.1) —
 * ohne das Paket bleibt die App lauffähig, nur der Export fehlt.
 */
class SepaFileBuilder {
    public function build(PaymentRun $run, string $messageId): string {
        FinancialFormatsSupport::ensureAvailable();

        return $run->kind === PaymentRunKind::DirectDebit
            ? $this->buildDirectDebit($run, $messageId)
            : $this->buildCreditTransfer($run, $messageId);
    }

    private function buildCreditTransfer(PaymentRun $run, string $messageId): string {
        $account = $run->bankAccount;
        $debtorIban = trim((string) ($account->iban ?? ''));
        if ($debtorIban === '') {
            throw new RuntimeException((string) __('sepa.error.account_without_iban'));
        }

        $debtorName = trim((string) ($account->account_holder ?? $account->label ?? ''));
        $builder = new \CommonToolkit\FinancialFormats\Builders\ISO20022\Pain\Pain001DocumentBuilder;
        $builder = $builder
            ->setMessageId($messageId)
            ->setInitiatingParty(new \CommonToolkit\FinancialFormats\Entities\ISO20022\Pain\PartyIdentification(name: $debtorName))
            ->beginPaymentInstruction(
                $messageId . '-PMT',
                new \CommonToolkit\FinancialFormats\Entities\ISO20022\Pain\PartyIdentification(name: $debtorName),
                new \CommonToolkit\FinancialFormats\Entities\ISO20022\Pain\AccountIdentification(iban: $debtorIban),
                $this->agent((string) ($account->bic ?? '')),
            )
            ->setRequestedExecutionDate($this->executionDate($run));

        foreach ($run->items as $item) {
            $builder = $builder->addTransaction(new \CommonToolkit\FinancialFormats\Entities\ISO20022\Pain\Type1\CreditTransferTransaction(
                paymentId: \CommonToolkit\FinancialFormats\Entities\ISO20022\Pain\PaymentIdentification::fromEndToEndId($this->endToEndId($item)),
                amount: $this->money($item),
                currency: \CommonToolkit\Enums\CurrencyCode::Euro,
                creditor: \CommonToolkit\FinancialFormats\Entities\ISO20022\Pain\PartyIdentification::fromName((string) $item->party_name),
                creditorAccount: \CommonToolkit\FinancialFormats\Entities\ISO20022\Pain\AccountIdentification::fromIban((string) $item->iban),
                // BIC ist im SEPA-Raum optional — eine erfundene wäre schlimmer
                // als keine.
                creditorAgent: $this->agent((string) ($item->bic ?? '')),
                remittanceInformation: \CommonToolkit\FinancialFormats\Entities\ISO20022\Pain\RemittanceInformation::fromText((string) $item->reference),
                chargeBearer: \CommonToolkit\FinancialFormats\Enums\Swift\Mt\ChargesCode::SLEV,
            ));
        }

        $document = $builder->endPaymentInstruction()->build();

        return (new \CommonToolkit\FinancialFormats\Generators\ISO20022\Pain\Pain001Generator)->generate($document);
    }

    private function buildDirectDebit(PaymentRun $run, string $messageId): string {
        $account = $run->bankAccount;
        $creditorIban = trim((string) ($account->iban ?? ''));
        if ($creditorIban === '') {
            throw new RuntimeException((string) __('sepa.error.account_without_iban'));
        }

        $creditorId = trim((string) Setting::get('finance.sepa_creditor_id', ''));
        if ($creditorId === '') {
            // Ohne Gläubiger-Identifikationsnummer weist die Bank die Datei
            // zurück — das gehört vor den Export, nicht danach.
            throw new RuntimeException((string) __('sepa.error.missing_creditor_id'));
        }

        $creditorName = trim((string) ($account->account_holder ?? $account->label ?? ''));
        $first = $run->items->first();
        $sequence = $first?->mandate?->isFirstCollection()
            ? \CommonToolkit\FinancialFormats\Enums\ISO20022\Pain\SequenceType::FIRST
            : ($first?->mandate?->kind === MandateKind::OneOff
                ? \CommonToolkit\FinancialFormats\Enums\ISO20022\Pain\SequenceType::ONE_OFF
                : \CommonToolkit\FinancialFormats\Enums\ISO20022\Pain\SequenceType::RECURRING);

        $builder = new \CommonToolkit\FinancialFormats\Builders\ISO20022\Pain\Pain008DocumentBuilder;
        $builder = $builder
            ->setMessageId($messageId)
            ->setInitiatingParty(new \CommonToolkit\FinancialFormats\Entities\ISO20022\Pain\PartyIdentification(name: $creditorName))
            ->beginSepaCorInstruction(
                $messageId . '-PMT',
                new \CommonToolkit\FinancialFormats\Entities\ISO20022\Pain\PartyIdentification(name: $creditorName),
                new \CommonToolkit\FinancialFormats\Entities\ISO20022\Pain\AccountIdentification(iban: $creditorIban),
                $creditorId,
                $sequence,
                $this->agent((string) ($account->bic ?? '')),
            )
            ->setRequestedCollectionDate($this->executionDate($run));

        foreach ($run->items as $item) {
            $mandate = $item->mandate;
            if ($mandate === null) {
                throw new RuntimeException((string) __('sepa.error.item_without_mandate'));
            }

            $builder = $builder->addTransaction(\CommonToolkit\FinancialFormats\Entities\ISO20022\Pain\Type8\DirectDebitTransaction::sepa(
                endToEndId: $this->endToEndId($item),
                amount: $this->money($item),
                mandateId: (string) $mandate->reference,
                mandateDate: new \DateTimeImmutable((string) CarbonImmutable::parse($mandate->signed_on)->toDateString()),
                debtorName: (string) $item->party_name,
                debtorIban: (string) $item->iban,
                debtorBic: ($item->bic ?? '') !== '' ? (string) $item->bic : null,
                remittanceInfo: (string) $item->reference,
            ));
        }

        $document = $builder->endPaymentInstruction()->build();

        return (new \CommonToolkit\FinancialFormats\Generators\ISO20022\Pain\Pain008Generator)->generate($document);
    }

    private function money(PaymentRunItem $item): \CommonToolkit\ValueObjects\Money {
        return \CommonToolkit\ValueObjects\Money::of((string) $item->amount, \CommonToolkit\Enums\CurrencyCode::Euro);
    }

    private function agent(string $bic): ?object {
        $bic = trim($bic);

        return $bic === ''
            ? null
            : \CommonToolkit\FinancialFormats\Entities\ISO20022\Pain\FinancialInstitution::fromBic($bic);
    }

    private function endToEndId(PaymentRunItem $item): string {
        $id = trim((string) ($item->end_to_end_id ?? ''));

        return $id === '' ? 'NOTPROVIDED' : $id;
    }

    private function executionDate(PaymentRun $run): \DateTimeImmutable {
        return new \DateTimeImmutable(CarbonImmutable::parse($run->execution_date)->toDateString());
    }
}
