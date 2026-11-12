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

use App\Enums\Finance\{AccountType, AccountingEntryStatus, DirectBookingKind, OpenItemDirection, OpenItemStatus, SettlementKind};
use App\Models\Accounting\{AccountingAccount, AccountingEntry, AccountingEntryLine, AccountingOpenItem, AccountingOpenItemSettlement, AccountingTaxCode};
use App\Models\Invoicing\Invoice;
use App\Models\Platform\{Organization, User};
use App\Services\Finance\Datev\DatevBookingConfig;
use App\Services\Invoicing\DunningService;
use App\Support\{MorphMap, Tz};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\{NumberHelper, StringHelper};
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
        $settlesTarget = isset($snapshot['settles_source_type']) || isset($snapshot['payment_allocation_id']) || isset($snapshot['settles_open_item_id']);

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
     *
     * Null, solange die Gegenbuchung bei aktivem Vier-Augen-Prinzip als
     * Entwurf auf die Freigabe wartet (E9): Der Ausgleich entsteht dann beim
     * Festschreiben in applyEntry(), bis dahin bleibt der Posten offen.
     *
     * @throws ValidationException wenn schon ein Entwurf wartet oder der Betrag den offenen Rest übersteigt
     */
    public function settle(
        AccountingOpenItem $item,
        SettlementKind $kind,
        string $amount,
        ?AccountingEntry $entry = null,
        ?string $note = null,
    ): ?AccountingOpenItemSettlement {
        return DB::transaction(function () use ($item, $kind, $amount, $entry, $note): ?AccountingOpenItemSettlement {
            // Gesperrt: zwei gleichzeitige Ausgleiche sähen sonst beide keinen
            // wartenden Entwurf und legten zwei an.
            /** @var AccountingOpenItem $item */
            $item = AccountingOpenItem::query()->lockForUpdate()->findOrFail($item->getKey());

            // Ein wartender Entwurf ist schon ein Ausgleich — ein zweiter von
            // Hand würde den Posten doppelt mindern, sobald beide freigegeben sind.
            if (($this->pendingDrafts([$item])[$item->id] ?? null) instanceof AccountingEntry) {
                throw ValidationException::withMessages([
                    'amount' => (string) __('accounting.open_items.error.draft_pending'),
                ]);
            }
            $this->assertWithinOpen($item, $kind, $amount);

            // Skonto und Ausbuchung brauchen eine Gegenbuchung im Journal
            // (Sicherheitsscan 2026-08-23, S-38): ohne sie war der Posten
            // ausgeglichen und das Journal unberuehrt — die Forderung verschwand,
            // das Erloes- bzw. Aufwandskonto blieb stehen. Offene Posten sind eine
            // Projektion der Buchhaltung, keine zweite Wahrheit daneben.
            $entry ??= $this->counterEntryFor($item, $kind, $amount, $note);

            if ($entry instanceof AccountingEntry) {
                if (! $entry->status->isPosted()) {
                    return null;
                }

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
        });
    }

    /**
     * Posten als strittig kennzeichnen (MVP-1559). Er bleibt offen und
     * ausgleichbar, wird aber nicht gemahnt: An der Ausgangsrechnung entsteht
     * eine Mahnsperre, sofern dort noch keine steht. Der Nachweis liegt im
     * Audit-Log des Postens.
     *
     * @throws ValidationException
     */
    public function markDisputed(AccountingOpenItem $item, string $reason, User $actor): AccountingOpenItem {
        return DB::transaction(function () use ($item, $reason, $actor): AccountingOpenItem {
            /** @var AccountingOpenItem $item */
            $item = AccountingOpenItem::query()->lockForUpdate()->findOrFail($item->getKey());
            if (! in_array($item->status, [OpenItemStatus::Open, OpenItemStatus::PartiallySettled], true)) {
                throw ValidationException::withMessages([
                    'reason' => (string) __('accounting.dispute.error.not_open'),
                ]);
            }

            $reason = StringHelper::truncate(trim($reason), 255, '…');
            $item->update(['status' => OpenItemStatus::Disputed, 'disputed_at' => now(), 'dispute_note' => $reason]);
            $item->audit('accounting.open_item_disputed', ['by' => $actor->id, 'reason' => $reason]);

            $invoice = $this->receivableInvoice($item);
            if ($invoice instanceof Invoice && ! $invoice->isDunningBlocked()) {
                app(DunningService::class)->block($invoice, $this->dunningReason($reason), $actor);
            }

            return $item->refresh();
        });
    }

    /**
     * Kennzeichen „strittig“ zurücknehmen: Der Posten gilt wieder als offen
     * bzw. teilweise ausgeglichen. Die Mahnsperre fällt nur, wenn sie aus dem
     * Kennzeichen stammt — eine von Hand gesetzte bleibt.
     *
     * @throws ValidationException
     */
    public function withdrawDispute(AccountingOpenItem $item, User $actor): AccountingOpenItem {
        return DB::transaction(function () use ($item, $actor): AccountingOpenItem {
            /** @var AccountingOpenItem $item */
            $item = AccountingOpenItem::query()->lockForUpdate()->findOrFail($item->getKey());
            if ($item->status !== OpenItemStatus::Disputed) {
                throw ValidationException::withMessages([
                    'status' => (string) __('accounting.dispute.error.not_disputed'),
                ]);
            }

            $reason = (string) $item->dispute_note;
            $open = $item->open_amount ?? Money::zero($item->currency);
            $item->update([
                'status' => $open->equals($item->original_amount ?? $open) ? OpenItemStatus::Open : OpenItemStatus::PartiallySettled,
                'disputed_at' => null,
                'dispute_note' => null,
            ]);
            $item->audit('accounting.open_item_dispute_withdrawn', ['by' => $actor->id, 'reason' => $reason]);

            $invoice = $this->receivableInvoice($item);
            if ($invoice instanceof Invoice && $invoice->isDunningBlocked() && $invoice->dunning_block_reason === $this->dunningReason($reason)) {
                app(DunningService::class)->unblock($invoice, $actor);
            }

            return $item->refresh();
        });
    }

    /** Ausgangsrechnung hinter einer Forderung — nur sie kennt eine Mahnsperre. */
    private function receivableInvoice(AccountingOpenItem $item): ?Invoice {
        if ($item->direction !== OpenItemDirection::Receivable || ! MorphMap::is($item->source_type, Invoice::class)) {
            return null;
        }

        return Invoice::query()->where('organization_id', $item->organization_id)->find($item->source_id);
    }

    private function dunningReason(string $reason): string {
        return StringHelper::truncate((string) __('accounting.dispute.dunning_reason', ['reason' => $reason]), 255, '…');
    }

    /**
     * Gegenbuchungen von Hand, die als Entwurf auf die Freigabe warten (E9) —
     * je Posten höchstens eine.
     *
     * @param  iterable<AccountingOpenItem>  $items
     * @return array<int, AccountingEntry> nach Posten-ID
     */
    public function pendingDrafts(iterable $items): array {
        $ids = [];
        $organizations = [];
        foreach ($items as $item) {
            $ids[] = (int) $item->getKey();
            $organizations[] = (int) $item->organization_id;
        }
        if ($ids === []) {
            return [];
        }

        return AccountingEntry::query()
            ->whereIn('organization_id', array_unique($organizations))
            ->where('source_type', MorphMap::alias(AccountingOpenItem::class))
            ->whereIn('source_id', $ids)
            ->whereIn('status', [AccountingEntryStatus::Draft->value, AccountingEntryStatus::Ready->value])
            ->get()
            ->keyBy(fn (AccountingEntry $entry): int => (int) $entry->source_id)
            ->all();
    }

    /**
     * Skonto- und Ausbuchungsausgleiche ohne Journalbuchung — Altbestand aus
     * der Zeit, als ein fehlendes Gegenkonto den Posten still schloss
     * (MVP-1555). Nur lesend: Die Korrektur bucht die Buchhaltung von Hand.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, AccountingOpenItemSettlement>
     */
    public function settlementsWithoutEntry(Organization $organization): \Illuminate\Database\Eloquent\Collection {
        return AccountingOpenItemSettlement::query()
            ->where('organization_id', $organization->id)
            ->whereIn('kind', [SettlementKind::Discount->value, SettlementKind::WriteOff->value])
            ->whereNull('accounting_entry_id')
            ->whereNull('reverses_settlement_id')
            // Ein zurückgenommener Ausgleich hat seine Wirkung schon verloren.
            ->whereNotIn('id', AccountingOpenItemSettlement::query()
                ->where('organization_id', $organization->id)
                ->whereNotNull('reverses_settlement_id')
                ->select('reverses_settlement_id'))
            ->with('openItem')
            ->orderBy('booked_on')
            ->orderBy('id')
            ->get();
    }

    /**
     * Festgeschriebene Skonto-Ausgleiche ohne Steuerberichtigung, obwohl der
     * Posten einen Steuerbezug hat — Bestand aus der Zeit, als das Skonto voll
     * aufs Skontokonto ging. Nur lesend: Über Korrekturbuchungen entscheidet die
     * Steuerberatung.
     *
     * @return list<array{settlement: AccountingOpenItemSettlement, tax: Money}>
     */
    public function discountsWithoutTaxCorrection(Organization $organization): array {
        $split = app(DiscountTaxSplit::class);
        $settlements = AccountingOpenItemSettlement::query()
            ->where('organization_id', $organization->id)
            ->where('kind', SettlementKind::Discount->value)
            ->whereNotNull('accounting_entry_id')
            ->whereNull('reverses_settlement_id')
            ->whereNotIn('id', AccountingOpenItemSettlement::query()
                ->where('organization_id', $organization->id)
                ->whereNotNull('reverses_settlement_id')
                ->select('reverses_settlement_id'))
            ->whereHas('entry', fn (Builder $query) => $query->where('status', AccountingEntryStatus::Posted->value))
            ->with(['entry.lines', 'openItem.entry.lines.taxCode'])
            ->orderBy('booked_on')
            ->orderBy('id')
            ->get();

        $found = [];
        foreach ($settlements as $settlement) {
            $item = $settlement->openItem;
            $entry = $settlement->entry;
            if (! $item instanceof AccountingOpenItem || ! $entry instanceof AccountingEntry || $split->carriesTax($entry)) {
                continue;
            }

            $tax = $split->taxOf($item, $settlement->amount?->getAmount() ?? '0.00');
            if ($tax->isPositive()) {
                $found[] = ['settlement' => $settlement, 'tax' => $tax];
            }
        }

        return $found;
    }

    /**
     * Ausgleichsarten, deren Gegenkonto im Kontenplan fehlt (Art ⇒ Kontonummer) —
     * der Dialog warnt, bevor der Ausgleich daran scheitert.
     *
     * @return array<string, string>
     */
    public function missingCounterAccounts(Organization $organization, OpenItemDirection $direction = OpenItemDirection::Receivable): array {
        $wanted = [
            SettlementKind::Discount->value => $this->counterAccountNumber($organization, SettlementKind::Discount, $direction),
            SettlementKind::WriteOff->value => $this->counterAccountNumber($organization, SettlementKind::WriteOff, $direction),
        ];
        $existing = AccountingAccount::query()
            ->where('organization_id', $organization->id)
            ->whereIn('number', array_values($wanted))
            ->pluck('number')
            ->all();

        return array_filter($wanted, static fn (string $number): bool => ! in_array($number, $existing, true));
    }

    /**
     * Erzeugt die Gegenbuchung zu einem Ausgleich von Hand.
     *
     * Nur fuer Skonto und Ausbuchung: Zahlung, Einbehalt und Rueckbuchung haben
     * ihre Buchung anderswo (Bankabgleich, Zahlungslauf) und bekaemen sie hier
     * ein zweites Mal.
     *
     * Fehlt das Gegenkonto im Kontenplan der Organisation, wird der Ausgleich
     * abgewiesen (MVP-1555): Ein Posten, der ohne Buchung schliesst, laesst
     * OPOS-Liste und Summen-/Saldenliste still auseinanderlaufen.
     *
     * @throws ValidationException
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

        $number = $this->counterAccountNumber($organization, $kind, $item->direction);

        $counterAccount = $organization instanceof Organization
            ? AccountingAccount::query()->where('organization_id', $organization->id)->where('number', $number)->first()
            : null;

        if (! $organization instanceof Organization || ! $actor instanceof User || ! $counterAccount instanceof AccountingAccount) {
            throw ValidationException::withMessages([
                'kind' => (string) __('accounting.open_items.error.counter_account_missing', [
                    'kind' => $kind->label(),
                    'account' => $number,
                ]),
            ]);
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
        // Skonto mindert auch die Steuer (§ 17 UStG): Netto aufs Skontokonto, Steueranteil aufs Steuerkonto.
        $counterLines = $kind === SettlementKind::Discount
            ? app(DiscountTaxSplit::class)->counterLines($item, $amount, fn (?AccountingTaxCode $code): int => $this->discountAccountId($organization, $item->direction, $counterAccount, $code))
            : [['accounting_account_id' => $counterAccount->id, $receivable ? 'debit' : 'credit' => $amount]];

        // Der Snapshot lenkt settleFromEntry() auf genau diesen Posten und
        // haelt die Ausgleichsart fest — sonst waere jede Gegenbuchung eine
        // Zahlung auf den aeltesten offenen Posten der Gegenpartei. Wartet die
        // Buchung auf die Freigabe, ist er der einzige Weg zum Posten.
        $snapshot = ['settlement_kind' => $kind->value, 'settles_open_item_id' => (int) $item->getKey()];
        if (is_string($item->source_type) && $item->source_id !== null) {
            $snapshot['settles_source_type'] = $item->source_type;
            $snapshot['settles_source_id'] = (int) $item->source_id;
        }

        return $this->journal()->postDirectOrSubmit($organization, [
            'booked_on' => CarbonImmutable::now(Tz::current()),
            'memo' => trim(sprintf(
                '%s zu %s%s',
                $kind === SettlementKind::Discount ? 'Skonto' : 'Ausbuchung',
                (string) ($item->document_reference ?? ('Posten ' . $item->getKey())),
                $note !== null && $note !== '' ? ' — ' . $note : '',
            )),
            'document_reference' => $item->document_reference,
            'source_type' => MorphMap::alias(AccountingOpenItem::class),
            'source_id' => (int) $item->getKey(),
            'source_key' => 'opos-settle:' . $item->getKey() . ':' . $kind->value . ':' . $amount,
            'snapshot' => $snapshot,
            'lines' => $receivable ? [...$counterLines, $itemLine] : [$itemLine, ...$counterLines],
        ], $actor, DirectBookingKind::OpenItemSettlement);
    }

    /**
     * Skontokonto je Steuersatz: Die Kontenrahmen trennen die Skontokonten nach
     * Satz. Solange die Organisation die Vorgabe (19 %) nutzt, geht der
     * ermäßigte Satz auf dessen Konto, sofern es im Kontenplan steht; ein eigens
     * eingestelltes Konto gilt für alle Sätze — die Kennziffer hängt am
     * Steuerkennzeichen der Zeile, nicht am Konto.
     */
    private function discountAccountId(Organization $organization, OpenItemDirection $direction, AccountingAccount $configured, ?AccountingTaxCode $code): int {
        $skr = DatevBookingConfig::forOrganization($organization)->skr;
        $receivable = $direction === OpenItemDirection::Receivable;
        $default = $receivable ? $skr->defaultDiscountAccount() : $skr->defaultReceivedDiscountAccount();

        if ($code instanceof AccountingTaxCode && $configured->number === $default && NumberHelper::comparePrecise((string) $code->rate, '7.00', 2) === 0) {
            $reduced = AccountingAccount::query()
                ->where('organization_id', $organization->id)
                ->where('number', $skr->reducedRateDiscountAccounts()[$receivable ? 'granted' : 'received'])
                ->active()
                ->value('id');
            if ($reduced !== null) {
                return (int) $reduced;
            }
        }

        return (int) $configured->id;
    }

    /**
     * Gegenkonto je Richtung: Gewährtes Skonto und Forderungsverlust gehören
     * zur Forderung; an der Verbindlichkeit sind es erhaltenes Skonto und ein
     * sonstiger Ertrag.
     */
    private function counterAccountNumber(?Organization $organization, SettlementKind $kind, OpenItemDirection $direction): string {
        $config = DatevBookingConfig::forOrganization($organization);

        return match (true) {
            $direction === OpenItemDirection::Receivable => $kind === SettlementKind::Discount ? $config->discountAccount : $config->writeOffAccount,
            default => $kind === SettlementKind::Discount ? $config->receivedDiscountAccount : $config->payableWriteOffAccount,
        };
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
    public function aging(Organization $organization, OpenItemDirection $direction, ?int $perPage = null, bool $withItems = true, ?OpenItemStatus $status = null): array {
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

        // Der Statusfilter gilt der Liste; die Bänder zeigen weiter alle offenen Posten.
        $list = fn (): Builder => $status instanceof OpenItemStatus ? $base()->where('status', $status->value) : $base();
        $items = match (true) {
            ! $withItems => new Collection(),
            $perPage !== null => $list()->orderBy('due_date')->paginate($perPage)->withQueryString(),
            default => $list()->get(),
        };

        return ['items' => $items, 'buckets' => $buckets];
    }

    /** Der offene Posten zu einer Belegquelle (Drilldown und Zahlungszuordnung). */
    public function forSource(Organization $organization, Model $source): ?AccountingOpenItem {
        return AccountingOpenItem::query()
            ->where('organization_id', $organization->id)
            ->where('source_type', $source->getMorphClass())
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
        $itemId = $snapshot['settles_open_item_id'] ?? null;

        $query = AccountingOpenItem::query()
            ->where('organization_id', $organization->id)
            ->where('direction', $direction->value);

        // Ein Rückläufer trifft einen bereits ausgeglichenen Posten — genau der
        // soll ja wieder aufleben. Ein benannter Posten wird nicht gefiltert:
        // Ist er inzwischen ausgeglichen, scheitert die Freigabe an der
        // Betragsprüfung, statt still ohne Ausgleich zu buchen.
        if (! $reopening && $itemId === null) {
            $query->stillOpen();
        }

        if ($itemId !== null) {
            $query->whereKey((int) $itemId);
        } elseif (is_string($sourceType) && $sourceId !== null) {
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
            if (! $writeOffRemainder) {
                $this->assertWithinOpen($item, $kind, $amount);
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
                // Strittig bleibt strittig, solange ein Rest offen ist (MVP-1559).
                $item->status === OpenItemStatus::Disputed => OpenItemStatus::Disputed,
                $newOpen->equals($original) => OpenItemStatus::Open,
                default => OpenItemStatus::PartiallySettled,
            };

            $item->update([
                'open_amount' => $newOpen->getAmount(),
                'status' => $status,
                'settled_at' => $status === OpenItemStatus::Settled ? now() : null,
            ] + ($status === OpenItemStatus::Settled ? ['disputed_at' => null, 'dispute_note' => null] : []));

            return $settlement;
        });
    }

    /** @throws ValidationException */
    private function assertWithinOpen(AccountingOpenItem $item, SettlementKind $kind, string $amount): void {
        if ($kind->reopens()) {
            return;
        }

        $openNow = $item->open_amount ?? Money::zero($item->currency);
        if (Money::of($amount, $item->currency)->greaterThan($openNow)) {
            throw ValidationException::withMessages([
                'amount' => (string) __('accounting.opos.error.amount_exceeds_open', [
                    'open' => (string) $openNow->getAmount(),
                ]),
            ]);
        }
    }

    private function directionOf(AccountType $type): OpenItemDirection {
        return $type === AccountType::Asset ? OpenItemDirection::Receivable : OpenItemDirection::Payable;
    }
}
