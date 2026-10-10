<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceMatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\EInvoice;

use App\Enums\Billing\DocumentDirection;
use App\Enums\Invoicing\{IncomingInvoiceMatchKind, IncomingInvoiceRecognition};
use App\Events\Invoicing\IncomingInvoiceAssigned;
use App\Exceptions\CollectiveContactException;
use App\Models\Contacts\ContactBankAccount;
use App\Models\Customer\Customer;
use App\Models\Invoicing\{IncomingEInvoice, InvoiceSenderRule};
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Stammdaten\CollectiveContacts;
use App\Support\Crypto\BlindIndex;
use App\Support\MorphMap;
use CommonToolkit\Helper\Data\BankHelper;
use InvalidArgumentException;
use RuntimeException;

/**
 * Gegenpartei eines Rechnungseingangs (Feature 163, MVP-1108).
 *
 * Automatisch zugeordnet wird nur, wenn genau eine Partei über ein exaktes
 * Merkmal des Belegs trifft (USt-IdNr., Steuernummer, IBAN). Trifft mehr als
 * eine, ist es ein Klärfall. Eine Absenderregel gilt nur, wenn der Beleg
 * selbst niemanden nennt und ihr nicht widerspricht — Versandplattformen
 * schicken Rechnungen vieler Lieferanten unter einer Adresse. Eigene
 * Kennungen zählen nie als Treffer.
 */
class IncomingInvoiceMatcher {
    public function __construct(private readonly IncomingEInvoiceService $invoices) {}

    /**
     * @return array{party: Supplier|Customer|null, kind: IncomingInvoiceMatchKind|null, conflict: list<Supplier|Customer>}
     */
    public function match(IncomingEInvoice $incoming): array {
        $isPurchase = $incoming->direction !== DocumentDirection::Outgoing;
        $own = $this->invoices->ownIdentity($incoming->organization);
        $summary = (array) $incoming->summary;

        $vat = IncomingEInvoiceService::normalizedVat($isPurchase ? $incoming->seller_vat_id : $incoming->buyer_vat_id);
        $taxNumber = $isPurchase ? IncomingEInvoiceService::digitsOf($summary['seller_tax_number'] ?? null) : '';
        $iban = $isPurchase ? BankHelper::normalizeIBAN($incoming->creditor_iban) : null;

        /** @var array<string, list<int>> $hits */
        $hits = [
            IncomingInvoiceMatchKind::VatId->value => $vat !== '' && $vat !== $own['vat'] ? $this->byVat($incoming, $isPurchase, $vat) : [],
            IncomingInvoiceMatchKind::TaxNumber->value => $taxNumber !== '' && $taxNumber !== $own['tax_number'] ? $this->byTaxNumber($incoming, $isPurchase, $taxNumber) : [],
            IncomingInvoiceMatchKind::Iban->value => $iban !== null && ! in_array($iban, $own['ibans'], true) ? $this->byIban($incoming, $isPurchase, $iban) : [],
        ];

        $ids = array_values(array_unique(array_merge(...array_values($hits))));
        if ($ids !== []) {
            $ids = array_values($this->partyQuery($incoming, $isPurchase)->whereKey($ids)->pluck('id')->map(intval(...))->all());
            $hits = array_map(static fn (array $found): array => array_values(array_intersect($found, $ids)), $hits);
        }
        if (count($ids) > 1) {
            return ['party' => null, 'kind' => null, 'conflict' => $this->parties($isPurchase, $ids)];
        }
        if (count($ids) === 1) {
            $kind = IncomingInvoiceMatchKind::from((string) array_key_first(array_filter($hits)));

            return ['party' => $this->parties($isPurchase, $ids)[0] ?? null, 'kind' => $kind, 'conflict' => []];
        }

        $ruleParty = $this->bySenderRule($incoming, $isPurchase);
        if ($ruleParty !== null && ($vat === '' || IncomingEInvoiceService::normalizedVat($ruleParty->vat_id) === '' || IncomingEInvoiceService::normalizedVat($ruleParty->vat_id) === $vat)) {
            return ['party' => $ruleParty, 'kind' => IncomingInvoiceMatchKind::SenderRule, 'conflict' => []];
        }

        return ['party' => null, 'kind' => null, 'conflict' => []];
    }

    /** Ordnet zu, wenn der Abgleich eindeutig ist; widersprüchliche Treffer werden als Abweichung vermerkt. */
    public function autoAssign(IncomingEInvoice $incoming): bool {
        // Ein Klärfall ohne erfasste Werte hat nichts, worüber er treffen könnte.
        $withoutValues = $incoming->recognition === IncomingInvoiceRecognition::None && ! isset(((array) $incoming->summary)['manual']);
        if ($incoming->supplier_id !== null || $incoming->customer_id !== null || $withoutValues || $incoming->transferred_at !== null) {
            return false;
        }

        $outcome = $this->match($incoming);
        if ($outcome['conflict'] !== []) {
            $summary = (array) $incoming->summary;
            $summary['deviations'] = [...(array) ($summary['deviations'] ?? []), (string) __('Widersprüchliche Treffer: :parties — bitte von Hand zuordnen.', [
                'parties' => implode(', ', array_map(static fn (Supplier|Customer $party): string => (string) $party->name, $outcome['conflict'])),
            ])];
            $incoming->forceFill(['summary' => $summary])->save();

            return false;
        }
        if ($outcome['party'] === null || $outcome['kind'] === null) {
            return false;
        }
        // Sonderfall am Sammelkontakt (Reverse Charge u. a.): Ein Mensch entscheidet.
        if ($outcome['party']->is_collective && CollectiveContacts::requiresNamedContact($incoming)) {
            return false;
        }

        $this->assign($incoming, $outcome['party'], $outcome['kind'], null);

        return true;
    }

    /**
     * Gegenpartei setzen. Ein übergebener Beleg bleibt, wie er ist — die
     * Zuordnung im Buchhaltungssystem lässt sich von hier nicht ändern.
     */
    public function assign(IncomingEInvoice $incoming, Supplier|Customer $party, IncomingInvoiceMatchKind $kind, ?User $actor): void {
        if ($incoming->transferred_at !== null) {
            throw new RuntimeException((string) __('Der Eingang ist bereits an die Buchhaltung übergeben — die Zuordnung ist dort zu ändern.'));
        }
        $isSupplier = $party instanceof Supplier;
        if ($isSupplier !== ($incoming->direction !== DocumentDirection::Outgoing)) {
            throw new InvalidArgumentException('Eingangsbelege gehören zu Lieferanten, Ausgangsbelege zu Kunden.');
        }
        if ((int) $party->organization_id !== (int) $incoming->organization_id) {
            throw new InvalidArgumentException('Partei einer anderen Organisation.');
        }
        if ($party->is_collective && CollectiveContacts::requiresNamedContact($incoming)) {
            throw new CollectiveContactException((string) __('Reverse Charge, innergemeinschaftlicher Fall oder Drittland: Dafür braucht es einen echten Firmenkontakt, keinen Sammelkontakt.'));
        }

        $incoming->forceFill([
            'supplier_id' => $isSupplier ? $party->id : null,
            'customer_id' => $isSupplier ? null : $party->id,
            'match_kind' => $kind,
            'matched_user_id' => $actor?->id,
            'matched_at' => now(),
        ])->save();
        $incoming->audit('incoming_einvoice.assigned', [
            'supplier_id' => $incoming->supplier_id,
            'customer_id' => $incoming->customer_id,
            'kind' => $kind->value,
        ]);

        IncomingInvoiceAssigned::dispatch($incoming);
    }

    /** Absender für künftige Eingänge merken (nur auf ausdrücklichen Wunsch). */
    public function rememberSender(IncomingEInvoice $incoming, Supplier|Customer $party, User $actor): ?InvoiceSenderRule {
        $email = InvoiceSenderRule::normalize((string) $incoming->sender_email);
        if ($email === '') {
            return null;
        }
        $isSupplier = $party instanceof Supplier;
        $rule = InvoiceSenderRule::query()->withoutGlobalScopes()->updateOrCreate([
            'organization_id' => $incoming->organization_id,
            'email' => $email,
            'direction' => $isSupplier ? DocumentDirection::Incoming : DocumentDirection::Outgoing,
        ], [
            'supplier_id' => $isSupplier ? $party->id : null,
            'customer_id' => $isSupplier ? null : $party->id,
            'created_by' => $actor->id,
        ]);
        $rule->audit('incoming_einvoice.sender_rule_saved', ['email' => $email]);

        return $rule;
    }

    /** @return list<int> */
    private function byVat(IncomingEInvoice $incoming, bool $isPurchase, string $vat): array {
        return array_values($this->partyQuery($incoming, $isPurchase)->whereNotNull('vat_id')->pluck('vat_id', 'id')
            ->filter(static fn (mixed $value): bool => IncomingEInvoiceService::normalizedVat($value) === $vat)
            ->keys()->map(intval(...))->all());
    }

    /** @return list<int> */
    private function byTaxNumber(IncomingEInvoice $incoming, bool $isPurchase, string $taxNumber): array {
        return array_values($this->partyQuery($incoming, $isPurchase)->whereNotNull('tax_number')->pluck('tax_number', 'id')
            ->filter(static fn (mixed $value): bool => IncomingEInvoiceService::digitsOf($value) === $taxNumber)
            ->keys()->map(intval(...))->all());
    }

    /** @return list<int> */
    private function byIban(IncomingEInvoice $incoming, bool $isPurchase, string $iban): array {
        return array_values(ContactBankAccount::query()->withoutGlobalScopes()
            ->where('organization_id', $incoming->organization_id)
            ->where('accountable_type', MorphMap::alias($isPurchase ? Supplier::class : Customer::class))
            ->whereIn('iban_hash', BlindIndex::ibanCandidates($iban))
            ->distinct()->pluck('accountable_id')->map(intval(...))->all());
    }

    private function bySenderRule(IncomingEInvoice $incoming, bool $isPurchase): Supplier|Customer|null {
        $email = InvoiceSenderRule::normalize((string) $incoming->sender_email);
        if ($email === '') {
            return null;
        }
        $rule = InvoiceSenderRule::query()->withoutGlobalScopes()
            ->where('organization_id', $incoming->organization_id)
            ->where('email', $email)
            ->where('direction', $isPurchase ? DocumentDirection::Incoming : DocumentDirection::Outgoing)
            ->first();
        $id = $isPurchase ? $rule?->supplier_id : $rule?->customer_id;

        return $id === null ? null : ($this->parties($isPurchase, [$id])[0] ?? null);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Supplier>|\Illuminate\Database\Eloquent\Builder<Customer>
     */
    private function partyQuery(IncomingEInvoice $incoming, bool $isPurchase): \Illuminate\Database\Eloquent\Builder {
        $query = $isPurchase ? Supplier::query() : Customer::query();

        // Sammelkontakte (MVP-1109) treffen nie über Merkmale — nur über eine Absenderregel.
        return $query->withoutGlobalScopes()->withoutCollective()->where('organization_id', $incoming->organization_id);
    }

    /**
     * @param  list<int>  $ids
     * @return list<Supplier|Customer>
     */
    private function parties(bool $isPurchase, array $ids): array {
        $query = $isPurchase ? Supplier::query() : Customer::query();

        return array_values($query->withoutGlobalScopes()->whereKey($ids)->orderBy('name')->get()->all());
    }
}
