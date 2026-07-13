<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirrorDocumentObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Mirror\Observers;

use App\Enums\Document\DocumentStatus;
use App\Models\Document;
use App\Plugins\Support\Mirror\{MirrorOutboxDispatcher, MirrorTargetRegistry};
use App\Services\Integration\IntegrationOutboxService;

/**
 * Reiht die Spiegelung eines freigegebenen Dokuments je Ablage-Ziel in die
 * Integrations-Outbox ein (MVP-330, Bauturbo A10 — gehoben aus dem WebDAV-
 * Plugin, Feature 058/MVP-127). „Freigegeben" = Status `Active` mit einer
 * aktuellen Version. Der Idempotenzschlüssel bindet Ziel + Dokument + Version —
 * reine Metadaten-Änderungen (gleiche Version) lösen keinen erneuten Upload
 * aus, eine neue Version schon. Nur bei vorhandener aktiver Ablage der
 * Organisation und nicht getrennter Spiegelung (Rang 18) des Ziels.
 */
class MirrorDocumentObserver {
    public function __construct(
        private readonly IntegrationOutboxService $outbox,
        private readonly MirrorTargetRegistry $targets,
    ) {}

    public function saved(Document $document): void {
        if ($document->status !== DocumentStatus::Active || $document->current_version_id === null) {
            return;
        }

        foreach ($this->targets->all() as $target) {
            // Für dieses Dokument wurde die Spiegelung DIESES Ziels bewusst
            // getrennt (Rang 18) → nie wieder automatisch einreihen.
            if ((bool) $document->getAttribute($target->detachedAttribute())) {
                continue;
            }
            if ($target->activeConnection((int) $document->organization_id) === null) {
                continue;
            }

            $this->outbox->enqueue(
                (int) $document->organization_id,
                $target->pluginId(),
                MirrorOutboxDispatcher::OP_MIRROR,
                [
                    'document_id' => $document->getKey(),
                    'version_id' => $document->current_version_id,
                    'document_type' => $document->document_type->value,
                ],
                $target->idempotencyKey('doc-' . $document->getKey() . ':v' . $document->current_version_id),
                $document,
            );
        }
    }
}
