<?php
/*
 * Created on   : Sat Aug 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InternalTransferService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\{AccountingAccount, AccountingEntry, AccountingTransfer};
use App\Models\{Organization, User};
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Interne Umbuchung zwischen Geldkonten (Feature 125, MVP-681).
 *
 * Bankabhebung und Kasseneinzahlung sind **ein** Geldfluss. Der Vorgang
 * koppelt beide Belege an eine Buchung; die gekoppelten Belege tauchen danach
 * nicht mehr einzeln in der Buchungs-Inbox auf. Ohne diese Klammer stünde der
 * Betrag zweimal im Ergebnis — einmal als Ausgabe, einmal als Einnahme.
 */
class InternalTransferService {
    public function __construct(
        private readonly JournalService $journal,
        private readonly AccountingSovereigntyResolver $sovereignty,
    ) {}

    /**
     * Umbuchung anlegen und sofort festschreiben.
     *
     * @param  array{booked_on: CarbonImmutable, amount: string, from_account: AccountingAccount, to_account: AccountingAccount, note: string, from_source?: ?Model, to_source?: ?Model}  $data
     */
    public function record(Organization $organization, array $data, User $actor): AccountingTransfer {
        $this->sovereignty->assertLocalPostingAllowed($organization, $data['booked_on']);

        $from = $data['from_account'];
        $to = $data['to_account'];

        if ((int) $from->id === (int) $to->id) {
            throw ValidationException::withMessages([
                'to_account' => [(string) __('accounting.transfer.error.same_account')],
            ]);
        }

        // Nur Geldkonten: Eine „Umbuchung" auf ein Erlöskonto wäre eine
        // Erfolgsbuchung, die sich als Geldfluss ausgibt.
        foreach (['from_account' => $from, 'to_account' => $to] as $field => $account) {
            if (! $account->is_bank && ! $account->is_cash && ! $account->is_clearing) {
                throw ValidationException::withMessages([
                    $field => [(string) __('accounting.transfer.error.not_a_money_account', ['account' => $account->displayLabel()])],
                ]);
            }
        }

        if (! Money::of($data['amount'], $this->journal->baseCurrency($organization))->isPositive()) {
            throw ValidationException::withMessages([
                'amount' => [(string) __('accounting.transfer.error.amount_positive')],
            ]);
        }

        $existing = $this->existingFor($organization, $data['from_source'] ?? null, $data['to_source'] ?? null);
        if ($existing instanceof AccountingTransfer) {
            return $existing;
        }

        return DB::transaction(function () use ($organization, $data, $actor, $from, $to): AccountingTransfer {
            $transfer = AccountingTransfer::query()->create([
                'organization_id' => $organization->id,
                'booked_on' => $data['booked_on']->toDateString(),
                'amount' => $data['amount'],
                'currency' => $this->journal->baseCurrency($organization),
                'from_account_id' => $from->id,
                'to_account_id' => $to->id,
                'note' => $data['note'],
                'from_source_type' => ($data['from_source'] ?? null)?->getMorphClass(),
                'from_source_id' => ($data['from_source'] ?? null)?->getKey(),
                'to_source_type' => ($data['to_source'] ?? null)?->getMorphClass(),
                'to_source_id' => ($data['to_source'] ?? null)?->getKey(),
                'created_by' => $actor->id,
            ]);

            $entry = $this->journal->postDirect($organization, [
                'booked_on' => $data['booked_on'],
                'memo' => $data['note'],
                'source_key' => $transfer->sourceKey(),
                'lines' => [
                    ['accounting_account_id' => $to->id, 'debit' => $data['amount'], 'credit' => '0.00'],
                    ['accounting_account_id' => $from->id, 'debit' => '0.00', 'credit' => $data['amount']],
                ],
            ], $actor);

            $transfer->update(['accounting_entry_id' => $entry->id]);

            return $transfer->refresh();
        });
    }

    /**
     * Vorhandener Vorgang zu einer der beiden Quellen.
     *
     * Ein zweiter Versuch für denselben Beleg findet die Umbuchung vor,
     * statt eine zweite anzulegen.
     */
    public function existingFor(Organization $organization, ?Model $from, ?Model $to): ?AccountingTransfer {
        $sources = array_filter([$from, $to]);
        if ($sources === []) {
            return null;
        }

        $query = AccountingTransfer::query()->where('organization_id', $organization->id);
        $query->where(function ($outer) use ($sources): void {
            foreach ($sources as $source) {
                $outer->orWhere(function ($inner) use ($source): void {
                    $inner->where('from_source_type', $source->getMorphClass())->where('from_source_id', $source->getKey());
                })->orWhere(function ($inner) use ($source): void {
                    $inner->where('to_source_type', $source->getMorphClass())->where('to_source_id', $source->getKey());
                });
            }
        });

        return $query->first();
    }

    /**
     * Gekoppelte Belege je Morph-Schlüssel — für die Buchungs-Inbox.
     *
     * @return array<string, AccountingTransfer>
     */
    public function coupledSources(Organization $organization): array {
        $map = [];

        foreach (AccountingTransfer::query()->where('organization_id', $organization->id)->with('entry')->get() as $transfer) {
            foreach ([['from_source_type', 'from_source_id'], ['to_source_type', 'to_source_id']] as [$typeField, $idField]) {
                $type = $transfer->{$typeField};
                $id = $transfer->{$idField};
                if ($type === null || $id === null) {
                    continue;
                }

                $map[$type . ':' . $id] = $transfer;
            }
        }

        return $map;
    }

    /** Buchung des Vorgangs, sofern sie noch aktiv ist. */
    public function entryOf(AccountingTransfer $transfer): ?AccountingEntry {
        return $transfer->entry;
    }
}
