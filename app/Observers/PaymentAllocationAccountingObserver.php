<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaymentAllocationAccountingObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Observers;

use App\Enums\Finance\{AccountingEntryStatus, PostingSourceKind};
use App\Models\Accounting\AccountingEntry;
use App\Models\Finance\PaymentAllocation;
use App\Models\User;
use App\Services\Accounting\JournalService;
use Illuminate\Support\Facades\Auth;

/**
 * Hält den Buchungskern mit dem Zahlungsabgleich in Takt (Feature 125,
 * MVP-674).
 *
 * Wird eine Zuordnung aufgehoben ({@see \App\Services\Finance\ReconciliationService::unmatch()}
 * löscht sie weich), darf die zugehörige Festbuchung **nicht** verschwinden —
 * sie bekommt eine Gegenbuchung. Der Observer sitzt hier statt im
 * ReconciliationService, damit der Zahlungsabgleich nichts über die
 * Buchhaltung wissen muss: Ohne aktiviertes Hauptbuch existiert schlicht keine
 * Buchung, und es passiert nichts.
 */
class PaymentAllocationAccountingObserver {
    public function __construct(private readonly JournalService $journal) {}

    public function deleted(PaymentAllocation $allocation): void {
        $entry = AccountingEntry::query()
            ->where('organization_id', $allocation->organization_id)
            ->where('source_key', PostingSourceKind::Payment->keyPrefix() . ':' . $allocation->id)
            ->first();

        if (! $entry instanceof AccountingEntry) {
            return;
        }

        $actor = Auth::user();
        if (! $actor instanceof User) {
            return;
        }

        if ($entry->status->isMutable()) {
            // Noch nicht festgeschrieben: Der Entwurf verliert seine Grundlage.
            $entry->delete();

            return;
        }

        if ($entry->status !== AccountingEntryStatus::Posted) {
            return;
        }

        $this->journal->reverse(
            $entry,
            (string) __('accounting.inbox.reversal_reason.unmatched'),
            $actor,
        );
    }
}
