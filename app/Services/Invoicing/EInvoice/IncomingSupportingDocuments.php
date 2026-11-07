<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingSupportingDocuments.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\EInvoice;

use App\Enums\Whistleblowing\AttachmentScanStatus;
use App\Models\Invoicing\IncomingEInvoice;
use App\Services\Attachments\FileAttacher;
use App\Services\Security\Scanning\ScanDriver;
use CommonToolkit\Helper\Data\{CryptoHelper, NumberHelper};
use CommonToolkit\Helper\FileSystem\File;
use ERechnungToolkit\Entities\SupportingDocument;
use ERechnungToolkit\Enums\AttachmentMimeCode;
use ERechnungToolkit\Parsers\{ERechnungParser, ZugferdPdfParser};
use PDFToolkit\Helper\PDFSecurityHelper;
use Throwable;

/**
 * Eingebettete Unterlagen einer eingehenden E-Rechnung (BG-24, Feature 196):
 * aus der XML lösen, prüfen und als Begleitdateien am Eingang ablegen. Die
 * XML führt; weitere Dateien eines ZUGFeRD-PDF werden nur mit Namen vermerkt.
 * Links (BT-124) werden angezeigt, nie abgerufen.
 */
final class IncomingSupportingDocuments {
    /** Gesamtgrenze je Eingang (ZeRN Niedersachsen: 50 MB für alles). */
    public const MAX_TOTAL_BYTES = 50 * 1024 * 1024;

    /** Bund, Länder und Peppol-Empfänger nennen 200 Anlagen je Rechnung. */
    public const MAX_DOCUMENTS = 200;

    public const META_TYPE = 'einvoice_supporting_document';

    /** Dateinamen der Rechnungs-XML in ZUGFeRD, Factur-X und XRechnung-PDF. */
    private const INVOICE_XML_NAMES = ['factur-x.xml', 'zugferd-invoice.xml', 'xrechnung.xml', 'order-x.xml'];

    public function __construct(
        private readonly FileAttacher $attacher,
        private readonly RecipientProfiles $profiles,
    ) {}

    /**
     * Löst die Unterlagen und legt sie ab; liefert den Block für die Zusammenfassung.
     *
     * @return array{documents: list<array<string, mixed>>, notices: list<string>, pdf_attachments: list<string>}
     */
    public function extract(IncomingEInvoice $incoming, string $xml, ?string $pdfContents, ?int $actorId): array {
        $limit = max(1, (int) $this->profiles->setting('einvoice.incoming_attachment_limit_mb', $incoming->organization)) * 1024 * 1024;
        $parser = new ERechnungParser(maxAttachmentBytes: $limit, maxAttachments: self::MAX_DOCUMENTS);

        try {
            $document = $parser->parse($xml);
        } catch (Throwable) {
            return ['documents' => [], 'notices' => [], 'pdf_attachments' => []];
        }

        // Die Hinweise des Parsers sind deutsch; die App vermerkt die übersprungenen Unterlagen selbst.
        $notices = [];
        $rows = [];
        $total = 0;
        $seen = [];
        foreach ($document->getSupportingDocuments() as $supporting) {
            $row = $this->row($supporting);
            $content = $supporting->getContent();
            if ($content !== null && $content !== '') {
                $hash = (string) CryptoHelper::hash($content);
                $row['sha256'] = $hash;
                if (isset($seen[$hash])) {
                    $row['duplicate'] = true;
                } elseif ($total + strlen($content) > self::MAX_TOTAL_BYTES) {
                    $row['skipped'] = 'total_limit';
                    $notices[] = (string) __('invoicing.incoming_documents.notice.total_limit', ['name' => $row['filename'] ?? $row['reference']]);
                } else {
                    $total += strlen($content);
                    $seen[$hash] = true;
                    $row = $this->store($incoming, $supporting, $content, $row, $actorId);
                }
            } elseif ($supporting->getMimeCode() !== null || $supporting->getFilename() !== null) {
                $row['skipped'] = 'limit';
                $notices[] = (string) __('invoicing.incoming_documents.notice.unreadable', [
                    'name' => $row['filename'] ?? $row['reference'],
                    'limit' => NumberHelper::formatBytes($limit, 0),
                ]);
            }
            $rows[] = $row;
        }

        return [
            'documents' => $rows,
            'notices' => $notices,
            'pdf_attachments' => $pdfContents !== null ? $this->pdfAttachmentNames($pdfContents) : [],
        ];
    }

    /** @return array<string, mixed> */
    private function row(SupportingDocument $document): array {
        $uri = $document->getExternalUri();

        return [
            'reference' => mb_substr($document->getReference(), 0, 191),
            'description' => $document->getDescription() !== null ? mb_substr($document->getDescription(), 0, 255) : null,
            'filename' => $document->getFilename() !== null ? mb_substr($document->getFilename(), 0, 255) : null,
            'mime' => $document->getMimeCode(),
            'size' => $document->sizeBytes(),
            'external_url' => $uri !== null && preg_match('#^https?://#i', $uri) === 1 ? mb_substr($uri, 0, 2048) : null,
            'attachment_id' => null,
            'mime_mismatch' => false,
            'encrypted' => false,
            'infected' => false,
            'duplicate' => false,
            'skipped' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function store(IncomingEInvoice $incoming, SupportingDocument $document, string $content, array $row, ?int $actorId): array {
        $declared = $document->getMimeCodeEnum();
        $detected = File::mimeTypeFromContent($content);
        $row['mime_mismatch'] = $declared === null || ($detected !== false && ! $declared->acceptsDetected($detected));

        try {
            $verdict = File::withTemp($content, static fn (string $path): mixed => app(ScanDriver::class)->scan($path, $detected !== false ? $detected : null), 'isd-scan-');
        } catch (Throwable) {
            $verdict = null; // ohne Urteil weiter wie beim Original: der Eingang wird nie automatisch gebucht
        }
        if ($verdict === AttachmentScanStatus::Rejected) {
            $row['infected'] = true;

            return $row;
        }
        if ($declared === AttachmentMimeCode::PDF || $detected === 'application/pdf') {
            $row['encrypted'] = File::withTemp($content, static fn (string $path): bool => PDFSecurityHelper::isEncrypted($path), 'isd-pdf-', 'pdf');
        }

        $name = (string) ($document->getFilename() ?? $document->getReference());
        $attachment = $this->attacher->storeContent(
            $incoming,
            $content,
            $name !== '' ? $name : 'unterlage',
            $document->getMimeCode(),
            $actorId,
            ['organization_id' => $incoming->organization_id, 'meta_type' => self::META_TYPE],
        );
        $row['attachment_id'] = $attachment->id;

        return $row;
    }

    /** @return list<string> */
    private function pdfAttachmentNames(string $pdf): array {
        $parser = new ZugferdPdfParser;
        if (! $parser->isAvailable()) {
            return [];
        }

        try {
            $names = File::withTemp($pdf, static fn (string $path): array => $parser->listAttachments($path), 'isd-zf-', 'pdf');
        } catch (Throwable) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn ($name): string => mb_substr((string) $name, 0, 255), (array) $names),
            static fn (string $name): bool => $name !== '' && ! in_array(mb_strtolower($name), self::INVOICE_XML_NAMES, true),
        ));
    }
}
