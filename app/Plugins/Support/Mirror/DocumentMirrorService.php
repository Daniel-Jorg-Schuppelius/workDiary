<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentMirrorService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Mirror;

use App\Models\{Document, DocumentVersion, ExternalReference, IntegrationInboxItem};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Gemeinsamer Spiegel-Kern der Ablage-Ziele (MVP-330, Bauturbo A10 — gehoben
 * aus dem WebDAV-Plugin, Feature 058/MVP-127; Semantik unverändert). Spiegelt
 * ein freigegebenes DMS-Dokument bzw. gerenderte PDF-Bytes in eine externe
 * Ablage. WorkDiary bleibt führend (kein Rückkanal).
 *
 * - **Übergabenachweis + Idempotenz** über {@see ExternalReference} (Plugin-ID
 *   des Ziels, Typ `dms_object` bzw. Quelle): Payload trägt SHA-256 des
 *   Inhalts, Zielpfad, Server-Signatur (ETag/cTag) und Zeitpunkt.
 *   Unveränderter Inhalt → nichts zu tun.
 * - **Konflikt statt stiller Übernahme**: Wurde die externe Datei seit unserer
 *   letzten Spiegelung fremdverändert (Signatur weicht ab), wird NICHT
 *   überschrieben, sondern ein sichtbarer {@see IntegrationInboxItem}
 *   (CASE_CONFLICT) angelegt (DoD 058).
 * - Transiente Zustellfehler werfen — die Outbox wiederholt sie.
 */
class DocumentMirrorService {
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
    public function mirror(MirrorTarget $target, Document $document, MirrorConnection $connection, RemoteFileGateway $gateway, bool $force = false): string {
        // Spiegelung für dieses Dokument in DIESEM Zweig getrennt (Rang 18) → nichts tun.
        if ((bool) $document->getAttribute($target->detachedAttribute())) {
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
            $target->pluginId(),
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
    public function mirrorBytes(MirrorTarget $target, Model $morph, string $externalType, string $relativePath, string $bytes, string $mime, string $displayTitle, MirrorConnection $connection, RemoteFileGateway $gateway, bool $force = false): string {
        return $this->deliverAndRecord($target->pluginId(), $morph, $externalType, $relativePath, $bytes, $mime, $displayTitle, $connection, $gateway, $force, []);
    }

    /**
     * Gemeinsamer Spiegel-Kern: unverändert-Kurzschluss (SHA), Konflikt bei
     * externer Änderung (kein stilles Überschreiben, außer $force), Zustellung,
     * ExternalReference-Nachweis + Zeitstempel. Quellenneutral über den Morph.
     *
     * @param  array<string, mixed>  $extraPayload
     */
    private function deliverAndRecord(string $pluginId, Model $morph, string $externalType, string $relativePath, string $contents, string $mime, string $displayTitle, MirrorConnection $connection, RemoteFileGateway $gateway, bool $force, array $extraPayload): string {
        $sha = hash('sha256', $contents);

        $ref = ExternalReference::query()->withoutGlobalScopes()
            ->where('organization_id', $connection->organizationId())
            ->where('plugin_id', $pluginId)
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
                $this->raiseConflict($pluginId, $morph, $externalType, $connection, $relativePath, $sha, $recordedSig, $currentSig, $displayTitle);

                return self::RESULT_CONFLICT; // nie stilles Überschreiben (DoD)
            }
        }

        $this->deliver($gateway, $relativePath, $contents, $mime);

        $newSig = $gateway->remoteSignature($relativePath);
        ExternalReference::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'plugin_id' => $pluginId,
                'external_type' => $externalType,
                'referenceable_type' => $morph->getMorphClass(),
                'referenceable_id' => $morph->getKey(),
            ],
            [
                'organization_id' => $connection->organizationId(),
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

        $connection->markMirrored();

        return self::RESULT_MIRRORED;
    }

    /** Ordner sicherstellen + Datei hochladen; wirft bei transientem Zustellfehler (Outbox-Retry). */
    private function deliver(RemoteFileGateway $gateway, string $relativePath, string $contents, string $mime): void {
        $folder = trim(str_contains($relativePath, '/') ? substr($relativePath, 0, (int) strrpos($relativePath, '/')) : '', '/');
        if ($folder !== '' && ! $gateway->ensureCollection($folder)) {
            throw new RuntimeException('Ablage-Ordner nicht erstellbar');
        }
        if (! $gateway->putFile($relativePath, $contents, $mime)) {
            throw new RuntimeException('Ablage-Upload fehlgeschlagen');
        }
    }

    private function raiseConflict(string $pluginId, Model $morph, string $externalType, MirrorConnection $connection, string $path, string $localSha, string $recordedSig, string $currentSig, string $displayTitle): void {
        IntegrationInboxItem::query()->withoutGlobalScopes()->firstOrCreate(
            [
                'organization_id' => $connection->organizationId(),
                'plugin_id' => $pluginId,
                'dedupe_key' => $pluginId . '-conflict:' . $externalType . '-' . $morph->getKey() . ':' . $currentSig,
            ],
            [
                'source' => $pluginId,
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
                'display_subtitle' => __($pluginId . '.conflict.subtitle'),
                'occurred_at' => Carbon::now(),
            ],
        );
    }
}
