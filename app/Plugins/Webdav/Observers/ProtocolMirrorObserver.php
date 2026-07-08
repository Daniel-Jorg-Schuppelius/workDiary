<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolMirrorObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Webdav\Observers;

use App\Enums\Protocol\ProtocolStatus;
use App\Models\{Protocol, WebdavConnection};
use App\Plugins\Webdav\Services\{DocumentMirrorService, WebdavOutboxDispatcher};
use App\Services\Integration\IntegrationOutboxService;

/**
 * Reiht ein signiertes (abgeschlossenes) Protokoll in die Integrations-Outbox
 * ein, damit sein PDF in die WebDAV-Ablage gespiegelt wird (Feature 058,
 * MVP-127, Rang 19) — nur wenn die Anbindung die Quelle `protocol_pdf` aktiviert
 * hat. Idempotenzschlüssel bindet Protokoll + Revision; signierte Protokolle sind
 * unveränderlich, ein Re-Save löst also keinen zweiten Upload aus.
 */
class ProtocolMirrorObserver {
    public function __construct(private readonly IntegrationOutboxService $outbox) {}

    public function saved(Protocol $protocol): void {
        if ($protocol->status !== ProtocolStatus::Signed) {
            return;
        }

        $connection = WebdavConnection::query()
            ->where('organization_id', $protocol->organization_id)
            ->where('active', true)
            ->first();
        if (! $connection instanceof WebdavConnection || ! $connection->mirrorsSource('protocol_pdf')) {
            return;
        }

        $this->outbox->enqueue(
            (int) $protocol->organization_id,
            DocumentMirrorService::PLUGIN_ID,
            WebdavOutboxDispatcher::OP_MIRROR_PROTOCOL,
            ['protocol_id' => $protocol->getKey(), 'revision' => $protocol->revision],
            'mirror:protocol-' . $protocol->getKey() . ':r' . $protocol->revision,
            $protocol,
        );
    }
}
