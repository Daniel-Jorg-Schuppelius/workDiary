<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PostingSourceRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Posting;

use App\Enums\Finance\PostingSourceKind;
use App\Services\Accounting\Posting\Adapters\{CashEntryAdapter, ExpenseAdapter, IncomingInvoiceAdapter, PaymentAdapter, SalesInvoiceAdapter};

/**
 * Registry der Quellenadapter (Feature 125, MVP-673).
 *
 * Eine Quellenart, ein Adapter — die Zuordnung steht hier und nicht verteilt
 * in der Inbox, damit ein neuer Adapter genau eine Stelle braucht.
 */
class PostingSourceRegistry {
    /** @var array<string, PostingSourceAdapter>|null */
    private ?array $adapters = null;

    public function __construct(
        private readonly SalesInvoiceAdapter $salesInvoices,
        private readonly IncomingInvoiceAdapter $incomingInvoices,
        private readonly ExpenseAdapter $expenses,
        private readonly CashEntryAdapter $cashEntries,
        private readonly PaymentAdapter $payments,
    ) {}

    /** @return array<string, PostingSourceAdapter> */
    public function all(): array {
        return $this->adapters ??= [
            PostingSourceKind::SalesInvoice->value => $this->salesInvoices,
            PostingSourceKind::IncomingInvoice->value => $this->incomingInvoices,
            PostingSourceKind::Expense->value => $this->expenses,
            PostingSourceKind::CashEntry->value => $this->cashEntries,
            PostingSourceKind::Payment->value => $this->payments,
        ];
    }

    public function for(PostingSourceKind $kind): PostingSourceAdapter {
        return $this->all()[$kind->value];
    }
}
