<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillDocumentPullService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Easybill\Services;

use App\Enums\Document\{DocumentStatus, DocumentType};
use App\Models\{Document, ExternalReference, User};
use App\Models\Finance\BillingTransfer;
use App\Plugins\Easybill\Api\EasybillClientFactory;
use App\Plugins\Easybill\{EasybillConfig, EasybillPlugin};
use App\Services\Document\DocumentService;
use App\Services\Finance\Targets\EasybillTarget;
use CommonToolkit\Helper\Data\CryptoHelper;

/**
 * Rückabruf fertiggestellter easybill-Belege ins DMS (MVP-431, W1.3):
 * easybill stellt den Entwurf fertig (Nummer!), workDiary holt PDF bzw.
 * E-Rechnung (je file_format_config über /download) und hängt sie an den
 * BillingTransfer. Nachweis = sha256 + Abrufzeitpunkt in der
 * ExternalReference-Payload — bewusst KEINE Schemaänderung am DMS.
 * Je externem Beleg wird genau einmal gezogen (document_pulled_at).
 */
class EasybillDocumentPullService {
    public function __construct(
        private readonly EasybillClientFactory $clients,
        private readonly DocumentService $documents,
    ) {}

    /** @return array{checked: int, pulled: int, pending: int} */
    public function pull(int $organizationId): array {
        $config = EasybillConfig::resolve($organizationId);
        if (empty($config['api_key']) || ! $config['pull_documents']) {
            return ['checked' => 0, 'pulled' => 0, 'pending' => 0];
        }

        $references = ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', EasybillPlugin::ID)
            ->where('external_type', EasybillTarget::EXT_TYPE_INVOICE)
            ->get()
            ->filter(fn(ExternalReference $ref): bool => empty(((array) $ref->payload)['document_pulled_at'] ?? null));
        if ($references->isEmpty()) {
            return ['checked' => 0, 'pulled' => 0, 'pending' => 0];
        }

        $client = $this->clients->for($organizationId);
        $pulled = 0;
        $pending = 0;

        foreach ($references as $reference) {
            $remote = $client->document((string) $reference->external_id);

            // Solange easybill den Beleg nicht fertiggestellt hat (Entwurf),
            // gibt es weder finale Nummer noch archivwürdiges Dokument.
            if (($remote['is_draft'] ?? true) !== false) {
                $pending++;

                continue;
            }

            $transfer = $reference->referenceable;
            if (! $transfer instanceof BillingTransfer) {
                continue;
            }

            $format = (string) ($config['einvoice_format'] ?? '');
            $file = $format !== ''
                ? $client->downloadFile((string) $reference->external_id)
                : null;
            if ($file === null) {
                $content = $client->downloadPdf((string) $reference->external_id);
                $file = $content !== null ? ['content' => $content, 'mime' => 'application/pdf'] : null;
            }
            if ($file === null) {
                $pending++;

                continue;
            }

            $number = trim((string) ($remote['number'] ?? ''));
            $isXml = str_contains($file['mime'], 'xml');
            $document = $this->storeDocument($transfer, $file['content'], $file['mime'], $number, $isXml);

            // Vollaudit 2026-07 (N25): bei reinem XML-Format (z. B.
            // xrechnung3_0_xml) zusätzlich das PDF archivieren — zweite
            // Dokumentversion mit eigenem sha256 in der Nachweis-Payload.
            $pdfSha = null;
            if ($isXml) {
                $pdfContent = $client->downloadPdf((string) $reference->external_id);
                if ($pdfContent !== null) {
                    $actor = $transfer->creator ?? User::query()->findOrFail($transfer->created_by_user_id);
                    $pdfName = 'easybill-' . ($number !== '' ? $number : 'beleg-' . $transfer->getKey()) . '.pdf';
                    $this->documents->addVersionFromContents($document, $actor, $pdfContent, $pdfName, 'application/pdf');
                    $pdfSha = CryptoHelper::hash($pdfContent);
                }
            }

            $reference->forceFill([
                'payload' => array_merge((array) $reference->payload, array_filter([
                    'document' => $remote,
                    'document_pulled_at' => now()->toIso8601String(),
                    'document_sha256' => CryptoHelper::hash($file['content']),
                    'document_mime' => $file['mime'],
                    'document_pdf_sha256' => $pdfSha,
                    'dms_document_id' => $document->id,
                ], static fn($v): bool => $v !== null)),
                'synced_at' => now(),
            ])->save();
            $pulled++;
        }

        return ['checked' => $references->count(), 'pulled' => $pulled, 'pending' => $pending];
    }

    private function storeDocument(BillingTransfer $transfer, string $content, string $mime, string $number, bool $isXml): Document {
        /** @var User $actor Übergabe-Ersteller als Akteur des Konsolen-Abrufs. */
        $actor = $transfer->creator ?? User::query()->findOrFail($transfer->created_by_user_id);

        $document = Document::query()->create([
            'organization_id' => $transfer->organization_id,
            'documentable_type' => $transfer->getMorphClass(),
            'documentable_id' => $transfer->getKey(),
            'title' => (string) __('easybill-Rechnung :number', ['number' => $number !== '' ? $number : '—']),
            'document_type' => DocumentType::Invoice->value,
            'status' => DocumentStatus::Active->value,
            'description' => (string) __('Automatischer Rückabruf aus easybill (Übergabe #:id).', ['id' => $transfer->getKey()]),
            'created_by_user_id' => $actor->id,
        ]);

        $name = 'easybill-' . ($number !== '' ? $number : 'beleg-' . $transfer->getKey()) . ($isXml ? '.xml' : '.pdf');
        $this->documents->addVersionFromContents($document, $actor, $content, $name, $mime);

        return $document;
    }
}
