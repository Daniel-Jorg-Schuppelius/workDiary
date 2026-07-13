<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirrorBackfillCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Mirror\Console;

use App\Enums\Document\DocumentStatus;
use App\Models\{Document, Organization};
use App\Plugins\Support\Mirror\{MirrorOutboxDispatcher, MirrorTarget};
use App\Services\Integration\IntegrationOutboxService;
use Illuminate\Console\Command;

/**
 * Gemeinsamer Voll-Spiegellauf der Ablage-Ziele (MVP-330, Bauturbo A10 —
 * gehoben aus `webdav:mirror`, Feature 058/MVP-127): reiht alle aktuell
 * freigegebenen Dokumente (Status Active mit Version) je Organisation
 * idempotent in die Integrations-Outbox ein — Aufholpfad neben den
 * ereignisgetriebenen Freigabe-Observern. Läuft manuell aus der Admin-UI
 * (bewusst KEIN Scheduler-Registry-Eintrag, wie der WebDAV-Bestand).
 * Dedupe über den Idempotenzschlüssel des Ziels.
 */
abstract class MirrorBackfillCommand extends Command {
    /** Das Ablage-Ziel dieses Commands (WebDAV bzw. SharePoint). */
    abstract protected function target(): MirrorTarget;

    public function handle(IntegrationOutboxService $outbox): int {
        $target = $this->target();
        $orgId = $this->option('organization');
        $query = Organization::query();
        if ($orgId !== null && $orgId !== '') {
            $query->whereKey((int) $orgId);
        }

        foreach ($query->get() as $org) {
            app()->instance('currentOrganization', $org);

            if ($target->activeConnection((int) $org->id) === null) {
                continue;
            }

            $documents = Document::query()
                ->where('organization_id', $org->id)
                ->where('status', DocumentStatus::Active->value)
                ->whereNotNull('current_version_id')
                ->get();

            $queued = 0;
            foreach ($documents as $document) {
                $outbox->enqueue(
                    (int) $org->id,
                    $target->pluginId(),
                    MirrorOutboxDispatcher::OP_MIRROR,
                    ['document_id' => $document->getKey(), 'version_id' => $document->current_version_id, 'document_type' => $document->document_type->value],
                    $target->idempotencyKey('doc-' . $document->getKey() . ':v' . $document->current_version_id),
                    $document,
                );
                $queued++;
            }

            $this->info(sprintf('Organisation #%d (%s): %d Dokumente eingereiht.', $org->id, $org->name, $queued));
        }

        return self::SUCCESS;
    }
}
