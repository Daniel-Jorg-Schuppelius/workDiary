<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenItemService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\Finance\{AccountType, OpenItemDirection, OpenItemStatus, SettlementKind};
use App\Models\Accounting\{AccountingAccount, AccountingEntry, AccountingEntryLine, AccountingOpenItem, AccountingOpenItemSettlement};
use App\Models\{Organization, User};
use App\Services\Finance\Datev\DatevBookingConfig;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\{Decimal, Money};
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\Validation\ValidationException;

/**
 * Offene Posten (Feature 125, MVP-674) — EINZIGE Schreibstelle für
 * `accounting_open_items` und ihre Ausgleiche.
 *
 * Der Posten entsteht **aus** der Festbuchung, nicht neben ihr: Beim Posten
 * legt jede Zeile auf einem OPOS-Konto in Erhöhungsrichtung einen Posten an,
 * jede Zeile in Gegenrichtung gleicht einen aus. Beide Wege laufen über
 * `applyEntry()` — es gibt keinen zweiten Ort, an dem sich ein Saldo ändern
 * könnte.
 */
class OpenItemService {
    /**
     * Verarbeitet eine frisch festgeschriebene Buchung: legt offene Posten an
     * und gleicht bestehende aus.
     */
    public function applyEntry(AccountingEntry $entry): void {
        $entry->loadMissing(['lines.account']);
        $organization = $entry->organization;
        if (! $organization instanceof Organization) {
            return;
        }

        // Gegenbuchungen wirken über reverseEntry() auf die Posten — liefe die
        // Storno-Buchung zusätzlich hier durch, entstünde ein zweiter,
        // gegenläufiger Ausgleich.
        if ($entry->reverses_entry_id !== null) {
            return;
        }

        $snapshot = is_array($entry->snapshot) ? $entry->snapshot : [];
        // Nennt der Snapshot ein Ausgleichsziel, ist die Zeile ein Ausgleich —
        // auch wenn sie den Posten wieder erhöht (Rückläufer).
        $settlesTarget = isset($snapshot['settles_source_type']) || isset($snapshot['payment_allocation_id']);

        foreach ($entry->lines as $line) {
            $account = $line->account;
            if ($account === null || ! $account->is_open_item) {
                continue;
            }

            $direction = $this->directionOf($account->type);
            $debit = $line->debit ?? Money::zero($line->currency);
            $credit = $line->credit ?? Money::zero($line->currency);

            $increasing = $direction === OpenItemDirection::Receivable
                ? $debit->isPositive()
                : $credit->isPositive();

            if ($increasing && ! $settlesTarget) {
                $this->register($organization, $entry, $line, $direction);

                continue;
            }

            $amount = $increasing
                ? ($direction === OpenItemDirection::Receivable ? $debit : $credit)
                : ($direction === OpenItemDirection::Receivable ? $credit : $debit);

            if ($amount->isPositive()) {
                $this->settleFromEntry($organization, $entry, $line, $direction, $amount->getAmount(), reopening: $increasing);
            }
        }
    }

    /**
     * Nimmt die Wirkung einer stornierten Buchung zurück: erzeugte Posten
     * werden ausgebucht, geleistete Ausgleiche als Gegenbewegung gespiegelt.
     */
    public function reverseEntry(AccountingEntry $original, AccountingEntry $reversal): void {
        foreach (AccountingOpenItem::query()->where('accounting_entry_id', $original->id)->get() as $item) {
            $this->recordSettlement($item, SettlementKind::Reversal, $item->open_amount?->getAmount() ?? '0.00', $reversal, null, null, true);
        }

        $settlements = AccountingOpenItemSettlement::query()
            ->where('accounting_entry_id', $original->id)
            ->whereNull('reverses_settlement_id')
            ->get();

        foreach ($settlements as $settlement) {
            $item = $settlement->openItem;
            if (! $item instanceof AccountingOpenItem) {
                continue;
            }

            $this->recordSettlement(
                $item,
                SettlementKind::Reversal,
                $settlement->amount?->getAmount() ?? '0.00',
                $reversal,
                $settlement->payment_allocation_id,
                $settlement->id,
            );
        }
    }

    /**
     * Ausgleich von Hand (Skonto, Einbehalt, Ausbuchung) — für Fälle, die
     * kein Zahlungsdatensatz abbildet.
     */
    public function settle(
        AccountingOpenItem $item,
        SettlementKind $kind,
        string $amount,
        ?AccountingEntry $entry = null,
        ?string $note = null,
    ): AccountingOpenItemSettlement {
        // Skonto und Ausbuchung brauchen eine Gegenbuchung im Journal
        // (Sicherheitsscan 2026-08-23, S-38): ohne sie war der Posten
        // ausgeglichen und das Journal unberuehrt — die Forderung verschwand,
        // das Erloes- bzw. Aufwandskonto blieb stehen. Offene Posten sind eine
        // Projektion der Buchhaltung, keine zweite Wahrheit daneben.
        $entry ??= $this->counterEntryFor($item, $kind, $amount, $note);

        if ($entry instanceof AccountingEntry) {
            // Die Festbuchung laeuft durch applyEntry() und legt den Ausgleich
            // dort an, wo alle anderen auch entstehen. Nur wenn sie den Posten
            // nicht getroffen hat (kein Belegbezug, keine Gegenpartei), wird er
            // hier nachgetragen — sonst gaebe es ihn zweimal.
            $fromEntry = AccountingOpenItemSettlement::query()
                ->where('accounting_open_item_id', $item->getKey())
                ->where('accounting_entry_id', $entry->getKey())
                ->latest('id')
                ->first();

            if ($fromEntry instanceof AccountingOpenItemSettlement) {
                return $fromEntry;
            }
        }

        return $this->recordSettlement($item, $kind, $amount, $entry, null, null, false, $note);
    }

    /**
     * Erzeugt die Gegenbuchung zu einem Ausgleich von Hand.
     *
     * Nur fuer Skonto und Ausbuchung: Zahlung, Einbehalt und Rueckbuchung haben
     * ihre Buchung anderswo (Bankabgleich, Zahlungslauf) und bekaemen sie hier
     * ein zweites Mal.
     *
     * Fehlt das Gegenkonto im Kontenplan der Organisation, wird **nicht
     * geraten**: der Ausgleich laeuft dann wie bisher ohne Journalzeile weiter,
     * damit unvollstaendige Kontenpflege ihn nicht blockiert.
     */
    private function counterEntryFor(
        AccountingOpenItem $item,
        SettlementKind $kind,
        string $amount,
        ?string $note,
    ): ?AccountingEntry {
        if (! in_array($kind, [SettlementKind::Discount, SettlementKind::WriteOff], true)) {
            return null;
        }

        $organization = $item->organization;
        $actor = Auth::user();

        if (! $organization instanceof Organization || ! $actor instanceof User) {
            return null;
        }

        $config = DatevBookingConfig::forOrganization($organization);
        $number = $kind === SettlementKind::Discount ? $config->discountAccount : $config->writeOffAccount;

        $counterAccount = AccountingAccount::query()
            ->where('organization_id', $organization->id)
            ->where('number', $number)
            ->first();

        if (! $counterAccount instanceof AccountingAccount) {
            return null;
        }

        // Der Posten wird entlastet: bei einer Forderung steht das Gegenkonto im
        // Soll und der Debitor im Haben, bei einer Verbindlichkeit umgekehrt.
        $receivable = $item->direction === OpenItemDirection::Receivable;
        $itemLine = [
            'accounting_account_id' => $item->accounting_account_id,
            $receivable ? 'credit' : 'debit' => $amount,
            'counterparty_type' => $item->counterparty_type,
            'counterparty_id' => $item->counterparty_id,
        ];
        $counterLine = [
            'accounting_account_id' => $counterAccount->id,
            $receivable ? 'debit' : 'credit' => $amount,
        ];

        // Der Snapshot lenkt settleFromEntry() auf genau diesen Posten und
        // haelt die Ausgleichsart fest — sonst waere jede Gegenbuchung eine
        // Zahlung auf den aeltesten offenen Posten der Gegenpartei.
        $snapshot = ['settlement_kind' => $kind->value];
        if (is_string($item->source_type) && $item->source_id !== null) {
            $snapshot['settles_source_type'] = $item->source_type;
            $snapshot['settles_source_id'] = (int) $item->source_id;
        }

        return $this->journal()->postDirect($organization, [
            'booked_on' => CarbonImmutable::now(Tz::current()),
            'memo' => trim(sprintf(
                '%s zu %s%s',
                $kind === SettlementKind::Discount ? 'Skonto' : 'Ausbuchung',
                (string) ($item->document_reference ?? ('Posten ' . $item->getKey())),
                $note !== null && $note !== '' ? ' — ' . $note : '',
            )),
            'document_reference' => $item->document_reference,
            'source_type' => AccountingOpenItem::class,
            'source_id' => (int) $item->getKey(),
            'source_key' => 'opos-settle:' . $item->getKey() . ':' . $kind->value . ':' . $amount,
            'snapshot' => $snapshot,
            'lines' => $receivable ? [$counterLine, $itemLine] : [$itemLine, $counterLine],
        ], $actor);
    }

    /**
     * JournalService haengt selbst an diesem Dienst (er ruft applyEntry auf) —
     * eine Konstruktor-Abhaengigkeit waere ein Ring. Deshalb erst beim Aufruf.
     */
    private function journal(): JournalService {
        return app(JournalService::class);
    }

    /**
     * Offene Posten einer Richtung mit Altersstruktur.
     *
     * @return array{items: \Illuminate\Database\Eloquent\Collection<int, AccountingOpenItem>, buckets: array<string, string>}
     */
    /**
     * Altersstruktur der offenen Posten.
     *
     * Die Bänder werden **in der Datenbank** summiert: Bei mehreren tausend
     * Posten kostet das Hydrieren der Modelle ein Vielfaches der Abfrage
     * selbst (MVP-683). Die Liste bleibt optional und wird nur geladen, wenn
     * sie jemand anzeigt.
     *
     * @return array{items: Collection<int, AccountingOpenItem>|LengthAwarePaginator<int, AccountingOpenItem>, buckets: array<string, string>}
     */
    public function aging(Organization $organization, OpenItemDirection $direction, ?int $perPage = null, bool $withItems = true): array {
        $today = CarbonImmutable::now(Tz::current())->startOfDay();

        // Grenzen als Datumswerte statt als Datumsarithmetik im SQL — das
        // hält die Abfrage über MariaDB und SQLite hinweg gleich.
        // Grenzen aus der EINEN Bänderungs-Definition (B15/E7).
        $limits = \App\Support\Billing\AgingBuckets::accounting()->limits;
        $bounds = [
            'today' => $today->toDateString(),
            'd30' => $today->subDays($limits[0])->toDateString(),
            'd60' => $today->subDays($limits[1])->toDateString(),
            'd90' => $today->subDays($limits[2])->toDateString(),
        ];

        $base = fn (): Builder => AccountingOpenItem::query()
            ->where('organization_id', $organization->id)
            ->where('direction', $direction->value)
            ->stillOpen();

        $row = $base()
            ->selectRaw('SUM(CASE WHEN due_date IS NULL OR due_date >= ? THEN open_amount ELSE 0 END) AS not_due', [$bounds['today']])
            ->selectRaw('SUM(CASE WHEN due_date < ? AND due_date >= ? THEN open_amount ELSE 0 END) AS d30', [$bounds['today'], $bounds['d30']])
            ->selectRaw('SUM(CASE WHEN due_date < ? AND due_date >= ? THEN open_amount ELSE 0 END) AS d60', [$bounds['d30'], $bounds['d60']])
            ->selectRaw('SUM(CASE WHEN due_date < ? AND due_date >= ? THEN open_amount ELSE 0 END) AS d90', [$bounds['d60'], $bounds['d90']])
            ->selectRaw('SUM(CASE WHEN due_date < ? THEN open_amount ELSE 0 END) AS d90plus', [$bounds['d90']])
            ->first();

        /** @var array<string, mixed> $sums */
        $sums = $row === null ? [] : $row->getAttributes();

        $buckets = [];
        foreach (\App\Support\Billing\AgingBuckets::accounting()->keys as $key) {
            $buckets[$key] = Decimal::of((string) ($sums[$key] ?? 0), 2)->getValue();
        }

        $items = match (true) {
            ! $withItems => new Collection(),
            $perPage !== null => $base()->orderBy('due_date')->paginate($perPage)->withQueryString(),
            default => $base()->get(),
        };

        return ['items' => $items, 'buckets' => $buckets];
    }

    /** Der offene Posten zu einer Belegquelle (Drilldown und Zahlungszuordnung). */
    public function forSource(Organization $organization, Model $source): ?AccountingOpenItem {
        return AccountingOpenItem::query()
            ->where('organization_id', $organization->id)
            ->where('source_type', $source::class)
            ->where('source_id', $source->getKey())
            ->stillOpen()
            ->orderBy('id')
            ->first();
    }

    private function register(Organization $organization, AccountingEntry $entry, AccountingEntryLine $line, OpenItemDirection $direction): AccountingOpenItem {
        $existing = AccountingOpenItem::query()->where('accounting_entry_line_id', $line->id)->first();
        if ($existing instanceof AccountingOpenItem) {
            return $existing;
        }

        $amount = $direction === OpenItemDirection::Receivable
            ? ($line->debit?->getAmount() ?? '0.00')
            : ($line->credit?->getAmount() ?? '0.00');

        $snapshot = is_array($entry->snapshot) ? $entry->snapshot : [];

        return AccountingOpenItem::query()->create([
            'organization_id' => $organization->id,
            'accounting_entry_id' => $entry->id,
            'accounting_entry_line_id' => $line->id,
            'accounting_account_id' => $line->accounting_account_id,
            'direction' => $direction,
            'status' => OpenItemStatus::Open,
            'counterparty_type' => $line->counterparty_type,
            'counterparty_id' => $line->counterparty_id,
            'source_type' => $entry->source_type,
            'source_id' => $entry->source_id,
            'document_reference' => $entry->document_reference,
            'document_date' => ($entry->document_on ?? $entry->booked_on)->toDateString(),
            'due_date' => isset($snapshot['due_date']) ? (string) $snapshot['due_date'] : null,
            'currency' => $entry->currency,
            'original_amount' => $amount,
            'open_amount' => $amount,
        ]);
    }

    /**
     * Ausgleich aus einer Buchung. Das Ziel steht im Quell-Snapshot
     * (`settles_source_*`) — geraten wird nicht: Ein falsch zugeordneter
     * Ausgleich ist schwerer zu finden als ein fehlender.
     */
    private function settleFromEntry(
        Organization $organization,
        AccountingEntry $entry,
        AccountingEntryLine $line,
        OpenItemDirection $direction,
        string $amount,
        bool $reopening = false,
    ): ?AccountingOpenItemSettlement {
        $snapshot = is_array($entry->snapshot) ? $entry->snapshot : [];
        $sourceType = $snapshot['settles_source_type'] ?? null;
        $sourceId = $snapshot['settles_source_id'] ?? null;

        $query = AccountingOpenItem::query()
            ->where('organization_id', $organization->id)
            ->where('direction', $direction->value);

        // Ein Rückläufer trifft einen bereits ausgeglichenen Posten — genau der
        // soll ja wieder aufleben.
        if (! $reopening) {
            $query->stillOpen();
        }

        if (is_string($sourceType) && $sourceId !== null) {
            $query->where('source_type', $sourceType)->where('source_id', (int) $sourceId);
        } elseif ($line->counterparty_type !== null && $line->counterparty_id !== null) {
            // Ohne Belegbezug bleibt nur die Gegenpartei — ältester Posten zuerst.
            $query->where('counterparty_type', $line->counterparty_type)
                ->where('counterparty_id', $line->counterparty_id);
        } else {
            return null;
        }

        $item = $query->orderBy('due_date')->orderBy('id')->first();
        if (! $item instanceof AccountingOpenItem) {
            return null;
        }

        $kind = SettlementKind::tryFrom((string) ($snapshot['settlement_kind'] ?? ''))
            ?? ($reopening ? SettlementKind::Reversal : SettlementKind::Payment);

        return $this->recordSettlement(
            $item,
            $kind,
            $amount,
            $entry,
            isset($snapshot['payment_allocation_id']) ? (int) $snapshot['payment_allocation_id'] : null,
        );
    }

    private function recordSettlement(
        AccountingOpenItem $item,
        SettlementKind $kind,
        string $amount,
        ?AccountingEntry $entry,
        ?int $paymentAllocationId = null,
        ?int $reversesSettlementId = null,
        bool $writeOffRemainder = false,
        ?string $note = null,
    ): AccountingOpenItemSettlement {
        return DB::transaction(function () use ($item, $kind, $amount, $entry, $paymentAllocationId, $reversesSettlementId, $writeOffRemainder, $note): AccountingOpenItemSettlement {
            // Posten INNERHALB der Transaktion gesperrt neu laden
            // (Sicherheitsscan 2026-08-23, S-38): gerechnet wurde bisher gegen
            // das übergebene, ungesperrte Modell. Zwei gleichzeitige
            // Ausgleiche lasen damit beide denselben Restbetrag und
            // überschrieben sich — die Summe der Ausgleiche konnte den Posten
            // übersteigen.
            /** @var AccountingOpenItem $item */
            $item = AccountingOpenItem::query()->lockForUpdate()->findOrFail($item->getKey());

            // Und nie mehr ausgleichen als offen ist. Vorher war der Betrag nur
            // `gt:0`: der Überbetrag wurde still auf 0 geklemmt, der
            // Ausgleichssatz behielt ihn aber — Posten und Sätze gingen
            // auseinander.
            if (! $kind->reopens() && ! $writeOffRemainder) {
                $openNow = $item->open_amount ?? Money::zero($item->currency);
                if (Money::of($amount, $item->currency)->greaterThan($openNow)) {
                    throw ValidationException::withMessages([
                        'amount' => (string) __('accounting.opos.error.amount_exceeds_open', [
                            'open' => (string) $openNow->getAmount(),
                        ]),
                    ]);
                }
            }

            $settlement = AccountingOpenItemSettlement::query()->create([
                'organization_id' => $item->organization_id,
                'accounting_open_item_id' => $item->id,
                'accounting_entry_id' => $entry?->id,
                'kind' => $kind,
                'amount' => $amount,
                'currency' => $item->currency,
                'booked_on' => ($entry instanceof AccountingEntry ? $entry->booked_on : CarbonImmutable::now())->toDateString(),
                'payment_allocation_id' => $paymentAllocationId,
                'reverses_settlement_id' => $reversesSettlementId,
                'note' => $note,
                'created_by' => Auth::id(),
                'created_at' => now(),
            ]);

            $open = $item->open_amount ?? Money::zero($item->currency);
            $delta = Money::of($amount, $item->currency);

            // Eine Rückbuchung öffnet den Posten wieder; alles andere mindert ihn.
            $newOpen = $kind->reopens() && ! $writeOffRemainder
                ? $open->plus($delta)
                : $open->minus($delta);

            if ($newOpen->isNegative()) {
                $newOpen = Money::zero($item->currency);
            }

            $original = $item->original_amount ?? Money::zero($item->currency);
            $status = match (true) {
                $newOpen->isZero() => OpenItemStatus::Settled,
                $newOpen->equals($original) => OpenItemStatus::Open,
                default => OpenItemStatus::PartiallySettled,
            };

            $item->update([
                'open_amount' => $newOpen->getAmount(),
                'status' => $status,
                'settled_at' => $status === OpenItemStatus::Settled ? now() : null,
            ]);

            return $settlement;
        });
    }

    private function directionOf(AccountType $type): OpenItemDirection {
        return $type === AccountType::Asset ? OpenItemDirection::Receivable : OpenItemDirection::Payable;
    }
}
