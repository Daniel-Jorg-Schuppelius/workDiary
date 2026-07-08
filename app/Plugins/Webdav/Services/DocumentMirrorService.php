<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentMirrorService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Webdav\Services;

use App\Models\{Document, DocumentVersion, ExternalReference, IntegrationInboxItem, WebdavConnection};
use App\Plugins\Webdav\Contracts\WebdavGateway;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Spiegelt ein freigegebenes DMS-Dokument in eine externe WebDAV-Ablage
 * (Feature 058, MVP-127). WorkDiary bleibt führend (kein Rückkanal).
 *
 * - **Übergabenachweis + Idempotenz** über {@see ExternalReference} (Plugin
 *   `webdav`, Typ `dms_object`): Payload trägt SHA-256 des Inhalts, Zielpfad,
 *   Server-Signatur (ETag) und Zeitpunkt. Unveränderter Inhalt → nichts zu tun.
 * - **Konflikt statt stiller Übernahme**: Wurde die externe Datei seit unserer
 *   letzten Spiegelung fremdverändert (Signatur weicht ab), wird NICHT
 *   überschrieben, sondern ein sichtbarer {@see IntegrationInboxItem}
 *   (CASE_CONFLICT) angelegt (DoD 058).
 * - Transiente Zustellfehler werfen — die Outbox wiederholt sie.
 */
class DocumentMirrorService {
    public const PLUGIN_ID = 'webdav';

    public const EXTERNAL_TYPE = 'dms_object';

    public const RESULT_MIRRORED = 'mirrored';

    public const RESULT_UNCHANGED = 'unchanged';

    public const RESULT_CONFLICT = 'conflict';

    public const RESULT_SKIPPED = 'skipped';

    /**
     * @param  bool  $force  Konfliktprüfung überspringen und die externe Datei
     *                       bewusst überschreiben (Konfliktauflösung „Remote
     *                       überschreiben", Rang 18).
     */
    public function mirror(Document $document, WebdavConnection $connection, WebdavGateway $gateway, bool $force = false): string {
        // Spiegelung für dieses Dokument getrennt (Rang 18) → nichts tun.
        if ($document->webdav_mirror_detached) {
            return self::RESULT_SKIPPED;
        }

        $version = $document->currentVersion;
        if (! $version instanceof DocumentVersion) {
            return self::RESULT_SKIPPED; // kein Dateiinhalt
        }

        $disk = Storage::disk((string) $version->disk);
        if (! $disk->exists((string) $version->path)) {
            return self::RESULT_SKIPPED; // lokale Datei fehlt (Datenproblem, nicht transient)
        }

        $contents = (string) $disk->get((string) $version->path);
        $relativePath = $connection->relativePathFor($document->document_type->value, (int) $document->getKey(), (string) $version->original_name);

        return $this->deliverAndRecord(
            $document,
            self::EXTERNAL_TYPE,
            $relativePath,
            $contents,
            (string) $version->mime,
            (string) $document->title,
            $connection,
            $gateway,
            $force,
            ['version_id' => (int) $version->getKey()],
        );
    }

    /**
     * Spiegelt bereits erzeugte Bytes (z. B. ein gerendertes Rechnungs-/Protokoll-
     * PDF, Rang 19) unter einem eigenen `$externalType` idempotent — teilt sich
     * mit {@see mirror()} den Konflikt-/Referenz-/Zustell-Kern.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $morph  lokale Herkunft (Invoice/Protocol)
     */
    public function mirrorBytes(Model $morph, string $externalType, string $relativePath, string $bytes, string $mime, string $displayTitle, WebdavConnection $connection, WebdavGateway $gateway, bool $force = false): string {
        return $this->deliverAndRecord($morph, $externalType, $relativePath, $bytes, $mime, $displayTitle, $connection, $gateway, $force, []);
    }

    /**
     * Gemeinsamer Spiegel-Kern: unverändert-Kurzschluss (SHA), Konflikt bei
     * externer Änderung (kein stilles Überschreiben, außer $force), Zustellung,
     * ExternalReference-Nachweis + Zeitstempel. Quellenneutral über den Morph.
     *
     * @param  array<string, mixed>  $extraPayload
     */
    private function deliverAndRecord(Model $morph, string $externalType, string $relativePath, string $contents, string $mime, string $displayTitle, WebdavConnection $connection, WebdavGateway $gateway, bool $force, array $extraPayload): string {
        $sha = hash('sha256', $contents);

        $ref = ExternalReference::query()->withoutGlobalScopes()
            ->where('organization_id', $connection->organization_id)
            ->where('plugin_id', self::PLUGIN_ID)
            ->where('external_type', $externalType)
            ->where('referenceable_type', $morph->getMorphClass())
            ->where('referenceable_id', $morph->getKey())
            ->first();

        // Bereits gespiegelt und inhaltlich unverändert → nichts zu tun.
        if ($ref instanceof ExternalReference && ($ref->payload['sha256'] ?? null) === $sha) {
            return self::RESULT_UNCHANGED;
        }

        // Inhalt geändert und Datei war schon gespiegelt: externe Änderung prüfen.
        // Bei $force (Konfliktauflösung „Remote überschreiben") übersprungen.
        if (! $force && $ref instanceof ExternalReference) {
            $recordedSig = $ref->payload['remote_sig'] ?? null;
            $currentSig = $gateway->remoteSignature($relativePath);
            if (is_string($recordedSig) && is_string($currentSig) && $currentSig !== $recordedSig) {
                $this->raiseConflict($morph, $externalType, $connection, $relativePath, $sha, $recordedSig, $currentSig, $displayTitle);

                return self::RESULT_CONFLICT; // nie stilles Überschreiben (DoD)
            }
        }

        $this->deliver($gateway, $relativePath, $contents, $mime);

        $newSig = $gateway->remoteSignature($relativePath);
        ExternalReference::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'plugin_id' => self::PLUGIN_ID,
                'external_type' => $externalType,
                'referenceable_type' => $morph->getMorphClass(),
                'referenceable_id' => $morph->getKey(),
            ],
            [
                'organization_id' => $connection->organization_id,
                'external_id' => $relativePath,
                'payload' => array_merge([
                    'sha256' => $sha,
                    'remote_path' => $relativePath,
                    'remote_sig' => $newSig,
                    'delivered_at' => Carbon::now()->toIso8601String(),
                ], $extraPayload),
                'synced_at' => Carbon::now(),
            ],
        );

        $connection->forceFill(['last_mirrored_at' => Carbon::now()])->save();

        return self::RESULT_MIRRORED;
    }

    /** Ordner sicherstellen + Datei hochladen; wirft bei transientem Zustellfehler (Outbox-Retry). */
    private function deliver(WebdavGateway $gateway, string $relativePath, string $contents, string $mime): void {
        $folder = trim(str_contains($relativePath, '/') ? substr($relativePath, 0, (int) strrpos($relativePath, '/')) : '', '/');
        if ($folder !== '' && ! $gateway->ensureCollection($folder)) {
            throw new RuntimeException('WebDAV-Ordner nicht erstellbar');
        }
        if (! $gateway->putFile($relativePath, $contents, $mime)) {
            throw new RuntimeException('WebDAV-Upload fehlgeschlagen');
        }
    }

    private function raiseConflict(Model $morph, string $externalType, WebdavConnection $connection, string $path, string $localSha, string $recordedSig, string $currentSig, string $displayTitle): void {
        IntegrationInboxItem::query()->withoutGlobalScopes()->firstOrCreate(
            [
                'organization_id' => $connection->organization_id,
                'plugin_id' => self::PLUGIN_ID,
                'dedupe_key' => 'webdav-conflict:' . $externalType . '-' . $morph->getKey() . ':' . $currentSig,
            ],
            [
                'source' => self::PLUGIN_ID,
                'target_type' => $morph->getMorphClass(),
                'external_type' => $externalType,
                'external_id' => $path,
                'case_type' => IntegrationInboxItem::CASE_CONFLICT,
                'status' => IntegrationInboxItem::STATUS_OPEN,
                'referenceable_type' => $morph->getMorphClass(),
                'referenceable_id' => $morph->getKey(),
                'local_snapshot' => ['sha256' => $localSha, 'remote_path' => $path],
                'remote_snapshot' => ['recorded_sig' => $recordedSig, 'current_sig' => $currentSig],
                'display_title' => $displayTitle,
                'display_subtitle' => __('webdav.conflict.subtitle'),
                'occurred_at' => Carbon::now(),
            ],
        );
    }
}
