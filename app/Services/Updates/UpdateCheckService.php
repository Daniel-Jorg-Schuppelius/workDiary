<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UpdateCheckService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Updates;

use App\Enums\Operations\{OperationsTaskSeverity, OperationsTaskType};
use App\Models\{ComponentUpdate, PluginState};
use App\Services\Operations\{OperationsAlertService, OperationsSignal};
use App\Services\Release\ReleaseManifestService;
use App\Support\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{Cache, Log};
use RuntimeException;

/**
 * Update-Verfügbarkeitsprüfung (Feature 022, MVP-054): liest ein
 * Ed25519-signiertes Feed-Dokument (Remote-URL oder Offline-Import),
 * gleicht es mit installierten Versionen ab und pflegt
 * component_updates. KEIN Self-Update — nur erkennen, erklären, melden.
 *
 * Externe Kommunikation ist Opt-in (updates.check_mode): disabled |
 * manual (nur Button/Offline-Import) | auto (täglicher Registry-Job).
 * Die Anfrage überträgt KEINE Installationsdaten (reiner GET auf den
 * Feed); Modus + letzte Prüfung erscheinen im Supportbericht.
 *
 * Feed-Format: {"payload": "<json-string>", "signature": "<base64>",
 * "algorithm": "ed25519"} — signiert wird der exakte payload-String
 * (keine Kanonisierung nötig). payload enthält
 * {"generated_at": ..., "components": [{key, type, channel, version,
 * classification, min_app_version, max_app_version, changelog_url,
 * requires}]}.
 */
class UpdateCheckService {
    public const MODE_DISABLED = 'disabled';

    public const MODE_MANUAL = 'manual';

    public const MODE_AUTO = 'auto';

    public const LAST_CHECK_CACHE_KEY = 'updates.last_check_at';

    public function __construct(
        private readonly ReleaseManifestService $release,
        private readonly OperationsAlertService $alerts,
        private readonly \App\Plugins\Support\PluginHttpFactory $http,
    ) {}

    public function mode(): string {
        $mode = (string) Setting::get('updates.check_mode', self::MODE_MANUAL);

        return in_array($mode, [self::MODE_DISABLED, self::MODE_MANUAL, self::MODE_AUTO], true)
            ? $mode
            : self::MODE_MANUAL;
    }

    public function lastCheckedAt(): ?CarbonImmutable {
        $raw = Cache::get(self::LAST_CHECK_CACHE_KEY);

        return is_string($raw) ? CarbonImmutable::parse($raw) : null;
    }

    /** Remote-Check (nur mode manual per Button bzw. auto per Job). */
    public function checkRemote(): int {
        if ($this->mode() === self::MODE_DISABLED) {
            throw new RuntimeException('Update-Check ist deaktiviert (updates.check_mode).');
        }
        $url = (string) Setting::get('updates.feed_url', '');
        if ($url === '') {
            throw new RuntimeException('Keine Update-Feed-URL konfiguriert (updates.feed_url).');
        }

        $client = $this->http->coreClient('update-check', $url);
        $client->setFollowRedirects(false);
        $client->setTimeout(15.0);
        $document = $client->getResponse($url)->throw()->json();

        return $this->apply(is_array($document) ? $document : [], source: 'remote');
    }

    /** Offline-Import derselben signierten Datei (Air-Gap, DoD 022). */
    public function importOffline(string $json): int {
        $document = json_decode($json, true);
        if (!is_array($document)) {
            throw new RuntimeException('Ungültiges Update-Dokument (kein JSON).');
        }

        return $this->apply($document, source: 'offline_import');
    }

    /**
     * Verifiziert Signatur, gleicht Komponenten ab und meldet neue
     * Versionen. Rückgabe: Anzahl offener Updates.
     *
     * @param array<string, mixed> $document
     */
    public function apply(array $document, string $source): int {
        $payloadJson = $document['payload'] ?? null;
        $signature = $document['signature'] ?? null;
        if (!is_string($payloadJson) || !is_string($signature)) {
            throw new RuntimeException('Update-Dokument unvollständig (payload/signature).');
        }
        $this->verifySignature($payloadJson, $signature);

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload) || !is_array($payload['components'] ?? null)) {
            throw new RuntimeException('Update-Dokument ohne components-Liste.');
        }

        $now = CarbonImmutable::now();
        $installed = $this->installedVersions();
        $openUpdates = 0;

        foreach ($payload['components'] as $component) {
            if (!is_array($component)) {
                continue;
            }
            $key = (string) ($component['key'] ?? '');
            $type = (string) ($component['type'] ?? 'component');
            $available = (string) ($component['version'] ?? '');
            if ($key === '' || $available === '') {
                continue;
            }

            $installedVersion = $installed[$type . ':' . $key] ?? null;
            if ($installedVersion === null) {
                continue; // Komponente hier nicht installiert
            }

            if (version_compare($this->normalize($available), $this->normalize($installedVersion), '<=')) {
                // Bereits aktuell → offene Update-Zeile schließen.
                ComponentUpdate::query()
                    ->where('component_type', $type)
                    ->where('component_key', $key)
                    ->get()
                    ->each
                    ->delete();
                $this->alerts->resolve("update_security:{$type}:{$key}");
                continue;
            }

            $classification = in_array($component['classification'] ?? 'normal', ComponentUpdate::CLASSIFICATIONS, true)
                ? (string) $component['classification']
                : 'normal';
            $compatible = $this->isCompatible(
                isset($component['min_app_version']) ? (string) $component['min_app_version'] : null,
                isset($component['max_app_version']) ? (string) $component['max_app_version'] : null,
            );

            $update = ComponentUpdate::query()->firstOrNew([
                'component_type' => $type,
                'component_key' => $key,
            ]);
            $isNewVersion = $update->available_version !== $available;
            $update->fill([
                'installed_version' => $installedVersion,
                'available_version' => $available,
                'channel' => (string) ($component['channel'] ?? 'stable'),
                'classification' => $classification,
                'min_app_version' => $component['min_app_version'] ?? null,
                'max_app_version' => $component['max_app_version'] ?? null,
                'compatible' => $compatible,
                'changelog_url' => $component['changelog_url'] ?? null,
                'requires' => is_array($component['requires'] ?? null) ? $component['requires'] : null,
                'source' => $source,
                'checked_at' => $now,
            ]);
            if ($isNewVersion) {
                // Neue Version hebt frühere Stummschaltung auf (DoD 022).
                $update->acknowledged_at = null;
                $update->acknowledged_by = null;
                $update->snoozed_until = null;
            }
            $update->save();
            $openUpdates++;

            if ($isNewVersion) {
                $this->announce($update);
            }
        }

        Cache::forever(self::LAST_CHECK_CACHE_KEY, $now->toIso8601String());
        Log::info('updates.checked', ['source' => $source, 'open' => $openUpdates]);

        return $openUpdates;
    }

    /** @return array<string, ComponentUpdate> offene, nicht stummgeschaltete Updates */
    public function pending(bool $includeMuted = true): array {
        return ComponentUpdate::query()
            ->orderByRaw("case classification when 'critical' then 0 when 'security' then 1 when 'recommended' then 2 else 3 end")
            ->get()
            ->filter(fn(ComponentUpdate $u): bool => $includeMuted || !$u->isMuted())
            ->keyBy(fn(ComponentUpdate $u): string => $u->component_type . ':' . $u->component_key)
            ->all();
    }

    private function announce(ComponentUpdate $update): void {
        $muted = $update->isMuted();

        if ($update->isSecurityRelevant()) {
            // Sicherheitsupdate: Aufgabe + Meldung — bleibt zusätzlich auf
            // Komponenten-/Diagnoseseite sichtbar, auch wenn Kanäle stumm sind.
            $this->alerts->report(new OperationsSignal(
                type: OperationsTaskType::UpdateSecurity,
                dedupeKey: "update_security:{$update->component_type}:{$update->component_key}",
                severity: $update->classification === 'critical'
                    ? OperationsTaskSeverity::Critical
                    : OperationsTaskSeverity::Warning,
                titleKey: 'operations.task.update_security',
                params: [
                    'component' => $update->component_key,
                    'installed' => (string) $update->installed_version,
                    'available' => $update->available_version,
                    'classification' => $update->classification,
                ],
                linkRoute: 'admin.components.index',
            ));

            return;
        }

        if ($muted) {
            return; // Routinehinweis bewusst abgeschaltet/zurückgestellt
        }

        $this->alerts->report(new OperationsSignal(
            type: OperationsTaskType::UpdateAvailable,
            dedupeKey: "update_available:{$update->component_type}:{$update->component_key}",
            severity: OperationsTaskSeverity::Info,
            titleKey: 'operations.task.update_available',
            params: [
                'component' => $update->component_key,
                'installed' => (string) $update->installed_version,
                'available' => $update->available_version,
            ],
            linkRoute: 'admin.components.index',
        ));
    }

    private function verifySignature(string $payloadJson, string $signatureB64): void {
        $publicKeyB64 = $this->release->publicKeyB64();
        if ($publicKeyB64 === null) {
            throw new RuntimeException('Kein Vendor-Public-Key verfügbar — Update-Feed kann nicht verifiziert werden.');
        }
        $signature = base64_decode($signatureB64, true);
        $publicKey = base64_decode($publicKeyB64, true);
        if ($signature === false || $signature === '' || $publicKey === false || $publicKey === ''
            || !sodium_crypto_sign_verify_detached($signature, $payloadJson, $publicKey)) {
            throw new RuntimeException('Update-Feed-Signatur ungültig — Dokument verworfen.');
        }
    }

    /** @return array<string, string> "type:key" => installierte Version */
    private function installedVersions(): array {
        $versions = ['app:app' => (string) config('app.version')];

        try {
            foreach (PluginState::query()->whereNotNull('installed_version')->get() as $state) {
                $versions['plugin:' . $state->plugin_id] ??= (string) $state->installed_version;
            }
        } catch (\Throwable) {
            // Tabelle fehlt (Installer) — nur App-Version prüfen.
        }

        return $versions;
    }

    private function isCompatible(?string $min, ?string $max): bool {
        $app = $this->normalize((string) config('app.version'));
        if ($min !== null && version_compare($app, $this->normalize($min), '<')) {
            return false;
        }
        if ($max !== null && version_compare($app, $this->normalize($max), '>')) {
            return false;
        }

        return true;
    }

    private function normalize(string $version): string {
        return ltrim(trim($version), 'v');
    }
}
