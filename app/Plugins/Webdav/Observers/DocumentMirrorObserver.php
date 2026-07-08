<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentMirrorObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Webdav\Observers;

use App\Enums\Document\DocumentStatus;
use App\Models\{Document, WebdavConnection};
use App\Plugins\Webdav\Services\{DocumentMirrorService, WebdavOutboxDispatcher};
use App\Services\Integration\IntegrationOutboxService;

/**
 * Reiht die Spiegelung eines freigegebenen Dokuments in die Integrations-Outbox
 * ein (Feature 058, MVP-127). „Freigegeben" = Status `Active` mit einer
 * aktuellen Version. Der Idempotenzschlüssel bindet Dokument + Version — reine
 * Metadaten-Änderungen (gleiche Version) lösen keinen erneuten Upload aus, eine
 * neue Version schon. Nur bei vorhandener aktiver WebDAV-Ablage der Organisation.
 */
class DocumentMirrorObserver {
    public function __construct(private readonly IntegrationOutboxService $outbox) {}

    public function saved(Document $document): void {
        // Für dieses Dokument wurde die Spiegelung bewusst getrennt (Rang 18) →
        // nie wieder automatisch einreihen.
        if ($document->webdav_mirror_detached) {
            return;
        }
        if ($document->status !== DocumentStatus::Active || $document->current_version_id === null) {
            return;
        }

        $hasAblage = WebdavConnection::query()
            ->where('organization_id', $document->organization_id)
            ->where('active', true)
            ->exists();
        if (! $hasAblage) {
            return;
        }

        $this->outbox->enqueue(
            (int) $document->organization_id,
            DocumentMirrorService::PLUGIN_ID,
            WebdavOutboxDispatcher::OP_MIRROR,
            [
                'document_id' => $document->getKey(),
                'version_id' => $document->current_version_id,
                'document_type' => $document->document_type->value,
            ],
            'mirror:doc-' . $document->getKey() . ':v' . $document->current_version_id,
            $document,
        );
    }
}
