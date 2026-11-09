<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskIncomingInvoiceTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk\Services;

use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Enums\Finance\AccountingVoucherState;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\{IncomingEInvoice, IncomingEInvoiceTransfer};
use App\Models\Platform\Organization;
use App\Models\Supplier\Supplier;
use App\Plugins\SevDesk\Api\{SevDeskClient, SevDeskClientFactory};
use App\Plugins\SevDesk\{SevDeskConfig, SevDeskPlugin};
use App\Services\Finance\Accounting\ContactPushService;
use App\Services\Finance\Accounting\Vouchers\{MirroredVoucher, VoucherMirror};
use App\Services\Invoicing\Contracts\IncomingInvoiceTransferTarget;
use App\Services\Invoicing\Dto\IncomingInvoiceTransferResult;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Rechnungseingang → sevDesk (Feature 163, MVP-1507).
 *
 * Der Beleg geht als Entwurf (Status 50) hinaus, die Buchhaltung bestätigt in
 * sevDesk. Die Datei lädt `uploadTempFile` hoch (XRechnung und ZUGFeRD erst
 * ab Buchhaltung 2.0), `saveVoucher` legt Beleg, Positionen und Datei in einem
 * Aufruf an. Liegt ein Beleg mit derselben Nummer schon dort, wird nur
 * verknüpft. Automatisch geht nur, was sevDesk ohne Rückfrage bucht: Euro,
 * Regelsteuer mit 0, 7 oder 19 % und ein Buchungskonto je Richtung; alles
 * andere wartet mit Hinweis, bis die Buchhaltung den Beleg in sevDesk erfasst —
 * die nächste Übergabe verknüpft ihn dann über die Rechnungsnummer.
 *
 * Richtung laut Doku („Attributes of a voucher“): `creditDebit` C = Ausgabe,
 * D = Einnahme.
 */
final class SevDeskIncomingInvoiceTarget implements IncomingInvoiceTransferTarget {
    /** Steuersätze, die sevDesk-Belege mit Regelsteuer annehmen (Doku „Tax Rules“). */
    private const array TAX_RATES = [0.0, 7.0, 19.0];

    /** Steuerkategorien (UNCL 5305) der Regelsteuer; Reverse Charge, EU und Ausfuhr bucht die Buchhaltung selbst. */
    private const array REGULAR_CATEGORIES = ['', 'S', 'Z'];

    /** Steuerregel 2.0: Umsatzsteuerpflichtige Umsätze bzw. Vorsteuerabziehbare Aufwendungen. */
    private const int TAX_RULE_REVENUE = 1;

    private const int TAX_RULE_EXPENSE = 9;

    public function key(): string {
        return SevDeskPlugin::ID;
    }

    public function label(): string {
        return (string) __('sevdesk::incoming.label');
    }

    public function isEnabled(Organization $organization): bool {
        $config = SevDeskConfig::resolve((int) $organization->id);

        return $config['enabled'] && $config['incoming_transfer'] && app(SevDeskClientFactory::class)->isConfigured((int) $organization->id);
    }

    public function appliesTo(IncomingEInvoice $incoming): bool {
        return true;
    }

    public function transfer(IncomingEInvoice $incoming, IncomingEInvoiceTransfer $journal): IncomingInvoiceTransferResult {
        if ($journal->external_id !== null) {
            // saveVoucher hängt die Datei im selben Aufruf an: mit vergebener ID ist nichts nachzuholen.
            return IncomingInvoiceTransferResult::transferred((string) $journal->external_id, $incoming->invoice_number);
        }

        $organizationId = (int) $incoming->organization_id;
        $config = SevDeskConfig::resolve($organizationId);
        $client = app(SevDeskClientFactory::class)->for($organizationId);
        $creditDebit = $incoming->direction === DocumentDirection::Outgoing ? 'D' : 'C';
        $party = $incoming->counterparty();
        if ($party === null) {
            return IncomingInvoiceTransferResult::waiting((string) __('sevdesk::incoming.no_party'));
        }
        $contactId = $party->is_collective ? null : $this->linkedContactId($incoming, $party);

        $existing = $this->existing($client, $incoming, $party, $contactId, $creditDebit);
        if ($existing !== null) {
            if (! $this->sameAmount((string) ($existing['sumGross'] ?? ''), $incoming)) {
                return IncomingInvoiceTransferResult::waiting((string) __('sevdesk::incoming.amount_differs', ['number' => (string) $incoming->invoice_number]));
            }

            return IncomingInvoiceTransferResult::linked((string) $existing['id'], trim((string) ($existing['description'] ?? '')) ?: $incoming->invoice_number);
        }
        if ($journal->candidate_dismissed_at === null) {
            $candidate = $this->candidateWithoutNumber($client, $incoming, $party, $contactId, $creditDebit);
            if ($candidate !== null) {
                return IncomingInvoiceTransferResult::candidate($candidate['id'], $candidate['label'], (string) __('sevdesk::incoming.candidate', ['voucher' => $candidate['label']]));
            }
        }

        // Erst prüfen, was sevDesk ohne Rückfrage bucht, dann einen fehlenden Kontakt anlegen.
        $version = $client->bookkeepingVersion();
        $booking = $this->booking($incoming, $config, $version);
        if (is_string($booking)) {
            return IncomingInvoiceTransferResult::waiting($booking);
        }
        $file = $this->file($incoming, $version);
        if (is_string($file)) {
            return IncomingInvoiceTransferResult::waiting($file);
        }
        $supplier = $this->supplier($client, $incoming, $party, $contactId);
        if (is_string($supplier)) {
            return IncomingInvoiceTransferResult::waiting($supplier);
        }

        $tax = $version === '2.0'
            ? ['taxRule' => ['id' => (string) ($creditDebit === 'D' ? self::TAX_RULE_REVENUE : self::TAX_RULE_EXPENSE), 'objectName' => 'TaxRule']]
            : ['taxType' => 'default'];
        $created = $client->saveVoucher([
            'voucher' => array_filter([
                'objectName' => 'Voucher',
                'mapAll' => true,
                'voucherDate' => $incoming->issue_date?->format('d.m.Y'),
                'deliveryDate' => $incoming->issue_date?->format('d.m.Y'),
                'paymentDeadline' => $incoming->due_date?->format('d.m.Y'),
                'supplierName' => mb_substr($this->partyName($incoming, $party), 0, 255),
                'description' => $incoming->invoice_number,
                // Entwurf: die Buchhaltung bestätigt in sevDesk.
                'status' => '50',
                'creditDebit' => $creditDebit,
                'voucherType' => 'VOU',
                'currency' => 'EUR',
                ...$tax,
            ], static fn (mixed $value): bool => $value !== null && $value !== '') + ['supplier' => $supplier['supplier']],
            'voucherPosSave' => $booking['positions'],
            'voucherPosDelete' => null,
            'filename' => $client->uploadVoucherFile($file['content'], $file['name']),
        ]);
        $voucher = is_array($created['voucher'] ?? null) ? $created['voucher'] : $created;
        $externalId = trim((string) ($voucher['id'] ?? ''));
        if ($externalId === '') {
            throw new RuntimeException('sevDesk saveVoucher returned no id.');
        }
        $journal->forceFill(['external_id' => $externalId, 'external_number' => $incoming->invoice_number])->save();
        $this->mirror($incoming, $externalId, $creditDebit, $supplier['supplier']['id'] ?? null, $booking['gross']);

        return IncomingInvoiceTransferResult::transferred($externalId, $incoming->invoice_number);
    }

    /**
     * Beleg mit derselben Nummer und Richtung in sevDesk, gleich in welchem
     * Status. Nummern sind nur je Aussteller eindeutig: mit verknüpftem Kontakt
     * sucht sevDesk nur dort, sonst muss der Lieferantenname passen.
     *
     * @return array<string, mixed>|null
     */
    private function existing(SevDeskClient $client, IncomingEInvoice $incoming, Supplier|Customer $party, ?string $contactId, string $creditDebit): ?array {
        $number = trim((string) $incoming->invoice_number);
        if ($number === '') {
            return null;
        }
        foreach ($client->findVouchers($this->filter(['descriptionLike' => $number, 'creditDebit' => $creditDebit], $contactId)) as $row) {
            if (trim((string) ($row['description'] ?? '')) === $number && trim((string) ($row['id'] ?? '')) !== '' && $this->sameParty($row, $incoming, $party, $contactId)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Beleg ohne Rechnungsnummer: derselbe Kontakt bzw. Name, dasselbe
     * Belegdatum und derselbe Bruttobetrag — nur ein Vorschlag.
     *
     * @return array{id: string, label: string}|null
     */
    private function candidateWithoutNumber(SevDeskClient $client, IncomingEInvoice $incoming, Supplier|Customer $party, ?string $contactId, string $creditDebit): ?array {
        $gross = $incoming->amount_gross;
        if (trim((string) $incoming->invoice_number) !== '' || $incoming->issue_date === null || $gross === null) {
            return null;
        }
        $day = Carbon::parse($incoming->issue_date->format('Y-m-d'), 'Europe/Berlin');
        $filter = $this->filter(['creditDebit' => $creditDebit, 'startDate' => $day->copy()->startOfDay()->getTimestamp(), 'endDate' => $day->copy()->endOfDay()->getTimestamp()], $contactId);
        foreach ($client->findVouchers($filter) as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '' || ! $this->sameAmount((string) ($row['sumGross'] ?? ''), $incoming) || ! $this->sameParty($row, $incoming, $party, $contactId)) {
                continue;
            }

            return [
                'id' => $id,
                'label' => implode(' · ', array_filter([
                    trim((string) ($row['description'] ?? '')) ?: null,
                    VoucherMirror::date($row['voucherDate'] ?? null),
                    NumberHelper::toGermanFormat(abs((float) $row['sumGross']), 2, withThousandsSeparator: true),
                    trim((string) ($row['supplierName'] ?? data_get($row, 'supplier.name', ''))) ?: null,
                ])),
            ];
        }

        return null;
    }

    /**
     * @param  array<string, scalar>  $filter
     * @return array<string, scalar>
     */
    private function filter(array $filter, ?string $contactId): array {
        return $contactId === null ? $filter : $filter + ['contact[id]' => $contactId, 'contact[objectName]' => 'Contact'];
    }

    /** @param  array<string, mixed>  $row */
    private function sameParty(array $row, IncomingEInvoice $incoming, Supplier|Customer $party, ?string $contactId): bool {
        if ($contactId !== null) {
            $rowContact = trim((string) data_get($row, 'supplier.id', ''));

            return $rowContact === '' || $rowContact === $contactId;
        }
        $names = array_unique(array_filter([mb_strtolower(trim((string) $party->name)), mb_strtolower(trim($this->partyName($incoming, $party)))]));
        $rowName = mb_strtolower(trim((string) ($row['supplierName'] ?? data_get($row, 'supplier.name', ''))));

        return in_array($rowName, $names, true);
    }

    private function sameAmount(string $gross, IncomingEInvoice $incoming): bool {
        $expected = $incoming->amount_gross?->toFloat();

        return $expected === null || (is_numeric($gross) && abs(abs((float) $gross) - abs($expected)) <= 0.01);
    }

    /**
     * Lieferant bzw. Kunde am Beleg: verknüpfter Kontakt, sonst ein eindeutiger
     * Namenstreffer in sevDesk, sonst der Kontakt-Push (nur wenn workDiary die
     * Stammdaten führt). Der Sammelkontakt geht ohne Kontakt mit dem Namen.
     *
     * @return array{supplier: array{id: int, objectName: string}|null}|string
     */
    private function supplier(SevDeskClient $client, IncomingEInvoice $incoming, Supplier|Customer $party, ?string $contactId): array|string {
        if ($party->is_collective) {
            return ['supplier' => null];
        }
        $contactId ??= $this->uniqueContactByName($client, $incoming, $party);
        if ($contactId === null) {
            try {
                $contactId = $party instanceof Supplier
                    ? app(ContactPushService::class)->pushSupplier($party, SevDeskPlugin::ID)
                    : app(ContactPushService::class)->push($party, SevDeskPlugin::ID);
            } catch (RuntimeException) {
                return (string) __('sevdesk::incoming.contact_missing', ['party' => (string) $party->name]);
            }
        }

        return ['supplier' => ['id' => (int) $contactId, 'objectName' => 'Contact']];
    }

    private function uniqueContactByName(SevDeskClient $client, IncomingEInvoice $incoming, Supplier|Customer $party): ?string {
        $name = mb_strtolower(trim((string) $party->name));
        if ($name === '') {
            return null;
        }
        $hits = array_values(array_filter($client->contactsByName((string) $party->name), static fn (mixed $row): bool => is_array($row)
            && mb_strtolower(trim((string) ($row['name'] ?? ''))) === $name && trim((string) ($row['id'] ?? '')) !== ''));
        if (count($hits) !== 1) {
            return null;
        }
        $contactId = trim((string) $hits[0]['id']);
        ExternalReference::link((int) $incoming->organization_id, SevDeskPlugin::ID, ExternalReference::TYPE_CONTACT, $party, $contactId);

        return $contactId;
    }

    private function linkedContactId(IncomingEInvoice $incoming, Supplier|Customer $party): ?string {
        $id = ExternalReference::query()
            ->forPlugin((int) $incoming->organization_id, SevDeskPlugin::ID, ExternalReference::TYPE_CONTACT)
            ->forReferenceable($party)
            ->value('external_id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    /** Name der echten Partei laut Beleg — am Sammelkontakt bleibt er am Beleg. */
    private function partyName(IncomingEInvoice $incoming, Supplier|Customer $party): string {
        return $incoming->counterpartyName() ?? (string) $party->name;
    }

    /**
     * Je Steuersatz eine Position mit Bruttobetrag und dem Buchungskonto der
     * Richtung; Gutschriften mit negativem Betrag. Ein Hinweis, wenn sevDesk den
     * Beleg so nicht ohne Rückfrage buchen kann.
     *
     * @param  array<string, mixed>  $config
     * @return array{positions: list<array<string, mixed>>, gross: string}|string
     */
    private function booking(IncomingEInvoice $incoming, array $config, string $version): array|string {
        $account = $incoming->direction === DocumentDirection::Outgoing ? $config['outgoing_account_id'] : $config['incoming_account_id'];
        if ($account === null) {
            return (string) __('sevdesk::incoming.no_account');
        }
        if (($incoming->currency->value ?? 'EUR') !== 'EUR') {
            return (string) __('sevdesk::incoming.currency');
        }
        $grossByRate = $this->grossByRate($incoming);
        if (is_string($grossByRate)) {
            return $grossByRate;
        }

        $sign = $incoming->kind === DocumentKind::CreditNote ? '-' : '';
        $positions = [];
        $total = '0.00';
        foreach ($grossByRate as $rate => $gross) {
            $positions[] = [
                'objectName' => 'VoucherPos',
                'mapAll' => true,
                ...($version === '2.0'
                    ? ['accountDatev' => ['id' => (int) $account, 'objectName' => 'AccountDatev']]
                    : ['accountingType' => ['id' => (int) $account, 'objectName' => 'AccountingType']]),
                'taxRate' => (float) $rate,
                'net' => false,
                'sumGross' => (float) ($sign . $gross),
                'comment' => mb_substr((string) ($incoming->invoice_number ?? ''), 0, 255),
            ];
            $total = NumberHelper::addPrecise($total, $gross, 2);
        }

        return ['positions' => $positions, 'gross' => $sign . $total];
    }

    /**
     * Bruttobetrag je Steuersatz (positiv) aus der Steueraufschlüsselung, sonst
     * aus geprüften Kopfwerten mit genau einem Satz.
     *
     * @return array<int, numeric-string>|string
     */
    private function grossByRate(IncomingEInvoice $incoming): array|string {
        $breakdown = $incoming->taxBreakdown();
        if ($breakdown !== []) {
            $grossByRate = [];
            foreach ($breakdown as $subtotal) {
                if (! in_array(strtoupper(trim((string) ($subtotal['category'] ?? ''))), self::REGULAR_CATEGORIES, true)) {
                    return (string) __('sevdesk::incoming.tax_case');
                }
                $rate = self::knownRate($subtotal['percent'] ?? null);
                if ($rate === null || ! is_numeric($subtotal['net'] ?? null) || ! is_numeric($subtotal['tax'] ?? null)) {
                    return (string) __('sevdesk::incoming.rates');
                }
                $gross = NumberHelper::addPrecise(ltrim((string) $subtotal['net'], '-'), ltrim((string) $subtotal['tax'], '-'), 2);
                $grossByRate[$rate] = NumberHelper::addPrecise($grossByRate[$rate] ?? '0', $gross, 2);
            }

            return $grossByRate;
        }

        $net = $incoming->amount_net;
        $tax = $incoming->amount_tax;
        $gross = $incoming->amount_gross;
        if ($net === null || $tax === null || $gross === null || $net->abs()->toFloat() < 0.01 || abs($net->plus($tax)->minus($gross)->toFloat()) > 0.01) {
            return (string) __('sevdesk::incoming.rates');
        }
        $rate = self::knownRate($tax->abs()->toFloat() / $net->abs()->toFloat() * 100);

        return $rate === null ? (string) __('sevdesk::incoming.rates') : [$rate => $gross->abs()->getAmount()];
    }

    private static function knownRate(mixed $percent): ?int {
        if (! is_numeric($percent)) {
            return null;
        }
        foreach (self::TAX_RATES as $rate) {
            if (abs((float) $percent - $rate) <= 0.5) {
                return (int) $rate;
            }
        }

        return null;
    }

    /**
     * Das Original; eine XRechnung unter Buchhaltung 1.0 als mitgeschicktes PDF,
     * denn sevDesk nimmt XML erst ab 2.0 an.
     *
     * @return array{content: string, name: string}|string
     */
    private function file(IncomingEInvoice $incoming, string $version): array|string {
        $original = $incoming->document?->currentVersion;
        if ($original === null || ! Storage::disk($original->disk)->exists($original->path)) {
            throw new RuntimeException('Belegdatei fehlt.');
        }
        $file = $original;
        if ($version !== '2.0' && str_contains(strtolower((string) $original->mime), 'xml')) {
            $file = $incoming->attachments()->get()->first(static fn ($attachment): bool => str_contains(strtolower((string) $attachment->mime), 'pdf') && Storage::disk($attachment->disk)->exists($attachment->path));
            if ($file === null) {
                return (string) __('sevdesk::incoming.xml_requires_update');
            }
        }

        return [
            'content' => (string) Storage::disk($file->disk)->get($file->path),
            'name' => (string) ($file->original_name ?: basename((string) $file->path)),
        ];
    }

    /** Spiegelzeile sofort: der übergebene Eingang verlässt den Belegfluss, der sevDesk-Beleg tritt an seine Stelle. */
    private function mirror(IncomingEInvoice $incoming, string $externalId, string $creditDebit, ?int $contactId, string $gross): void {
        $counters = VoucherMirror::counters();
        app(VoucherMirror::class)->store((int) $incoming->organization_id, SevDeskPlugin::ID, new MirroredVoucher(
            externalId: $externalId,
            direction: $creditDebit === 'D' ? DocumentDirection::Outgoing : DocumentDirection::Incoming,
            kind: DocumentKind::Other,
            rawType: $creditDebit,
            rawStatus: '50',
            state: AccountingVoucherState::Draft,
            number: $incoming->invoice_number,
            date: $incoming->issue_date?->format('Y-m-d'),
            dueDate: $incoming->due_date?->format('Y-m-d'),
            totalAmount: $gross,
            netAmount: $incoming->amount_net?->getAmount(),
            currency: 'EUR',
            contactExternalId: $contactId !== null ? (string) $contactId : null,
            supplierName: $incoming->counterpartyName(),
        ), $counters);
    }
}
