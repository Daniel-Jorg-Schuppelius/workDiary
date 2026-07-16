<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Licensing;

use App\Models\{AuditLog, Organization};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\JsonHelper;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class LicenseService {
    private const CACHE_KEY = 'license:current';
    private const STATUS_KEY = 'license:lastStatus';

    /**
     * Memo der Datei-Integritätsprüfung. Ohne Memo hasht jeder
     * current()/forOrganization()-Aufruf alle versiegelten Dateien neu —
     * der Ergebnis-Cache greift erst danach. Das TTL-Fenster begrenzt das
     * Memo in langlaufenden Prozessen (Queue-Worker/Octane).
     */
    private static ?LicenseResult $integrityResult = null;
    private static ?float $integrityCheckedAt = null;

    public function __construct(
        private readonly Filesystem $files,
        private readonly CacheRepository $cache,
    ) {}

    public function isEnforced(): bool {
        return (bool) config('license.enforce', true);
    }

    public function current(?string $host = null): LicenseResult {
        $integrity = $this->checkIntegrity();
        if ($integrity !== null) {
            return $integrity;
        }

        $ttl = (int) config('license.cache_ttl', 300);
        if ($ttl > 0) {
            $cached = $this->safeCacheGet(self::CACHE_KEY . ':' . ($host ?? '_'));
            if ($cached instanceof LicenseResult) {
                return $cached;
            }
        }

        $result = $this->evaluate($host);

        $this->recordStatusTransition($result);

        if ($ttl > 0) {
            $this->safeCacheCall(fn() => $this->cache->put(self::CACHE_KEY . ':' . ($host ?? '_'), $result, $ttl));
        }

        return $result;
    }

    /**
     * Liest aus dem Cache, ohne bei Infrastruktur-Fehlern (z. B. nicht
     * erreichbarer DB-Cache) den gesamten Request mit einem 500 abzubrechen.
     * Die Lizenzbewertung selbst benötigt keine Datenbank.
     */
    private function safeCacheGet(string $key): mixed {
        try {
            return $this->cache->get($key);
        } catch (Throwable $e) {
            Log::warning('Lizenz-Cache nicht lesbar, fahre ohne Cache fort.', ['exception' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Führt eine schreibende Cache-/DB-Operation "best effort" aus. Schlägt sie
     * fehl (z. B. fehlende cache-Tabelle), wird dies protokolliert, aber nicht
     * als Fehler an den Aufrufer weitergereicht.
     */
    private function safeCacheCall(callable $callback): void {
        try {
            $callback();
        } catch (Throwable $e) {
            Log::warning('Lizenz-Cache nicht beschreibbar, überspringe.', ['exception' => $e->getMessage()]);
        }
    }

    public function flush(): void {
        // Cache hat eine kleine Key-Variation pro Host; einfacher: per Tag oder
        // generischer Flush. Wir nutzen direkte Forgets f\u00fcr die h\u00e4ufigsten F\u00e4lle.
        $this->safeCacheCall(function (): void {
            $this->cache->forget(self::CACHE_KEY . ':_');
            $this->cache->forget(self::STATUS_KEY);
        });
    }

    public function install(string $licenseKey): LicenseResult {
        $licenseKey = trim($licenseKey);
        $result = $this->verify($licenseKey);
        if (! $result->isUsable() && $result->status !== LicenseStatus::Expired) {
            return $result;
        }

        $path = storage_path('app/' . config('license.key_path', 'license.key'));
        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $licenseKey);
        @chmod($path, 0600);

        $this->flush();

        return $result;
    }

    /**
     * Installiert einen org-gebundenen Lizenzschluessel in `organizations.license_key`
     * und synchronisiert `organizations.plan` mit dem Tier der Lizenz (loest darueber
     * den Downgrade-Observer aus, falls das Tier sinkt).
     */
    public function installForOrganization(Organization $org, string $licenseKey): LicenseResult {
        $licenseKey = trim($licenseKey);
        $result = $this->verify($licenseKey, null, (string) $org->license_uid);
        if (! $result->isUsable() && $result->status !== LicenseStatus::Expired) {
            return $result;
        }

        $org->license_key = $licenseKey;
        if ($result->payload !== null) {
            $org->plan = $result->payload->plan;
        }
        $org->save();

        $this->flushOrganization($org);

        return $result;
    }

    public function flushOrganization(Organization $org): void {
        $this->safeCacheCall(fn() => $this->cache->forget(self::CACHE_KEY . ':org:' . (string) $org->getKey()));
    }

    /** Ed25519 Private Key (raw) aus Config oder Schlüssel-Datei, oder null. */
    public function privateKey(): ?string {
        $b64 = trim((string) config('license.private_key', ''));
        if ($b64 === '') {
            $path = (string) config('license.private_key_path', '');
            if ($path !== '') {
                $full = str_starts_with($path, '/') ? $path : base_path($path);
                if ($this->files->exists($full)) {
                    $b64 = $this->extractEnvValue((string) $this->files->get($full), 'LICENSE_PRIVATE_KEY');
                }
            }
        }
        if ($b64 === '') {
            return null;
        }

        $key = self::b64Decode($b64);

        return ($key !== null && strlen($key) === SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) ? $key : null;
    }

    /** Kann diese Instanz Lizenzen ausstellen (Private Key vorhanden)? */
    public function canIssue(): bool {
        return $this->privateKey() !== null;
    }

    private function extractEnvValue(string $contents, string $key): string {
        $lines = preg_split('/\r\n|\r|\n/', $contents);
        foreach ($lines !== false ? $lines : [] as $line) {
            $line = trim($line);
            if (str_starts_with($line, $key . '=')) {
                return trim(substr($line, strlen($key) + 1), " \t\"'");
            }
        }

        return '';
    }

    /**
     * Signiert eine org-gebundene Lizenz und installiert sie direkt. Nur auf einer
     * Herausgeber-Instanz (Private Key vorhanden) sinnvoll.
     *
     * @param  array<int,string>  $addons
     */
    /**
     * Signiert eine Lizenz und gibt den Schlüssel zurück (OHNE Installation) –
     * für die Ausstellung an Kunden. null, wenn kein Private Key vorhanden ist.
     *
     * @param  array<int,string>  $addons
     */
    public function signLicense(
        string $plan,
        array $addons,
        ?string $expires,
        string $licensee,
        ?string $organizationUid = null,
        ?string $domain = null,
        ?string $email = null,
        ?int $maxUsers = null,
    ): ?string {
        $private = $this->privateKey();
        if ($private === null || $private === '') {
            return null;
        }

        $payload = [
            'license_id' => bin2hex(random_bytes(8)),
            'licensee' => $licensee,
            'email' => ($email !== null && $email !== '') ? $email : null,
            'issued_at' => CarbonImmutable::now()->toIso8601String(),
            'expires_at' => ($expires !== null && $expires !== '')
                ? CarbonImmutable::parse($expires)->endOfDay()->toIso8601String()
                : null,
            'domain' => ($domain !== null && $domain !== '') ? $domain : null,
            'max_users' => $maxUsers,
            'plan' => $plan,
            'addons' => array_values($addons),
            'organization' => ($organizationUid !== null && $organizationUid !== '') ? $organizationUid : null,
        ];

        $json = JsonHelper::encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = sodium_crypto_sign_detached($json, $private);

        return self::b64Encode($json) . '.' . self::b64Encode($signature);
    }

    /**
     * Signiert eine org-gebundene Lizenz und installiert sie direkt.
     *
     * @param  array<int,string>  $addons
     */
    public function issueForOrganization(
        Organization $org,
        string $plan,
        array $addons,
        ?string $expires,
        string $licensee,
        ?int $maxUsers = null,
    ): LicenseResult {
        $key = $this->signLicense($plan, $addons, $expires, $licensee, (string) $org->license_uid, null, null, $maxUsers);
        if ($key === null) {
            return LicenseResult::fail(LicenseStatus::PublicKeyMissing, 'Kein Private Key konfiguriert.');
        }

        return $this->installForOrganization($org, $key);
    }

    public function rawKey(): ?string {
        $env = config('license.key');
        if (is_string($env) && $env !== '') {
            return trim($env);
        }

        $path = storage_path('app/' . config('license.key_path', 'license.key'));
        if ($this->files->exists($path)) {
            $content = trim((string) $this->files->get($path));

            return $content !== '' ? $content : null;
        }

        return null;
    }

    public function verify(string $licenseKey, ?string $host = null, ?string $organizationUid = null): LicenseResult {
        $publicKey = $this->loadPublicKey();
        if ($publicKey === null) {
            return LicenseResult::fail(LicenseStatus::PublicKeyMissing, 'Public Key fehlt in der Konfiguration.');
        }

        $parts = explode('.', $licenseKey);
        if (count($parts) !== 2) {
            return LicenseResult::fail(LicenseStatus::Malformed, 'Lizenzschlüssel hat ein unbekanntes Format.');
        }

        $payloadJson = self::b64Decode($parts[0]);
        $signature = self::b64Decode($parts[1]);

        if ($payloadJson === null || $payloadJson === '' || $signature === null || $signature === '') {
            return LicenseResult::fail(LicenseStatus::Malformed, 'Lizenzschlüssel ist nicht korrekt kodiert.');
        }

        if (! sodium_crypto_sign_verify_detached($signature, $payloadJson, $publicKey)) {
            return LicenseResult::fail(LicenseStatus::BadSignature, 'Signatur ungültig.');
        }

        try {
            $decoded = JsonHelper::decode($payloadJson);
        } catch (\InvalidArgumentException) {
            return LicenseResult::fail(LicenseStatus::Malformed, 'Lizenz-Payload ist kein gültiges JSON.');
        }
        if (! is_array($decoded)) {
            return LicenseResult::fail(LicenseStatus::Malformed, 'Lizenz-Payload ist kein JSON-Objekt.');
        }

        $payload = LicensePayload::fromArray($decoded);

        if ($payload->domain !== null && $payload->domain !== '') {
            $effectiveHost = $host ?? (app()->runningInConsole() ? '' : (string) app(Request::class)->getHost());
            if (! self::matchesDomain($effectiveHost, $payload->domain)) {
                return new LicenseResult(
                    LicenseStatus::DomainMismatch,
                    $payload,
                    'Lizenz ist auf "' . $payload->domain . '" gebunden, App läuft auf "' . $effectiveHost . '".'
                );
            }
        }

        // Org-Bindung: ist die Lizenz an eine Organisation gebunden, muss deren
        // stabile Bindungs-ID (license_uid) exakt passen – verhindert das
        // Kopieren einer Lizenz auf eine andere Organisation.
        if ($payload->organization !== null && $payload->organization !== '') {
            if ($organizationUid === null || ! hash_equals($payload->organization, $organizationUid)) {
                return new LicenseResult(
                    LicenseStatus::OrgMismatch,
                    $payload,
                    'Lizenz ist an eine andere Organisation gebunden.'
                );
            }
        }

        if ($payload->expiresAt !== null) {
            $now = CarbonImmutable::now();
            $graceDays = (int) config('license.grace_days', 14);
            $hardLimit = $payload->expiresAt->addDays($graceDays);

            if ($now->greaterThan($hardLimit)) {
                return new LicenseResult(LicenseStatus::Expired, $payload, 'Lizenz endgültig abgelaufen.');
            }
            if ($now->greaterThan($payload->expiresAt)) {
                return new LicenseResult(LicenseStatus::GracePeriod, $payload, 'Lizenz abgelaufen, Schonfrist läuft bis ' . $hardLimit->toDateString() . '.');
            }
        }

        return LicenseResult::ok(LicenseStatus::Valid, $payload);
    }

    protected function evaluate(?string $host): LicenseResult {
        $key = $this->rawKey();
        if ($key === null) {
            return LicenseResult::fail(LicenseStatus::Missing, 'Keine Lizenz installiert.');
        }

        return $this->verify($key, $host);
    }

    /**
     * Org-gebundene Lizenzbewertung: liest den Schluessel aus
     * `organizations.license_key` und prueft Signatur, Org-Bindung und Ablauf.
     * Cache pro Organisation; Integritaets-Seal gilt installationsweit.
     */
    public function forOrganization(Organization $org): LicenseResult {
        $integrity = $this->checkIntegrity();
        if ($integrity !== null) {
            return $integrity;
        }

        $key = $org->license_key;
        if (! is_string($key) || trim($key) === '') {
            return LicenseResult::fail(LicenseStatus::Missing, 'Keine Lizenz für diese Organisation.');
        }

        $ttl = (int) config('license.cache_ttl', 300);
        $cacheKey = self::CACHE_KEY . ':org:' . (string) $org->getKey();
        if ($ttl > 0) {
            $cached = $this->safeCacheGet($cacheKey);
            if ($cached instanceof LicenseResult) {
                return $cached;
            }
        }

        $result = $this->verify(trim($key), null, (string) $org->license_uid);

        if ($ttl > 0) {
            $this->safeCacheCall(fn() => $this->cache->put($cacheKey, $result, $ttl));
        }

        return $result;
    }

    /**
     * Erkennt Übergänge zwischen Lizenz-Status und schreibt einen
     * passenden Audit-Eintrag (`license.expired`, `license.gracePeriodEntered`,
     * `license.blocked`). Wird nur bei tatsächlichem Statuswechsel ausgelöst,
     * der zuletzt gesehene Status liegt im Cache (`license:lastStatus`).
     */
    private function recordStatusTransition(LicenseResult $result): void {
        // Statuswechsel werden nur protokolliert (Audit/Cache). Schl\u00e4gt der
        // dahinterliegende Speicher fehl (DB-Cache nicht erreichbar), darf das
        // niemals den eigentlichen Request zum Absturz bringen.
        try {
            $this->recordStatusTransitionUnsafe($result);
        } catch (Throwable $e) {
            Log::warning('Lizenz-Statuswechsel konnte nicht protokolliert werden.', ['exception' => $e->getMessage()]);
        }
    }

    private function recordStatusTransitionUnsafe(LicenseResult $result): void {
        $newStatus = $result->status;
        $previousValue = $this->cache->get(self::STATUS_KEY);
        $previousStatus = is_string($previousValue)
            ? LicenseStatus::tryFrom($previousValue)
            : null;

        if ($previousStatus === $newStatus) {
            return;
        }

        $this->cache->forever(self::STATUS_KEY, $newStatus->value);

        // Erster bekannter Status (frischer Cache): nichts auditieren,
        // sonst entsteht beim ersten Request immer ein Eintrag.
        if ($previousStatus === null) {
            return;
        }

        $events = [];
        if ($newStatus === LicenseStatus::GracePeriod && $previousStatus !== LicenseStatus::GracePeriod) {
            $events[] = 'license.expired';
            $events[] = 'license.gracePeriodEntered';
        }
        if ($newStatus === LicenseStatus::Expired && $previousStatus !== LicenseStatus::Expired) {
            if ($previousStatus !== LicenseStatus::GracePeriod) {
                $events[] = 'license.expired';
            }
            $events[] = 'license.blocked';
        }

        if ($events === []) {
            return;
        }

        $payload = $result->payload;
        $changes = [
            'from' => $previousStatus->value,
            'to' => $newStatus->value,
            'license_id' => $payload?->licenseId,
            'expires_at' => $payload?->expiresAt?->toIso8601String(),
        ];

        foreach ($events as $event) {
            AuditLog::query()->create([
                'organization_id' => null,
                'user_id' => null,
                'event' => $event,
                'auditable_type' => self::class,
                'auditable_id' => 0,
                'changes' => $changes,
            ]);
        }
    }

    /**
     * @return non-empty-string|null
     */
    private function loadPublicKey(): ?string {
        // Versiegelter Public Key hat Vorrang – ein Patch der .env reicht damit
        // nicht aus, um die Signaturprüfung gegen einen eigenen Key zu wenden.
        $b64 = LicenseSeal::isSealed()
            ? LicenseSeal::publicKey()
            : (string) config('license.public_key', '');

        if ($b64 === '') {
            return null;
        }
        $raw = self::b64Decode($b64);
        if ($raw === null || strlen($raw) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return null;
        }

        return $raw;
    }

    /**
     * Prüft, ob die lizenzrelevanten Dateien gegenüber dem Sealing-Zeitpunkt
     * unverändert sind. Gibt null zurück, wenn alles in Ordnung ist (oder die
     * App noch nicht versiegelt wurde), sonst ein Fehlerergebnis.
     */
    private function checkIntegrity(): ?LicenseResult {
        $ttl = (int) config('license.cache_ttl', 300);
        if ($ttl > 0 && self::$integrityCheckedAt !== null && (microtime(true) - self::$integrityCheckedAt) < $ttl) {
            return self::$integrityResult;
        }

        $result = $this->verifySealedFiles();
        self::$integrityResult = $result;
        self::$integrityCheckedAt = microtime(true);

        return $result;
    }

    public static function flushIntegrityCache(): void {
        self::$integrityResult = null;
        self::$integrityCheckedAt = null;
    }

    private function verifySealedFiles(): ?LicenseResult {
        if (! LicenseSeal::isSealed()) {
            return null;
        }

        $files = LicenseSeal::files();
        if ($files === []) {
            return null;
        }

        foreach ($files as $relativePath => $expectedHash) {
            $path = base_path((string) $relativePath);
            if (! $this->files->exists($path)) {
                return LicenseResult::fail(
                    LicenseStatus::Tampered,
                    'Lizenz-Integrität verletzt: Datei fehlt (' . $relativePath . ').'
                );
            }
            $actual = ToolkitFile::hash($path);
            if (! hash_equals((string) $expectedHash, $actual)) {
                return LicenseResult::fail(
                    LicenseStatus::Tampered,
                    'Lizenz-Integrität verletzt: ' . $relativePath . ' wurde verändert.'
                );
            }
        }

        return null;
    }

    private static function matchesDomain(string $host, string $pattern): bool {
        $host = strtolower($host);
        $pattern = strtolower($pattern);

        if ($pattern === $host) {
            return true;
        }
        // Wildcard-Pattern wie "*.example.com"
        if (str_starts_with($pattern, '*.')) {
            $suffix = substr($pattern, 1); // ".example.com"

            return str_ends_with($host, $suffix) || $host === substr($suffix, 1);
        }

        return false;
    }

    public static function b64Encode(string $bytes): string {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function b64Decode(string $value): ?string {
        $value = strtr($value, '-_', '+/');
        $padded = $value . str_repeat('=', (4 - strlen($value) % 4) % 4);
        $decoded = base64_decode($padded, true);

        return $decoded === false ? null : $decoded;
    }
}
