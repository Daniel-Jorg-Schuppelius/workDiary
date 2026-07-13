<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirrorProtocolObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Mirror\Observers;

use App\Enums\Protocol\ProtocolStatus;
use App\Models\Protocol;
use App\Plugins\Support\Mirror\{MirrorOutboxDispatcher, MirrorTargetRegistry};
use App\Services\Integration\IntegrationOutboxService;

/**
 * Reiht ein signiertes (abgeschlossenes) Protokoll je Ablage-Ziel in die
 * Integrations-Outbox ein, damit sein PDF gespiegelt wird (MVP-330, Bauturbo
 * A10 — gehoben aus dem WebDAV-Plugin, Feature 058/MVP-127, Rang 19) — nur
 * wenn die Anbindung die Quelle `protocol_pdf` aktiviert hat. Der
 * Idempotenzschlüssel bindet Protokoll + Revision; signierte Protokolle sind
 * unveränderlich, ein Re-Save löst also keinen zweiten Upload aus.
 */
class MirrorProtocolObserver {
    public function __construct(
        private readonly IntegrationOutboxService $outbox,
        private readonly MirrorTargetRegistry $targets,
    ) {}

    public function saved(Protocol $protocol): void {
        if ($protocol->status !== ProtocolStatus::Signed) {
            return;
        }

        foreach ($this->targets->all() as $target) {
            $connection = $target->activeConnection((int) $protocol->organization_id);
            if ($connection === null || ! $connection->mirrorsSource('protocol_pdf')) {
                continue;
            }

            $this->outbox->enqueue(
                (int) $protocol->organization_id,
                $target->pluginId(),
                MirrorOutboxDispatcher::OP_MIRROR_PROTOCOL,
                ['protocol_id' => $protocol->getKey(), 'revision' => $protocol->revision],
                $target->idempotencyKey('protocol-' . $protocol->getKey() . ':r' . $protocol->revision),
                $protocol,
            );
        }
    }
}
