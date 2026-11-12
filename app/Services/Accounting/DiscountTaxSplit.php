<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiscountTaxSplit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\Finance\{OpenItemDirection, TaxCodeDirection};
use App\Models\Accounting\{AccountingAccount, AccountingEntry, AccountingEntryLine, AccountingOpenItem, AccountingTaxCode};
use CommonToolkit\Enums\RoundingMode;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\Money;

/**
 * Teilt einen Skontobetrag in Netto und Steueranteil (§ 17 UStG) — die EINE
 * Stelle für den Ausgleich von Hand und für das Skonto aus dem Bankabgleich.
 *
 * Der Steuerbezug kommt aus der Buchung des offenen Postens: ihre Zeilen mit
 * Steuerkennzeichen nennen Satz, Bemessungsgrundlage und Steuer. Bei mehreren
 * Sätzen wird anteilig nach den Bruttoanteilen geteilt (Restcent nach dem
 * größten Rest). Ohne solche Zeilen — Kleinunternehmer, steuerfrei,
 * Startsalden — gibt es nichts zu berichtigen: Der Betrag bleibt ungeteilt.
 *
 * Steuerschuld des Empfängers (§ 13b UStG, innergemeinschaftlicher Erwerb,
 * MVP-1621): Der Posten ist netto, das Skonto enthält keine Steuer. Es mindert
 * die Bemessungsgrundlage (§ 17 Abs. 1 Satz 5 UStG) — Umsatzsteuer und
 * Vorsteuer sinken um denselben Betrag.
 */
class DiscountTaxSplit {
    /**
     * @param  string  $amount  Skonto brutto
     * @return list<array{tax_code: ?AccountingTaxCode, net: Money, tax: Money, account_id: ?int}> ein Teil je Steuerkennzeichen, der Rest ohne Steuerbezug mit `tax_code` null; bei Steuerschuld des Empfängers ist `net` der ganze Anteil und `account_id` das Aufwandskonto des Belegs
     */
    public function split(AccountingOpenItem $item, string $amount): array {
        $discount = Money::of($amount, $item->currency);
        $zero = Money::zero($item->currency);
        $groups = $this->groups($item);
        if ($groups === [] || ! $discount->isPositive()) {
            return [['tax_code' => null, 'net' => $discount, 'tax' => $zero, 'account_id' => null]];
        }

        $weights = [];
        $taxedGross = $zero;
        foreach ($groups as $key => $group) {
            // Bei Steuerschuld des Empfängers trägt der Posten nur das Netto.
            $gross = $group['tax_code']->isSelfAssessed() ? $group['net'] : $group['net']->plus($group['tax']);
            $weights[$key] = $gross->getAmount();
            $taxedGross = $taxedGross->plus($gross);
        }
        // Was der Posten über die besteuerten Gruppen hinaus trägt, hat keinen Steuerbezug.
        $untaxed = ($item->original_amount ?? $zero)->minus($taxedGross);
        if ($untaxed->isPositive()) {
            $weights['untaxed'] = $untaxed->getAmount();
        }

        $parts = [];
        foreach ($discount->allocateByWeights($weights) as $key => $share) {
            if ($share->isZero()) {
                continue;
            }
            if ($key === 'untaxed') {
                $parts[] = ['tax_code' => null, 'net' => $share, 'tax' => $zero, 'account_id' => null];

                continue;
            }

            $group = $groups[$key];
            $tax = Money::of(NumberHelper::dividePrecise(
                NumberHelper::multiplyPrecise($share->getAmount(), $group['tax']->getAmount(), 6),
                $weights[$key],
                2,
                RoundingMode::HalfUp,
            ), $item->currency);
            $selfAssessed = $group['tax_code']->isSelfAssessed();
            $parts[] = ['tax_code' => $group['tax_code'], 'net' => $selfAssessed ? $share : $share->minus($tax), 'tax' => $tax, 'account_id' => $selfAssessed ? $group['account_id'] : null];
        }

        return $parts;
    }

    /**
     * Gegenzeilen eines Skonto-Ausgleichs in der Zeilenform des JournalService:
     * je Steuerkennzeichen der Nettobetrag auf dem Skontokonto (mit Kennzeichen
     * und minderndem Steuerbetrag) und die Steuer auf dem Steuerkonto. Bei einer
     * Forderung stehen sie im Soll, bei einer Verbindlichkeit im Haben.
     *
     * Steuerschuld des Empfängers: Vorsteuer im Haben, Umsatzsteuer im Soll. Ist
     * das Skontokonto ein DATEV-Automatikkonto (Vorgabe-Kennzeichen), mindert
     * das Skonto das Aufwandskonto des Belegs — die Automatik rechnete sonst
     * Vorsteuer aus einem Nettobetrag heraus.
     *
     * @param  callable(?AccountingTaxCode): int  $discountAccountId  Skontokonto je Kennzeichen (null = ohne Steuerbezug)
     * @return list<array<string, mixed>>
     */
    public function counterLines(AccountingOpenItem $item, string $amount, callable $discountAccountId): array {
        $side = $item->direction === OpenItemDirection::Receivable ? 'debit' : 'credit';
        $other = $side === 'debit' ? 'credit' : 'debit';
        $lines = [];

        foreach ($this->split($item, $amount) as $part) {
            $code = $part['tax_code'];
            $selfAssessed = $code instanceof AccountingTaxCode && $code->isSelfAssessed() && ! $part['tax']->isZero();
            $accountId = $discountAccountId($code);
            if ($selfAssessed && $part['account_id'] !== null
                && AccountingAccount::query()->whereKey($accountId)->whereNotNull('default_tax_code_id')->exists()) {
                $accountId = $part['account_id'];
            }
            $lines[] = [
                'accounting_account_id' => $accountId,
                $side => $part['net']->getAmount(),
                'accounting_tax_code_id' => $part['tax']->isZero() ? null : $code?->id,
                // Negativ: Das Skonto mindert Bemessungsgrundlage und Steuer der Kennziffer.
                'tax_amount' => $part['tax']->isZero() ? null : $part['tax']->negated()->getAmount(),
            ];
            if ($selfAssessed) {
                $lines[] = ['accounting_account_id' => (int) $code->input_tax_account_id, $side => $part['tax']->getAmount(), 'memo' => $code->code];
                $lines[] = ['accounting_account_id' => (int) $code->tax_account_id, $other => $part['tax']->getAmount(), 'memo' => $code->code];
            } elseif ($code instanceof AccountingTaxCode && ! $part['tax']->isZero()) {
                $lines[] = [
                    'accounting_account_id' => (int) $code->tax_account_id,
                    $side => $part['tax']->getAmount(),
                    'memo' => $code->code,
                ];
            }
        }

        return $lines;
    }

    /**
     * Steueranteil eines Skontobetrags — für Hinweise und den Bericht „Buchungsqualität“.
     */
    public function taxOf(AccountingOpenItem $item, string $amount): Money {
        return Money::sum(array_column($this->split($item, $amount), 'tax'), $item->currency);
    }

    /**
     * Steuersätze, nach denen ein Skonto an diesem Posten berichtigt wird; leer = keine Berichtigung.
     *
     * @return list<string>
     */
    public function rates(AccountingOpenItem $item): array {
        return array_values(array_unique(array_map(
            static fn (array $group): string => (string) $group['tax_code']->rate,
            $this->groups($item),
        )));
    }

    /** Trägt der Posten Steuerschuld des Empfängers? Dann mindert ein Skonto Umsatz- und Vorsteuer zugleich. */
    public function selfAssessed(AccountingOpenItem $item): bool {
        foreach ($this->groups($item) as $group) {
            if ($group['tax_code']->isSelfAssessed()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Besteuerte Anteile der Buchung des Postens, je Steuerkennzeichen.
     *
     * @return array<int, array{tax_code: AccountingTaxCode, net: Money, tax: Money, account_id: ?int}>
     */
    private function groups(AccountingOpenItem $item): array {
        $entry = $item->entry;
        if (! $entry instanceof AccountingEntry) {
            return [];
        }
        $entry->loadMissing('lines.taxCode');
        $wanted = $item->direction === OpenItemDirection::Receivable ? [TaxCodeDirection::Output] : [TaxCodeDirection::Input, TaxCodeDirection::SelfAssessed];
        $zero = Money::zero($item->currency);

        /** @var array<int, array{tax_code: AccountingTaxCode, net: Money, tax: Money, net_form: bool, account_id: ?int}> $groups */
        $groups = [];
        foreach ($entry->lines as $line) {
            $code = $line->taxCode;
            if (! $code instanceof AccountingTaxCode
                || ! in_array($code->direction, $wanted, true)
                || $code->tax_account_id === null
                || ($code->isSelfAssessed() && $code->input_tax_account_id === null)
                || NumberHelper::isZeroPrecise((string) $code->rate)) {
                continue;
            }

            $groups[$code->id] ??= ['tax_code' => $code, 'net' => $zero, 'tax' => $zero, 'net_form' => false, 'account_id' => null];
            // Betrag in Wirkungsrichtung: Umsatzsteuer im Haben, Vorsteuer und Eigenberechnung im Soll.
            $amount = $code->direction === TaxCodeDirection::Output ? $line->signedAmount()->negated() : $line->signedAmount();
            $isTaxLine = $code->isTaxAccount((int) $line->accounting_account_id);

            if (! $isTaxLine) {
                // Kennzeichen an der Nettozeile: Zeile = Grundlage, `tax_amount` = Steuer.
                if (! $groups[$code->id]['net_form']) {
                    $groups[$code->id] = ['tax_code' => $code, 'net' => $zero, 'tax' => $zero, 'net_form' => true, 'account_id' => (int) $line->accounting_account_id];
                }
                $groups[$code->id]['net'] = $groups[$code->id]['net']->plus($amount);
                $groups[$code->id]['tax'] = $groups[$code->id]['tax']->plus($line->tax_amount ?? $zero);
            } elseif (! $groups[$code->id]['net_form']) {
                // Ältere Form: Kennzeichen an der Steuerzeile, die Grundlage folgt aus dem Satz.
                $groups[$code->id]['tax'] = $groups[$code->id]['tax']->plus($line->tax_amount ?? $amount);
            }
        }

        $result = [];
        foreach ($groups as $id => $group) {
            if (! $group['tax']->isPositive()) {
                continue;
            }
            $net = $group['net_form']
                ? $group['net']
                : $group['tax']->times(100)->dividedBy((string) $group['tax_code']->rate);
            $result[$id] = ['tax_code' => $group['tax_code'], 'net' => $net, 'tax' => $group['tax'], 'account_id' => $group['account_id']];
        }

        return $result;
    }

    /** Hat die Buchung eine Zeile mit Steuerkennzeichen? Dann trägt ein Skonto-Ausgleich seine Berichtigung schon. */
    public function carriesTax(AccountingEntry $entry): bool {
        return $entry->lines->contains(fn (AccountingEntryLine $line): bool => $line->accounting_tax_code_id !== null);
    }
}
