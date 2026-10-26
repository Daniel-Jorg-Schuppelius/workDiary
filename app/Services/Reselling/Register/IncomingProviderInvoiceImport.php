<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingProviderInvoiceImport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Billing\DocumentDirection;
use App\Enums\Reselling\SubscriptionProvider;
use App\Models\Domain\DomainAccountingEntry;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\{Organization, User};
use App\Models\Reselling\{ResalePeriod, ResalePurchaseEntry, ResaleSubscription};
use App\Services\Billing\Purchase\{IncomingEInvoicePurchaseDocumentSource, PurchaseDocument};
use App\Services\Invoicing\EInvoice\IncomingEInvoiceService;
use App\Services\Reselling\Marketplace\{ProviderInvoice, QualityHostingInvoiceReader};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CountryCode;
use CommonToolkit\Helper\Data\{CryptoHelper, NumberHelper, StringHelper};
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Facades\{DB, Log, Storage};
use PDFToolkit\Helper\PDFTextProvider;
use RuntimeException;
use Throwable;

/**
 * Eingangsrechnung eines zugeordneten Lieferanten als Einkauf übernehmen
 * (Feature 185, MVP-1420): Quality Hosting positionsgenau über den
 * PDF-Leser, Domainanbieter je Domainname, alle anderen pro rata auf den
 * Rechnungsmonat. Herkunft ist die Eingangsrechnung (Morph am Einkauf);
 * ein zweiter Lauf erkennt die Zeilen am Hash. Nicht zuordenbare
 * Positionen bleiben als Einkauf ohne Abo stehen — der Prüffall im
 * Einkauf-Reiter.
 *
 * @phpstan-type Result array{kind: string, number: string, entries: int, net: float, unmatched: list<string>, duplicates: int, issues: list<string>, error: string|null, skipped: string|null}
 */
class IncomingProviderInvoiceImport {
    public const KIND_LINES = 'lines';

    public const KIND_DOMAINS = 'domains';

    public const KIND_PRO_RATA = 'pro_rata';

    public function __construct(
        private readonly ProviderSuppliers $suppliers,
        private readonly PurchaseAllocator $allocator,
        private readonly QualityHostingInvoiceReader $reader,
        private readonly IncomingEInvoicePurchaseDocumentSource $documents,
        private readonly IncomingEInvoiceService $einvoices,
    ) {}

    /** Zuständig? Eingangsbeleg mit Lieferant, der einem Anbieter zugeordnet ist. */
    public function providerFor(IncomingEInvoice $incoming): ?SubscriptionProvider {
        $organization = $incoming->organization;
        if ($organization === null || $incoming->supplier_id === null || $incoming->direction === DocumentDirection::Outgoing) {
            return null;
        }

        return $this->suppliers->providerFor($organization, (int) $incoming->supplier_id);
    }

    /**
     * @return Result|null null = nicht zuständig
     */
    public function import(IncomingEInvoice $incoming, ?User $user = null): ?array {
        $provider = $this->providerFor($incoming);
        $organization = $incoming->organization;
        if ($provider === null || $organization === null) {
            return null;
        }
        $document = $this->documents->toDocument($incoming);

        return match ($provider) {
            SubscriptionProvider::QualityHosting => $this->importLines($organization, $incoming, $provider, $document, $user),
            SubscriptionProvider::DomainReselling => $this->importDomains($organization, $incoming, $provider, $document, $user),
            default => $this->allocateProRata($organization, $incoming, $provider, $document, $user),
        };
    }

    /** @return Result */
    private function importLines(Organization $organization, IncomingEInvoice $incoming, SubscriptionProvider $provider, PurchaseDocument $document, ?User $user): array {
        $result = $this->result(self::KIND_LINES, $document);
        $original = $this->storedOriginal($incoming);
        if ($original === null) {
            $result['error'] = (string) __('resale.purchase.incoming.no_file');

            return $result;
        }
        try {
            $invoice = $this->read($original['path'], $original['name']);
        } catch (Throwable $e) {
            $result['error'] = $e::class === RuntimeException::class ? $e->getMessage() : (string) __('resale.purchase.incoming.unreadable', ['number' => $document->number ?? '?']);
            if ($e::class !== RuntimeException::class) {
                Log::warning('resale: Eingangsrechnung nicht lesbar', ['incoming_id' => $incoming->id, 'exception' => $e::class, 'message' => $e->getMessage()]);
            }

            return $result;
        }
        if ($invoice->number === '' || $invoice->lines === []) {
            $result['error'] = (string) __('resale.purchase.incoming.unreadable', ['number' => $document->number ?? '?']);

            return $result;
        }
        $imported = $this->allocator->importProviderInvoice($organization, $invoice, $provider, $user, $original['name'], $document->currency, $document, keepUnmatched: true);
        $result['number'] = $invoice->number;
        $result['entries'] = $imported['matched'];
        $result['net'] = $imported['net'];
        $result['unmatched'] = $imported['unmatched'];
        $result['duplicates'] = $imported['duplicates'];
        $result['issues'] = $imported['issues'];

        return $result;
    }

    /**
     * Sammelrechnung ohne Positionen (Telekom): pro rata auf die Perioden des Rechnungsmonats.
     *
     * @return Result
     */
    private function allocateProRata(Organization $organization, IncomingEInvoice $incoming, SubscriptionProvider $provider, PurchaseDocument $document, ?User $user): array {
        $result = $this->result(self::KIND_PRO_RATA, $document);
        $net = $incoming->amount_net;
        if ($net === null || $net->isZero()) {
            $result['error'] = (string) __('resale.purchase.incoming.no_net');

            return $result;
        }
        $month = $document->date ?? CarbonImmutable::instance($incoming->received_at);
        $allocated = $this->allocator->allocateVoucher($organization, $document, $provider, $net, $month, $user);
        $result['entries'] = $allocated['entries'];
        $result['net'] = $allocated['allocated'];
        if ($allocated['unallocated'] > 0.009) {
            $result['issues'][] = (string) __('resale.purchase.incoming.unallocated', ['amount' => Money::ofFloat($allocated['unallocated'], $net->getCurrency(), 2)->format()]);
        }

        return $result;
    }

    /**
     * Domainrechnung je Domainname: Positionen aus der E-Rechnung, sonst aus dem
     * PDF-Text. Führt das Buchungsjournal des Registrars (Feature 083) die
     * Kosten schon, wird nichts gebucht — sonst stünde jede Domain doppelt.
     *
     * @return Result
     */
    private function importDomains(Organization $organization, IncomingEInvoice $incoming, SubscriptionProvider $provider, PurchaseDocument $document, ?User $user): array {
        $result = $this->result(self::KIND_DOMAINS, $document);
        if (DomainAccountingEntry::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->exists()) {
            $result['skipped'] = (string) __('resale.purchase.incoming.domains_accounting');

            return $result;
        }
        $original = $this->storedOriginal($incoming);
        if ($original === null) {
            $result['error'] = (string) __('resale.purchase.incoming.no_file');

            return $result;
        }
        $lines = $this->domainLines($original, $document->currency);
        if ($lines === []) {
            $result['error'] = (string) __('resale.purchase.incoming.unreadable', ['number' => $document->number ?? '?']);

            return $result;
        }
        $subscriptions = ResaleSubscription::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('provider', SubscriptionProvider::DomainReselling->value)
            ->get()
            ->keyBy(static fn(ResaleSubscription $s): string => mb_strtolower((string) $s->external_id));
        $entryDate = $document->date ?? CarbonImmutable::instance($incoming->received_at);

        DB::transaction(function () use ($organization, $provider, $document, $user, $lines, $subscriptions, $entryDate, &$result): void {
            foreach ($lines as $position => $line) {
                $hash = (string) CryptoHelper::hash('incoming|' . $document->morphId . '|' . $position . '|' . $line['domain']);
                if (ResalePurchaseEntry::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->where('raw_hash', $hash)->exists()) {
                    $result['duplicates']++;

                    continue;
                }
                $subscription = $subscriptions->get($line['domain']);
                $period = $subscription === null ? null : ResalePeriod::query()->withoutGlobalScopes()
                    ->where('subscription_id', $subscription->id)
                    ->where('starts_on', '<', DateRange::dayAfter($entryDate))
                    ->where('ends_on', '>=', DateRange::day($entryDate))
                    ->first();
                ResalePurchaseEntry::query()->create([
                    'organization_id' => $organization->id,
                    'subscription_id' => $subscription?->id,
                    'period_id' => $period?->id,
                    'provider' => $provider,
                    'source' => ResalePurchaseEntry::SOURCE_PROVIDER_INVOICE,
                    'document_type' => $document->morphClass,
                    'document_id' => $document->morphId,
                    'document_number' => $document->number !== null ? mb_substr($document->number, 0, 64) : null,
                    'entry_date' => $entryDate->toDateString(),
                    'description' => mb_substr($line['text'], 0, 255),
                    'net_amount' => $line['net'],
                    'currency' => $document->currency->value,
                    'raw_hash' => $hash,
                    'created_by_user_id' => $user?->id,
                ]);
                if ($subscription === null) {
                    $result['unmatched'][] = $line['domain'];
                } else {
                    $result['entries']++;
                    $result['net'] += $line['net'];
                }
            }
            $result['net'] = round($result['net'], 2);
        });

        return $result;
    }

    /**
     * Domain-Positionen: aus der E-Rechnung (Name + Netto), sonst Zeilen des
     * PDF-Texts mit Domainname und Betrag am Zeilenende.
     *
     * @param  array{contents: string, mime: ?string, path: string, name: string}  $original
     * @return list<array{domain: string, net: float, text: string}>
     */
    private function domainLines(array $original, \CommonToolkit\Enums\CurrencyCode $currency): array {
        $lines = [];
        $document = null;
        try {
            $document = $this->einvoices->parse($original['contents'], $original['mime'], $original['path']);
        } catch (Throwable) {
            $document = null;
        }
        if ($document !== null && $document->countLines() > 0) {
            foreach ($document->getLines() as $line) {
                $text = trim($line->getItemName() . ' ' . ($line->getItemDescription() ?? ''));
                $domain = self::domainIn($text);
                if ($domain === null) {
                    continue;
                }
                $lines[] = ['domain' => $domain, 'net' => $line->getNetAmount()->toFloat(), 'text' => $text];
            }

            return $lines;
        }
        try {
            $text = (string) (new PDFTextProvider($original['path']))->rowAlignedText();
        } catch (Throwable) {
            return [];
        }
        foreach (StringHelper::lines($text) as $row) {
            $row = trim($row);
            $domain = self::domainIn($row);
            if ($domain === null || preg_match('/(-?\d{1,3}(?:\.\d{3})*,\d{2})\s*(?:€|EUR)?\s*$/u', $row, $m) !== 1) {
                continue;
            }
            try {
                $net = NumberHelper::normalizeDecimal($m[1], CountryCode::Germany);
            } catch (Throwable) {
                continue;
            }
            $lines[] = ['domain' => $domain, 'net' => $net, 'text' => $row];
        }

        return $lines;
    }

    private static function domainIn(string $text): ?string {
        if (preg_match('/(?<![\w@.-])((?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,})(?![\w-])/i', $text, $m) !== 1) {
            return null;
        }

        return mb_strtolower($m[1]);
    }

    /** PDF lesen — Naht zum Leser (Tests liefern hier ein Text-Fixture). */
    protected function read(string $path, string $name): ProviderInvoice {
        return $this->reader->read($path, $name);
    }

    /**
     * Original der Eingangsrechnung von der Platte (aktuelle Dokumentversion) — Naht für Tests.
     *
     * @return array{contents: string, mime: ?string, path: string, name: string}|null
     */
    protected function storedOriginal(IncomingEInvoice $incoming): ?array {
        $version = $incoming->document?->currentVersion;
        if ($version === null) {
            return null;
        }
        $disk = Storage::disk((string) ($version->disk ?: 'local'));
        $path = (string) $version->path;
        if ($path === '' || ! $disk->exists($path)) {
            return null;
        }

        return [
            'contents' => (string) $disk->get($path),
            'mime' => $version->mime !== null ? (string) $version->mime : null,
            'path' => $disk->path($path),
            'name' => (string) $version->original_name,
        ];
    }

    /** @return Result */
    private function result(string $kind, PurchaseDocument $document): array {
        return ['kind' => $kind, 'number' => (string) ($document->number ?? ''), 'entries' => 0, 'net' => 0.0, 'unmatched' => [], 'duplicates' => 0, 'issues' => [], 'error' => null, 'skipped' => null];
    }
}
