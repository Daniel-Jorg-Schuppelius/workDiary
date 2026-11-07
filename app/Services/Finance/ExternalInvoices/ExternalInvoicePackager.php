<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalInvoicePackager.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\ExternalInvoices;

use App\Enums\Finance\{ExternalInvoiceFormat, ExternalPackageKind};
use App\Models\Finance\{ExternalInvoice, ExternalInvoicePackage};
use App\Models\Invoicing\InvoiceSupportingDocument;
use App\Models\Platform\User;
use App\Services\Invoicing\EInvoice\SupportingDocumentService;
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper, NumberHelper};
use CommonToolkit\Helper\FileSystem\File;
use ERechnungToolkit\Entities\{Document, SupportingDocument};
use ERechnungToolkit\Enums\{AugmentFailure, ERechnungSyntax};
use ERechnungToolkit\Exceptions\AugmentException;
use ERechnungToolkit\Generators\{SupportingDocumentAugmenter, ZugferdPdfAugmenter};
use ERechnungToolkit\Parsers\{ERechnungParser, ZugferdPdfParser};
use ERechnungToolkit\Validators\{CiiSchemaValidator, DocumentRulesValidator, UblSchemaValidator};
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PDFToolkit\Helper\PDFSecurityHelper;
use Throwable;

/**
 * Versandfassung einer extern ausgestellten Rechnung (Feature 196): das
 * Original bleibt unverändert, Nachweise kommen formatgerecht hinzu (PDF als
 * Beilagen, XRechnung als BG-24, ZUGFeRD als ergänzte XML mit Supplement-
 * Dateien). Die Ergänzung ist eine DOM-Einfügung im Toolkit, keine
 * Neugenerierung; danach prüft die Gegenprobe, dass Nummer, Parteien,
 * Positionen und Beträge gleich geblieben sind.
 */
final class ExternalInvoicePackager {
    private const FOLDER = 'external-invoices';

    public function __construct(private readonly SupportingDocumentService $documents) {}

    /** Format am Inhalt; null, wenn es weder PDF noch eine bekannte XML-Rechnung ist. */
    public function detectFormat(string $content): ?ExternalInvoiceFormat {
        if (str_starts_with(ltrim($content), '%PDF')) {
            $parser = new ZugferdPdfParser;

            return $parser->isAvailable() && $parser->isZugferdPdfContent($content) ? ExternalInvoiceFormat::Zugferd : ExternalInvoiceFormat::Pdf;
        }

        return match ((new SupportingDocumentAugmenter)->detectSyntax($content)) {
            ERechnungSyntax::CII => ExternalInvoiceFormat::XRechnungCii,
            ERechnungSyntax::UBL_INVOICE, ERechnungSyntax::UBL_CREDIT_NOTE => ExternalInvoiceFormat::XRechnungUbl,
            null => null,
        };
    }

    /**
     * Grund, warum das Original nicht formatgerecht ergänzt werden kann; null = möglich.
     * Ein reines PDF wird nie verändert, die Nachweise gehen daneben mit.
     */
    public function blocker(ExternalInvoiceFormat $format, string $original): ?string {
        if ($format === ExternalInvoiceFormat::Pdf) {
            return null;
        }
        $failure = $format->isXml()
            ? (new SupportingDocumentAugmenter)->canAugment($original)
            : File::withTemp($original, static fn (string $path): ?AugmentFailure => (new ZugferdPdfAugmenter)->canAugment($path), 'eip-chk-', 'pdf');

        return $failure === null ? null : $this->reason($failure->value);
    }

    /** Käuferreferenz (BT-10, Leitweg-ID) der Rechnung; null, wenn sie fehlt oder nicht lesbar ist. */
    public function buyerReference(ExternalInvoiceFormat $format, string $original): ?string {
        $document = $this->parse($format, $original);
        $reference = $document?->getBuyerReference();

        return $reference !== null && trim($reference) !== '' ? $reference : null;
    }

    /**
     * Versandfassung bauen, prüfen, ablegen und festschreiben.
     *
     * @param  list<InvoiceSupportingDocument>  $documents  eingefrorene Unterlagen in Ausgabereihenfolge
     * @param  array<string, mixed>  $mail  Umschlag (cc, bcc, template_id, custom_text) für Wiederholungen
     *
     * @throws ValidationException mit konkretem Grund
     */
    public function package(
        ExternalInvoice $invoice,
        string $original,
        ExternalPackageKind $kind,
        string $channel,
        string $recipient,
        array $documents,
        array $mail,
        int $limitBytes,
        ?User $actor,
    ): ExternalInvoicePackage {
        $format = $invoice->original_format ?? throw $this->fail('format_unknown');
        $supporting = $this->supporting($documents);
        $number = Str::slug($invoice->supportingReferenceNumber()) ?: 'rechnung';
        $checks = [];
        $links = array_values(array_filter(array_map(static fn (SupportingDocument $d): ?string => $d->getExternalUri(), $supporting)));

        if ($kind === ExternalPackageKind::Resend || $format === ExternalInvoiceFormat::Pdf) {
            $files = $kind === ExternalPackageKind::Initial
                ? [['name' => sprintf('%s-%s.pdf', $invoice->isCreditNote() ? 'gutschrift' : 'rechnung', $number), 'content' => $original, 'mime' => 'application/pdf', 'role' => 'invoice']]
                : [];
            foreach ($supporting as $document) {
                if ($document->isEmbedded()) {
                    $files[] = ['name' => (string) $document->getFilename(), 'content' => (string) $document->getContent(), 'mime' => (string) $document->getMimeCode(), 'role' => 'evidence'];
                }
            }
            if ($files === []) {
                throw $this->fail('resend_nothing');
            }
            $checks[] = 'original_unchanged';
        } else {
            $augmented = $this->augment($format, $original, $supporting);
            $this->assertSameBusinessContent($format, $original, $augmented, count($supporting));
            $checks = ['business_content', 'rules', 'schema'];
            $files = [[
                'name' => sprintf('%s-%s.%s', $format === ExternalInvoiceFormat::Zugferd ? 'zugferd' : 'xrechnung', $number, $format->extension()),
                'content' => $augmented,
                'mime' => $format->mime(),
                'role' => 'invoice',
            ]];
        }

        $size = array_sum(array_map(static fn (array $f): int => strlen($f['content']), $files));
        if ($size > $limitBytes) {
            throw ValidationException::withMessages(['package' => __('finance.external_invoice.error.too_large', [
                'size' => NumberHelper::formatBytes($size, 1),
                'limit' => NumberHelper::formatBytes($limitBytes, 0),
            ])]);
        }
        $checks[] = 'budget';

        $folder = sprintf('%s/%d/%d/packages/%s', self::FOLDER, $invoice->organization_id, $invoice->id, Str::lower((string) Str::ulid()));
        $disk = Storage::disk(ExternalInvoiceService::DISK);
        $stored = [];
        foreach ($files as $file) {
            $path = $folder . '/' . $file['name'];
            $disk->put($path, $file['content']);
            $stored[] = [
                'name' => $file['name'],
                'path' => $path,
                'mime' => $file['mime'],
                'size' => strlen($file['content']),
                'sha256' => (string) CryptoHelper::hash($file['content']),
                'role' => $file['role'],
            ];
        }

        return ExternalInvoicePackage::query()->create([
            'organization_id' => $invoice->organization_id,
            'external_invoice_id' => $invoice->id,
            'kind' => $kind,
            'format' => $kind === ExternalPackageKind::Resend ? ExternalInvoiceFormat::Pdf : $format,
            'channel' => $channel,
            'recipient' => Str::limit($recipient, 500, ''),
            'storage_path' => $folder,
            'files' => $stored,
            'sha256' => (string) CryptoHelper::hash(JsonHelper::encode(array_column($stored, 'sha256'))),
            'original_sha256' => $invoice->original_sha256,
            'evidence' => array_map(static fn (InvoiceSupportingDocument $d): array => [
                'id' => $d->id,
                'reference' => $d->reference,
                'filename' => $d->filename,
                'sha256' => $d->sha256,
                'kind' => $d->kind->value,
                'external_url' => $d->external_url,
            ], $documents),
            'report' => [
                'checks' => $checks,
                'links' => $links,
                'size' => $size,
                'limit' => $limitBytes,
                'mail' => $mail,
            ],
            'created_by' => $actor?->id,
        ]);
    }

    /**
     * Dateien einer Versandfassung, gegen den festgeschriebenen Hash geprüft.
     *
     * @return list<array{name: string, content: string, mime: string}>
     */
    public function files(ExternalInvoicePackage $package): array {
        $disk = Storage::disk(ExternalInvoiceService::DISK);
        $files = [];
        foreach ($package->files as $file) {
            $content = $disk->exists($file['path']) ? (string) $disk->get($file['path']) : null;
            if ($content === null || CryptoHelper::hash($content) !== $file['sha256']) {
                throw new \RuntimeException((string) __('finance.external_invoice.error.package_integrity', ['name' => $file['name']]));
            }
            $files[] = ['name' => $file['name'], 'content' => $content, 'mime' => $file['mime']];
        }

        return $files;
    }

    /**
     * @param  list<InvoiceSupportingDocument>  $documents
     * @return list<SupportingDocument>
     */
    private function supporting(array $documents): array {
        $result = [];
        foreach ($documents as $document) {
            $content = $document->is_embedded ? $this->documents->content($document) : null;
            if ($document->is_embedded && ($content === null || CryptoHelper::hash($content) !== $document->sha256)) {
                throw ValidationException::withMessages(['package' => __('invoicing.supporting_documents.error.integrity', ['reference' => $document->reference])]);
            }
            if ($content !== null && $document->mime === 'application/pdf'
                && File::withTemp($content, static fn (string $path): bool => PDFSecurityHelper::isEncrypted($path), 'eip-enc-', 'pdf')) {
                throw ValidationException::withMessages(['package' => __('invoicing.supporting_documents.error.encrypted')]);
            }
            $result[] = new SupportingDocument(
                $document->reference,
                $document->description,
                $document->external_url,
                $content,
                $content !== null ? $document->mime : null,
                $content !== null ? $document->filename : null,
            );
        }

        return $result;
    }

    /** @param  list<SupportingDocument>  $supporting */
    private function augment(ExternalInvoiceFormat $format, string $original, array $supporting): string {
        try {
            if ($format->isXml()) {
                return (new SupportingDocumentAugmenter)->augment($original, $supporting);
            }

            return (string) File::withTemp($original, static fn (string $path): string => (new ZugferdPdfAugmenter)->augment($path, $supporting), 'eip-zf-', 'pdf');
        } catch (AugmentException $e) {
            throw $this->fail($e->getReason()->value);
        }
    }

    /**
     * Gegenprobe: alles außer den neuen Unterlagen gleich, Regeln und Schema grün.
     */
    private function assertSameBusinessContent(ExternalInvoiceFormat $format, string $original, string $augmented, int $added): void {
        $before = $this->parse($format, $original);
        $after = $this->parse($format, $augmented);
        if ($before === null || $after === null) {
            throw $this->fail('unreadable');
        }
        if ($this->fingerprint($before) !== $this->fingerprint($after)
            || count($after->getSupportingDocuments()) !== count($before->getSupportingDocuments()) + $added) {
            throw $this->fail('content_changed');
        }

        $rules = (new DocumentRulesValidator)->validate($after);
        if ($rules->hasErrors()) {
            throw ValidationException::withMessages(['package' => __('finance.external_invoice.error.rules', ['messages' => implode(' ', array_map('strval', $rules->getErrors()))])]);
        }

        $xml = $format === ExternalInvoiceFormat::Zugferd ? (string) (new ZugferdPdfParser)->extractXmlFromString($augmented) : $augmented;
        $schema = $format === ExternalInvoiceFormat::XRechnungUbl ? new UblSchemaValidator : new CiiSchemaValidator;
        if ($schema->isAvailable()) {
            $errors = $schema->validate($xml);
            if ($errors !== []) {
                throw ValidationException::withMessages(['package' => __('finance.external_invoice.error.schema', ['messages' => Str::limit(implode(' ', array_map('strval', $errors)), 500)])]);
            }
        }
    }

    private function parse(ExternalInvoiceFormat $format, string $content): ?Document {
        try {
            if ($format === ExternalInvoiceFormat::Zugferd) {
                $xml = (new ZugferdPdfParser)->extractXmlFromString($content);

                return $xml === null ? null : (new ERechnungParser)->parse($xml);
            }
            if ($format->isXml()) {
                return (new ERechnungParser)->parse($content);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /** @return array<string, mixed> Geschäftsinhalt, der durch die Ergänzung nicht berührt werden darf */
    private function fingerprint(Document $document): array {
        return [
            'id' => $document->getId(),
            'date' => $document->getIssueDate()->format('Y-m-d'),
            'type' => $document->getInvoiceType()->value,
            'currency' => $document->getCurrency()->value,
            'seller' => $document->getSeller()->getName(),
            'buyer' => $document->getBuyer()->getName(),
            'buyer_reference' => $document->getBuyerReference(),
            'lines' => count($document->getLines()),
            'net' => $document->getNetAmount()->getAmount(),
            'tax' => $document->getTaxAmount()->getAmount(),
            'gross' => $document->getGrossAmount()->getAmount(),
            'payable' => $document->getPayableAmount()->getAmount(),
        ];
    }

    private function reason(string $code): string {
        $key = 'finance.external_invoice.blocked.' . $code;
        $text = __($key);

        return $text !== $key ? (string) $text : (string) __('finance.external_invoice.blocked.other', ['code' => $code]);
    }

    private function fail(string $code): ValidationException {
        return ValidationException::withMessages(['package' => $this->reason($code)]);
    }
}
