<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrityAnchorService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Release;

use App\Enums\Backup\BackupTargetStatus;
use App\Models\Backup\BackupTargetConnection;
use App\Models\IntegrityCheck;
use App\Plugins\Contracts\BackupTarget;
use App\Services\Backup\BackupNaming;
use App\Services\Backup\Concerns\ResolvesBackupTarget;
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Support\Facades\Log;

/**
 * Externe Root-Hash-Verankerung (Feature 097, MVP-447): lädt nach jeder
 * Baseline-Erzeugung eine kleine Klartext-Ankerdatei `integrity-anchor.json`
 * (nur Hashes/Status — keine Geheimnisse) über den bestehenden
 * {@see BackupTarget}-Vertrag auf ein aktives Backupziel und liest sie beim
 * Verify zurück.
 *
 * Zweck: Der Fall „Angreifer ersetzt lokal Baseline **und** Prüfhistorie" ist
 * mit einer rein lokalen Kette nicht beweisbar — der externe Anker macht ihn
 * sichtbar. Kein konfiguriertes Ziel ⇒ Signal wird übersprungen (Hinweis,
 * kein Fehler); Upload-/Download-Fehler brechen den Prüflauf nie.
 */
class IntegrityAnchorService {
    use ResolvesBackupTarget;

    public const FILE_NAME = 'integrity-anchor.json';

    public const SCHEMA = 'workdiary.integrity-anchor/v1';

    public function __construct(private readonly BackupNaming $naming) {}

    /** Erstes aktives Backupziel; `null` = Verankerung nicht konfiguriert. */
    public function connection(): ?BackupTargetConnection {
        return BackupTargetConnection::query()
            ->where('status', BackupTargetStatus::Active->value)
            ->orderBy('id')
            ->first();
    }

    /** Remote-Name des Ankers (im eigenen Backupbereich der Installation). */
    public function remoteName(): string {
        return $this->naming->pseudonym() . '/' . self::FILE_NAME;
    }

    /**
     * Ankerinhalt aus Baseline + letztem Prüflauf.
     *
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    public function payload(array $manifest, ?IntegrityCheck $lastCheck = null): array {
        return [
            'schema' => self::SCHEMA,
            'generated_at' => now()->toIso8601String(),
            'root' => (string) ($manifest['root'] ?? ''),
            'baseline_source' => (string) ($manifest['source'] ?? ''),
            'baseline_generated_at' => (string) ($manifest['generated_at'] ?? ''),
            'last_status' => $lastCheck?->status->value,
            'last_findings_hash' => $lastCheck?->findings_hash,
            'last_ran_at' => $lastCheck?->ran_at?->toIso8601String(),
        ];
    }

    /**
     * Anker hochladen. `null` = übersprungen (kein Ziel/kein Adapter oder
     * Transportfehler) — der Aufrufer behandelt das als Hinweis, nie als
     * Fehlschlag des Integritätslaufs.
     *
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>|null  hochgeladener Ankerinhalt
     */
    public function push(array $manifest, ?IntegrityCheck $lastCheck = null, ?BackupTarget $adapter = null): ?array {
        $connection = $this->connection();
        if ($connection === null) {
            return null;
        }

        $payload = $this->payload($manifest, $lastCheck);
        $temporary = tempnam(sys_get_temp_dir(), 'wd-anchor-');
        if ($temporary === false) {
            return null;
        }

        try {
            file_put_contents($temporary, JsonHelper::encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $adapter ??= $this->adapter($connection);
            $adapter->backupEnsureFolder($connection, $this->naming->pseudonym());
            $adapter->backupUploadPart($connection, $temporary, $this->remoteName());

            return $payload;
        } catch (\Throwable $e) {
            // Verankerung ist ein Sekundärsignal — nie Fail-Ursache.
            Log::warning('integrity.anchor_push_failed', ['error' => $e->getMessage()]);

            return null;
        } finally {
            @unlink($temporary);
        }
    }

    /**
     * Anker zurücklesen.
     *
     * @return array<string, mixed>|null  `null` = nicht verfügbar
     */
    public function pull(?BackupTarget $adapter = null): ?array {
        $connection = $this->connection();
        if ($connection === null) {
            return null;
        }

        try {
            $adapter ??= $this->adapter($connection);
            $decoded = json_decode((string) $adapter->backupDownload($connection, $this->remoteName()), true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            Log::warning('integrity.anchor_pull_failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Vergleicht den externen Anker mit dem lokalen Stand.
     *
     * @param  array<string, mixed>  $manifest  lokale Baseline
     * @return array{state: 'skipped'|'unavailable'|'match'|'mismatch', issues: list<string>, remote_root: string|null}
     */
    public function compare(array $manifest, ?IntegrityCheck $lastCheck = null, ?BackupTarget $adapter = null): array {
        if ($this->connection() === null) {
            return ['state' => 'skipped', 'issues' => [], 'remote_root' => null];
        }

        $remote = $this->pull($adapter);
        if ($remote === null) {
            return [
                'state' => 'unavailable',
                'issues' => [(string) __('integrity.anchor.unavailable')],
                'remote_root' => null,
            ];
        }

        $issues = [];
        $remoteRoot = (string) ($remote['root'] ?? '');
        $localRoot = (string) ($manifest['root'] ?? '');

        if ($remoteRoot === '' || ! hash_equals($remoteRoot, $localRoot)) {
            $issues[] = (string) __('integrity.anchor.root_mismatch', [
                'remote' => mb_substr($remoteRoot, 0, 16) ?: '—',
                'local' => mb_substr($localRoot, 0, 16) ?: '—',
            ]);
        }

        // Prüfhistorie: ein zurückgesetzter lokaler Verlauf fällt hier auf.
        $remoteFindings = $remote['last_findings_hash'] ?? null;
        if (is_string($remoteFindings) && $remoteFindings !== ''
            && $lastCheck !== null && $lastCheck->findings_hash !== $remoteFindings) {
            $issues[] = (string) __('integrity.anchor.history_mismatch');
        }

        return [
            'state' => $issues === [] ? 'match' : 'mismatch',
            'issues' => $issues,
            'remote_root' => $remoteRoot !== '' ? $remoteRoot : null,
        ];
    }
}
