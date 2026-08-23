<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PostingProposalLine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Posting;

use App\Enums\Finance\PostingAccountRole;
use App\Models\Accounting\AccountingAccount;

/**
 * Eine Zeile eines Buchungsvorschlags (Feature 125, MVP-673).
 *
 * Trägt die Rolle mit, aus der sie entstanden ist — der Vorschlag muss
 * erklären können, WARUM dieses Konto vorgeschlagen wird, nicht nur DASS.
 */
final class PostingProposalLine {
    public function __construct(
        public readonly PostingAccountRole $role,
        public readonly AccountingAccount $account,
        public readonly string $debit,
        public readonly string $credit,
        public readonly ?int $taxCodeId = null,
        public readonly ?string $taxAmount = null,
        public readonly ?string $memo = null,
        public readonly ?string $counterpartyType = null,
        public readonly ?int $counterpartyId = null,
        public readonly ?string $ruleVersion = null,
    ) {}

    /**
     * Form für {@see \App\Services\Accounting\JournalService::draft()}.
     *
     * @return array<string, mixed>
     */
    public function toLineData(): array {
        return [
            'accounting_account_id' => $this->account->id,
            'debit' => $this->debit,
            'credit' => $this->credit,
            'accounting_tax_code_id' => $this->taxCodeId,
            'tax_amount' => $this->taxAmount,
            'counterparty_type' => $this->counterpartyType,
            'counterparty_id' => $this->counterpartyId,
            'memo' => $this->memo,
        ];
    }

    /**
     * Erklärzeile für die Inbox.
     *
     * @return array<string, mixed>
     */
    public function toExplanation(): array {
        return [
            'role' => $this->role->value,
            'account' => $this->account->displayLabel(),
            'debit' => $this->debit,
            'credit' => $this->credit,
            'tax_amount' => $this->taxAmount,
            'rule' => $this->ruleVersion,
        ];
    }
}
