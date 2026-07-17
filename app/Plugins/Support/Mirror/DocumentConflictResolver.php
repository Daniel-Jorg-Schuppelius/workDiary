<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentConflictResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Mirror;

use App\Models\{Document, DocumentVersion, ExternalReference, IntegrationInboxItem, User};
use App\Services\Document\DocumentService;
use App\Services\Integration\InboxActionService;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Löst einen Spiegelkonflikt eines Ablage-Ziels ({@see IntegrationInboxItem}
 * CASE_CONFLICT) auf eine von drei Arten auf (MVP-330, Bauturbo A10 — gehoben
 * aus dem WebDAV-Plugin, Feature 058/MVP-127, Rang 18) — jeweils auditiert
 * (fachliches Document-Audit + zentraler Inbox-Abschluss über
 * {@see InboxActionService::markResolved()}):
 *  - {@see overwrite()}: die externe Datei mit dem lokalen Stand überschreiben.
 *  - {@see importAsVersion()}: den externen Stand als neue lokale Version ziehen.
 *  - {@see detach()}: die Spiegelung dieses einen Dokuments dauerhaft trennen.
 */
class DocumentConflictResolver {
    public function __construct(private readonly InboxActionService $inbox) {}

    /** (a) „Remote überschreiben": lokaler Stand gewinnt, externe Änderung wird verworfen. */
    public function overwrite(MirrorTarget $target, IntegrationInboxItem $item): void {
        $document = $this->document($item);
        $connection = $this->connection($target, $item);
        $gateway = $target->gatewayFor($connection);

        app(DocumentMirrorService::class)->mirror($target, $document, $connection, $gateway, force: true);

        $document->audit($target->pluginId() . '.conflict.overwritten', ['external_id' => $item->external_id]);
        $this->inbox->markResolved($item, IntegrationInboxItem::STATUS_RESOLVED_LOCAL, $document);
    }

    /** (b) „Remote als neue lokale Version importieren": externer Stand gewinnt lokal. */
    public function importAsVersion(MirrorTarget $target, IntegrationInboxItem $item): void {
        $document = $this->document($item);
        $connection = $this->connection($target, $item);
        $gateway = $target->gatewayFor($connection);

        $path = (string) $item->external_id;
        $content = $gateway->getFile($path);
        if ($content === null) {
            throw new RuntimeException('Ablage-Download fehlgeschlagen.');
        }

        $current = $document->currentVersion;
        $name = $current instanceof DocumentVersion && $current->original_name !== '' ? $current->original_name : basename($path);
        $mime = $current instanceof DocumentVersion ? $current->mime : null;

        $version = app(DocumentService::class)->addVersionFromContents($document, $this->actor(), $content, $name, $mime, __($target->pluginId() . '.conflict.import_note'));

        // Referenz auf den importierten (= externen) Stand nachziehen, damit der
        // nächste Spiegellauf nicht sofort denselben Konflikt neu meldet.
        ExternalReference::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'plugin_id' => $target->pluginId(),
                'external_type' => DocumentMirrorService::EXTERNAL_TYPE,
                'referenceable_type' => $document->getMorphClass(),
                'referenceable_id' => $document->getKey(),
            ],
            [
                'organization_id' => $item->organization_id,
                'external_id' => $path,
                'payload' => [
                    'sha256' => CryptoHelper::hash($content),
                    'remote_path' => $path,
                    'remote_sig' => $gateway->remoteSignature($path),
                    'version_id' => (int) $version->getKey(),
                    'delivered_at' => now()->toIso8601String(),
                ],
                'synced_at' => now(),
            ],
        );

        $document->audit($target->pluginId() . '.conflict.imported', ['external_id' => $item->external_id, 'version_no' => $version->version_no]);
        $this->inbox->markResolved($item, IntegrationInboxItem::STATUS_RESOLVED_REMOTE, $document);
    }

    /** (c) „Spiegelung trennen": Referenz löschen + Marker setzen, Anbindung bleibt aktiv. */
    public function detach(MirrorTarget $target, IntegrationInboxItem $item): void {
        $document = $this->document($item);

        ExternalReference::query()->withoutGlobalScopes()
            ->where('organization_id', $item->organization_id)
            ->where('plugin_id', $target->pluginId())
            ->where('external_type', DocumentMirrorService::EXTERNAL_TYPE)
            ->where('referenceable_type', $document->getMorphClass())
            ->where('referenceable_id', $document->getKey())
            ->delete();

        // Marker setzen; der Observer prüft ihn ZUERST und reiht nichts mehr ein.
        $document->forceFill([$target->detachedAttribute() => true])->save();

        $document->audit($target->pluginId() . '.mirror.detached', ['external_id' => $item->external_id]);
        $this->inbox->markResolved($item, IntegrationInboxItem::STATUS_DISMISSED, $document);
    }

    private function document(IntegrationInboxItem $item): Document {
        $model = $item->referenceable;
        if (! $model instanceof Document) {
            throw new RuntimeException('Spiegelkonflikt ohne zugehöriges Dokument.');
        }

        return $model;
    }

    private function connection(MirrorTarget $target, IntegrationInboxItem $item): MirrorConnection {
        $connection = $target->activeConnection((int) $item->organization_id);
        if ($connection === null) {
            throw new RuntimeException('Keine aktive Ablage-Anbindung.');
        }

        return $connection;
    }

    private function actor(): User {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new RuntimeException('Kein angemeldeter Benutzer.');
        }

        return $user;
    }
}
