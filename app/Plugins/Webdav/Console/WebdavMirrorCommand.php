<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebdavMirrorCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Webdav\Console;

use App\Enums\Document\DocumentStatus;
use App\Models\{Document, Organization, WebdavConnection};
use App\Plugins\Webdav\Services\{DocumentMirrorService, WebdavOutboxDispatcher};
use App\Services\Integration\IntegrationOutboxService;
use Illuminate\Console\Command;

/**
 * Voll-Spiegellauf (Feature 058, MVP-127): reiht alle aktuell freigegebenen
 * Dokumente (Status Active mit Version) je Organisation idempotent in die
 * Integrations-Outbox ein — Aufholpfad neben dem ereignisgetriebenen
 * {@see \App\Plugins\Webdav\Observers\DocumentMirrorObserver}. Läuft im
 * Scheduler und manuell aus der Admin-UI. Dedupe über den Idempotenzschlüssel.
 */
class WebdavMirrorCommand extends Command {
    protected $signature = 'webdav:mirror
        {--organization= : ID einer einzelnen Organisation, sonst alle}';

    protected $description = 'Spiegelt alle freigegebenen Dokumente je Organisation in die WebDAV-Ablage (idempotent über die Outbox).';

    public function handle(IntegrationOutboxService $outbox): int {
        $orgId = $this->option('organization');
        $query = Organization::query();
        if ($orgId !== null && $orgId !== '') {
            $query->whereKey((int) $orgId);
        }

        foreach ($query->get() as $org) {
            app()->instance('currentOrganization', $org);

            $hasAblage = WebdavConnection::query()
                ->where('organization_id', $org->id)
                ->where('active', true)
                ->exists();
            if (! $hasAblage) {
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
                    DocumentMirrorService::PLUGIN_ID,
                    WebdavOutboxDispatcher::OP_MIRROR,
                    ['document_id' => $document->getKey(), 'version_id' => $document->current_version_id, 'document_type' => $document->document_type->value],
                    'mirror:doc-' . $document->getKey() . ':v' . $document->current_version_id,
                    $document,
                );
                $queued++;
            }

            $this->info(sprintf('Organisation #%d (%s): %d Dokumente eingereiht.', $org->id, $org->name, $queued));
        }

        return self::SUCCESS;
    }
}
