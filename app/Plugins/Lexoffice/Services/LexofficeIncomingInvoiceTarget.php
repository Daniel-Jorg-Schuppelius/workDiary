<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeIncomingInvoiceTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use APIToolkit\Entities\ID;
use APIToolkit\Exceptions\NotAcceptableException;
use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Enums\Invoicing\IncomingInvoiceRecognition;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\{IncomingEInvoice, IncomingEInvoiceTransfer};
use App\Models\Platform\Organization;
use App\Models\Supplier\Supplier;
use App\Plugins\Lexoffice\Api\LexofficeClientFactory;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficePlugin};
use App\Plugins\Lexoffice\Models\LexofficeVoucher;
use App\Services\Finance\Accounting\ContactPushService;
use App\Services\Invoicing\Contracts\IncomingInvoiceTransferTarget;
use App\Services\Invoicing\Dto\IncomingInvoiceTransferResult;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Support\Facades\Storage;
use Lexoffice\API\Client;
use Lexoffice\API\Endpoints\{VoucherListEndpoint, VouchersEndpoint};
use Lexoffice\Entities\Files\File as LexofficeFile;
use Lexoffice\Entities\Vouchers\Voucher;
use RuntimeException;

/**
 * Rechnungseingang → Lexware Office (Feature 163, MVP-1111).
 *
 * Der Beleg geht als „zu prüfen“ (`unchecked`) hinaus: Ein angelegter Beleg
 * ist per API nicht löschbar und seit 14.07.2026 nur noch nach `open`
 * änderbar — die Buchhaltung bestätigt in Lexware. Liegt ein Beleg mit
 * derselben Nummer schon dort, wird nur verknüpft. Beträge gehen nur mit
 * Positionen hinaus (Spaltenmethode, je Steuersatz eine Position) und nur mit
 * Buchungskategorie, in EUR und mit den Sätzen, die Lexware kennt.
 */
final class LexofficeIncomingInvoiceTarget implements IncomingInvoiceTransferTarget {
    /** Buchungskategorie je Lieferant bzw. Kunde (ExternalReference). */
    public const EXT_TYPE_POSTING_CATEGORY = 'posting_category';

    /** Steuersätze, die Lexware für Belege annimmt (Kochbuch Buchhaltung). */
    private const TAX_RATES = [0.0, 5.0, 7.0, 16.0, 19.0];

    public function key(): string {
        return LexofficePlugin::ID;
    }

    public function label(): string {
        return (string) __('lexoffice::incoming.label');
    }

    public function isEnabled(Organization $organization): bool {
        $config = LexofficeConfig::resolve((int) $organization->id);

        return $config['enabled'] && is_string($config['api_key']) && $config['api_key'] !== '' && $config['incoming_transfer'];
    }

    public function appliesTo(IncomingEInvoice $incoming): bool {
        return true;
    }

    public function transfer(IncomingEInvoice $incoming, IncomingEInvoiceTransfer $journal): IncomingInvoiceTransferResult {
        $config = LexofficeConfig::resolve((int) $incoming->organization_id);
        $client = app(LexofficeClientFactory::class)->sdk((string) $config['api_key'], (string) $config['base_url']);
        $vouchers = new VouchersEndpoint($client);
        $type = self::voucherType($incoming);
        $note = null;

        if ($journal->external_id === null) {
            $existing = $this->existing($client, $incoming, $type);
            if ($existing !== null) {
                if (! $this->sameAmount($existing, $incoming)) {
                    return IncomingInvoiceTransferResult::waiting((string) __('lexoffice::incoming.amount_differs', ['number' => (string) $incoming->invoice_number]));
                }

                return IncomingInvoiceTransferResult::linked($existing['id'], $existing['voucherNumber'] ?? $incoming->invoice_number);
            }

            $contact = $this->contact($incoming);
            if (is_string($contact)) {
                return IncomingInvoiceTransferResult::waiting($contact);
            }
            [$amounts, $note] = $this->amounts($incoming, $config);

            try {
                $resource = $vouchers->create(Voucher::fromArray(array_filter([
                    'type' => $type,
                    'voucherStatus' => 'unchecked',
                    'voucherNumber' => $incoming->invoice_number,
                    'voucherDate' => self::date($incoming->issue_date),
                    'dueDate' => self::date($incoming->due_date),
                    'taxType' => 'gross',
                    'remark' => mb_substr((string) __('lexoffice::incoming.remark', ['sender' => $incoming->sender_email ?? $incoming->source]), 0, 255),
                    ...$contact,
                    ...$amounts,
                ], static fn (mixed $value): bool => $value !== null)));
            } catch (NotAcceptableException $e) {
                // E26: Rolle fehlt am Kontakt — die Buchhaltung ergänzt sie in Lexware, WorkDiary nie still.
                if (isset($contact['contactId']) && str_contains((string) $e->getContent(), 'contactId')) {
                    return IncomingInvoiceTransferResult::waiting((string) __('lexoffice::incoming.contact_role', ['party' => (string) $incoming->counterparty()?->name]));
                }

                throw $e;
            }
            $externalId = $resource->getId()->toString();
            if ($externalId === '') {
                throw new RuntimeException('Lexoffice voucher create returned no id.');
            }
            // Sofort festhalten: bricht der Datei-Upload ab, hängt die Wiederholung nur noch die Datei an.
            $journal->forceFill(['external_id' => $externalId, 'external_number' => $incoming->invoice_number])->save();
            $this->mirror($incoming, $externalId, $type, $contact, $amounts);
        }

        $this->attachFiles($vouchers, $incoming, (string) $journal->external_id);

        return IncomingInvoiceTransferResult::transferred((string) $journal->external_id, $incoming->invoice_number, $note);
    }

    /** Belegart in Lexware aus Richtung und Rechnungsart. */
    public static function voucherType(IncomingEInvoice $incoming): string {
        $isCredit = $incoming->kind === DocumentKind::CreditNote;

        return $incoming->direction === DocumentDirection::Outgoing
            ? ($isCredit ? 'salescreditnote' : 'salesinvoice')
            : ($isCredit ? 'purchasecreditnote' : 'purchaseinvoice');
    }

    /**
     * Beleg mit derselben Nummer und Belegart in Lexware, gleich in welchem
     * Status — auch einer, den die Buchhaltung selbst erfasst hat. Nummern
     * sind nur je Aussteller eindeutig: mit verknüpftem Kontakt sucht Lexware
     * nur dort, sonst muss der Kontaktname passen.
     *
     * @return array{id: string, voucherNumber: ?string, totalAmount: float}|null
     */
    private function existing(Client $client, IncomingEInvoice $incoming, string $type): ?array {
        $number = trim((string) $incoming->invoice_number);
        $party = $incoming->counterparty();
        if ($number === '' || $party === null) {
            return null;
        }
        $contactId = $party->is_collective ? null : $this->linkedContactId($incoming, $party);
        $names = array_unique(array_filter([
            mb_strtolower(trim((string) $party->name)),
            mb_strtolower(trim(self::collectiveName($incoming, $party))),
        ]));

        $page = (new VoucherListEndpoint($client))->search(array_filter([
            'voucherType' => $type,
            'voucherStatus' => 'any',
            'voucherNumber' => $number,
            'contactId' => $contactId,
        ]));
        foreach ($page->getContent() as $voucher) {
            $id = $voucher->getID()?->toString();
            if ($id === null || $id === '' || trim((string) $voucher->getVoucherNumber()) !== $number) {
                continue;
            }
            if ($contactId === null && ! in_array(mb_strtolower(trim((string) $voucher->getContactName())), $names, true)) {
                continue;
            }

            return ['id' => $id, 'voucherNumber' => $voucher->getVoucherNumber(), 'totalAmount' => $voucher->getTotalAmount()->toFloat()];
        }

        return null;
    }

    /** @param  array{id: string, voucherNumber: ?string, totalAmount: float}  $existing */
    private function sameAmount(array $existing, IncomingEInvoice $incoming): bool {
        $gross = $incoming->amount_gross?->toFloat();

        return $gross === null || abs(abs($existing['totalAmount']) - abs($gross)) <= 0.01;
    }

    /**
     * Kontaktangaben des Belegs oder ein Hinweis, warum noch keiner feststeht.
     *
     * @return array{useCollectiveContact: bool, contactId?: string, contactName?: string}|string
     */
    private function contact(IncomingEInvoice $incoming): array|string {
        $party = $incoming->counterparty();
        if ($party === null) {
            return (string) __('lexoffice::incoming.no_party');
        }
        if ($party->is_collective) {
            return ['useCollectiveContact' => true, 'contactName' => mb_substr(self::collectiveName($incoming, $party), 0, 255)];
        }

        $linked = $this->linkedContactId($incoming, $party);
        if ($linked !== null) {
            return ['useCollectiveContact' => false, 'contactId' => $linked];
        }

        // Sucht zuerst einen passenden Lexware-Kontakt (E-Mail, USt-IdNr.) und legt nur sonst einen an.
        try {
            $contactId = $party instanceof Supplier
                ? app(ContactPushService::class)->pushSupplier($party, LexofficePlugin::ID)
                : app(ContactPushService::class)->push($party, LexofficePlugin::ID);
        } catch (RuntimeException) {
            return (string) __('lexoffice::incoming.contact_missing', ['party' => (string) $party->name]);
        }

        return ['useCollectiveContact' => false, 'contactId' => $contactId];
    }

    private function linkedContactId(IncomingEInvoice $incoming, Supplier|Customer $party): ?string {
        $reference = ExternalReference::query()
            ->forPlugin((int) $incoming->organization_id, LexofficePlugin::ID, LexofficePlugin::EXT_TYPE_CONTACT)
            ->forReferenceable($party)
            ->first();

        return $reference instanceof ExternalReference && $reference->external_id !== '' ? (string) $reference->external_id : null;
    }

    /** Name der echten Partei am Sammelkontakt — er bleibt am Beleg. */
    private static function collectiveName(IncomingEInvoice $incoming, Supplier|Customer $party): string {
        $name = trim((string) ($incoming->direction === DocumentDirection::Outgoing ? $incoming->buyer_name : $incoming->seller_name));

        return $name !== '' ? $name : (string) $party->name;
    }

    /**
     * Positionen je Steuersatz mit Summen — oder gar keine Beträge, dann mit Grund.
     *
     * @param  array<string, mixed>  $config
     * @return array{0: array{totalGrossAmount?: float, totalTaxAmount?: float, voucherItems?: list<array{amount: float, taxAmount: float, taxRatePercent: float, categoryId: string}>}, 1: ?string}
     */
    private function amounts(IncomingEInvoice $incoming, array $config): array {
        $category = $this->category($incoming, $config);
        if ($category === null) {
            return [[], (string) __('lexoffice::incoming.header_only.no_category')];
        }
        if (($incoming->currency->value ?? 'EUR') !== 'EUR') {
            return [[], (string) __('lexoffice::incoming.header_only.currency')];
        }

        $grossByRate = $this->grossByRate($incoming);
        if ($grossByRate === null) {
            return [[], (string) __('lexoffice::incoming.header_only.rates')];
        }

        $items = [];
        $gross = 0.0;
        $tax = 0.0;
        foreach ($grossByRate as $rate => $amount) {
            // Spaltenmethode wie Lexware: Steuer je Satz aus dem Bruttobetrag herausgerechnet.
            $itemTax = round($amount * $rate / (100 + $rate), 2);
            $items[] = ['amount' => round($amount, 2), 'taxAmount' => $itemTax, 'taxRatePercent' => (float) $rate, 'categoryId' => $category];
            $gross += round($amount, 2);
            $tax += $itemTax;
        }

        return [['totalGrossAmount' => round($gross, 2), 'totalTaxAmount' => round($tax, 2), 'voucherItems' => $items], null];
    }

    /**
     * Bruttobetrag je Steuersatz (positiv, auch bei Gutschriften). Aus der
     * Steueraufschlüsselung der E-Rechnung, sonst aus geprüften Kopfwerten
     * mit genau einem Satz. Null, wenn ein Satz nicht passt.
     *
     * @return array<int, float>|null
     */
    private function grossByRate(IncomingEInvoice $incoming): ?array {
        $summary = (array) $incoming->summary;
        $breakdown = (array) ($summary['tax_breakdown'] ?? []);
        $grossByRate = [];
        if ($incoming->recognition === IncomingInvoiceRecognition::Structured && $breakdown !== []) {
            foreach ($breakdown as $subtotal) {
                $rate = self::knownRate(is_array($subtotal) ? ($subtotal['percent'] ?? null) : null);
                if ($rate === null) {
                    return null;
                }
                $grossByRate[$rate] = ($grossByRate[$rate] ?? 0.0) + abs((float) ($subtotal['net'] ?? 0)) + abs((float) ($subtotal['tax'] ?? 0));
            }

            return $grossByRate;
        }

        $net = $incoming->amount_net?->toFloat();
        $tax = $incoming->amount_tax?->toFloat();
        $gross = $incoming->amount_gross?->toFloat();
        if ($net === null || $tax === null || $gross === null || abs(($net + $tax) - $gross) > 0.01 || abs($net) < 0.01) {
            return null;
        }
        $rate = self::knownRate(abs($tax) / abs($net) * 100);

        return $rate === null ? null : [$rate => abs($gross)];
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

    /** @param  array<string, mixed>  $config */
    private function category(IncomingEInvoice $incoming, array $config): ?string {
        $party = $incoming->counterparty();
        $reference = $party === null ? null : ExternalReference::query()
            ->forPlugin((int) $incoming->organization_id, LexofficePlugin::ID, self::EXT_TYPE_POSTING_CATEGORY)
            ->forReferenceable($party)
            ->first();
        $category = $reference->external_id
            ?? ($incoming->direction === DocumentDirection::Outgoing ? $config['outgoing_default_category'] : $config['incoming_default_category']);

        return is_string($category) && $category !== '' ? $category : null;
    }

    /** Original (bei XRechnung die XML) und ein mitgeschickter Sichtbeleg als PDF. */
    private function attachFiles(VouchersEndpoint $vouchers, IncomingEInvoice $incoming, string $externalId): void {
        $version = $incoming->document?->currentVersion;
        if ($version === null || ! Storage::disk($version->disk)->exists($version->path)) {
            throw new RuntimeException('Belegdatei fehlt.');
        }
        $files = [[Storage::disk($version->disk)->path($version->path), (string) ($version->original_name ?: basename($version->path))]];
        $isXml = str_contains(strtolower((string) $version->mime), 'xml');
        if ($isXml) {
            $visual = $incoming->attachments()->get()->first(static fn ($attachment): bool => str_contains(strtolower((string) $attachment->mime), 'pdf'));
            if ($visual !== null && Storage::disk($visual->disk)->exists($visual->path)) {
                $files[] = [Storage::disk($visual->disk)->path($visual->path), (string) $visual->original_name];
            }
        }
        foreach ($files as [$path, $name]) {
            // Lexware erkennt Dubletten an der Prüfsumme: eine Wiederholung legt nichts doppelt an.
            $vouchers->addFile(new ID($externalId), new LexofficeFile(['filePath' => $path, 'fileName' => $name]));
        }
    }

    /**
     * Spiegelzeile sofort, nicht erst beim nächsten Abgleich — sonst zählte der Belegfluss den Eingang doppelt.
     *
     * @param  array<string, mixed>  $contact
     * @param  array<string, mixed>  $amounts
     */
    private function mirror(IncomingEInvoice $incoming, string $externalId, string $type, array $contact, array $amounts): void {
        $party = $incoming->counterparty();
        LexofficeVoucher::query()->updateOrCreate(
            ['organization_id' => $incoming->organization_id, 'external_id' => $externalId],
            [
                'contact_external_id' => $contact['contactId'] ?? null,
                'supplier_id' => $party instanceof Supplier ? $party->id : null,
                'customer_id' => $party instanceof Customer ? $party->id : null,
                'voucher_type' => $type,
                'recipient_name' => $contact['contactName'] ?? null,
                'voucher_status' => 'unchecked',
                'voucher_number' => $incoming->invoice_number,
                'voucher_date' => $incoming->issue_date,
                'due_date' => $incoming->due_date,
                'total_amount' => isset($amounts['totalGrossAmount']) ? NumberHelper::normalizeDecimalString((string) $amounts['totalGrossAmount']) : $incoming->amount_gross?->getAmount(),
                'currency' => $incoming->currency->value ?? 'EUR',
                'synced_at' => now(),
            ],
        );
    }

    private static function date(mixed $value): ?string {
        return $value instanceof \DateTimeInterface
            ? CarbonImmutable::parse($value->format('Y-m-d'), 'Europe/Berlin')->startOfDay()->format('Y-m-d\TH:i:s.vP')
            : null;
    }
}
