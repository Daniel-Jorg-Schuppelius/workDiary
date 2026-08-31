<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrityLockdownService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Release;

use App\Enums\Notification\NotificationEvent;
use App\Enums\Security\IntegrityCheckStatus;
use App\Models\{AuditLog, IntegrityCheck, Organization, User};
use App\Models\Crisis\CrisisCase;
use App\Notifications\GenericEventNotification;
use App\Services\Crisis\CrisisAlertService;
use Illuminate\Support\Facades\{Artisan, Log, Notification};
use Illuminate\Support\Facades\Storage;

/**
 * Integritäts-Lockdown (Feature 097, MVP-448): schaltet die Installation in
 * den Wartungsmodus, wenn eine **signierte Release-Baseline** über
 * **mindestens zwei konsekutive Läufe** dateiweise Abweichungen zeigt.
 *
 * Fehlalarm-Schutz ist bewusst streng (Doku 095: Hotfix ohne neue Baseline
 * + Lockdown an = selbstverschuldeter Ausfall, deshalb Default `off`):
 *
 *  - Env `INTEGRITY_LOCKDOWN=confirmed` muss gesetzt sein (Default `off`),
 *  - Baseline `source=release` **und** intakte Signaturkette (keine
 *    `chain`-Befunde — bei lokalem Freeze fehlt der Herkunftsbeweis),
 *  - ≥ 2 konsekutive `deviation`-Läufe (transiente Deploy-Zustände lösen
 *    nicht aus),
 *  - Abweichung im dateiweisen Scope, nicht ausschließlich das
 *    `composer-autoloader`-Pseudo-Paket.
 *
 * Aktion: `php artisan down` mit Bypass-Secret aus der Env (damit Admins
 * hineinkommen), CrisisAlert (Feature 070) + Plattform-Admin-Notification und
 * Audit-Ketten-Eintrag mit Auslöse-Befund. Entsperrt wird über eine neue
 * gültige Baseline oder manuelles `up` — beides auditiert.
 */
class IntegrityLockdownService {
    public const MODE_OFF = 'off';

    public const MODE_CONFIRMED = 'confirmed';

    /** Mindestzahl konsekutiver Abweichungsläufe. */
    public const CONSECUTIVE_RUNS = 2;

    public function armed(): bool {
        return (string) config('integrity.lockdown.mode', self::MODE_OFF) === self::MODE_CONFIRMED;
    }

    /**
     * Prüft nach einem Verify-Lauf, ob der Lockdown greifen muss.
     *
     * @param  array<string, mixed>  $manifest
     * @return bool  true = Lockdown ausgelöst
     */
    public function evaluate(IntegrityCheck $check, array $manifest, IntegrityComparison $comparison): bool {
        if (! $this->armed() || ! $this->qualifies($check, $manifest, $comparison)) {
            return false;
        }
        if ($this->isDown()) {
            return false; // bereits gesperrt — kein zweiter Alarm
        }

        $reason = [
            'root' => (string) ($manifest['root'] ?? ''),
            'added' => $check->added_count,
            'modified' => $check->modified_count,
            'deleted' => $check->deleted_count,
            'packages' => $check->packages_changed_count,
            'findings_hash' => (string) $check->findings_hash,
        ];

        $this->engageMaintenance();
        $this->audit('integrity.lockdown_engaged', $check, $reason);
        $this->raiseCrisis($check, $reason);
        $this->notifyPlatformAdmins($check);

        return true;
    }

    /**
     * Auslösebedingungen (alle müssen erfüllt sein).
     *
     * @param  array<string, mixed>  $manifest
     */
    public function qualifies(IntegrityCheck $check, array $manifest, IntegrityComparison $comparison): bool {
        if ($check->status !== IntegrityCheckStatus::Deviation) {
            return false;
        }
        // Nur signierte Release-Baselines beweisen die Herkunft — und
        // „signiert" muss geprüft sein, nicht behauptet (Sicherheitsscan
        // 2026-08-23, S-52). Der String `source === 'release'` steht in einer
        // Datei, die der Web-Nutzer schreiben kann; er belegt nichts. Verlangt
        // wird deshalb ein `release.json`, dessen Signatur gegen den
        // KONFIGURIERTEN Herausgeber-Schlüssel aufgeht.
        if ((string) ($manifest['source'] ?? '') !== 'release' || $comparison->chain !== []) {
            return false;
        }

        if (! $this->releaseSignatureVerified()) {
            return false;
        }
        // Rein dateiweise Befunde zählen; ein alleiniges Autoloader-Aggregat
        // (Cache-Rebuild) ist kein Manipulationsbeweis.
        $fileScope = $comparison->added !== [] || $comparison->modified !== [] || $comparison->deleted !== [];
        $packagesBeyondAutoloader = array_values(array_filter(
            $comparison->packages,
            static fn(string $name): bool => ! str_starts_with($name, 'composer-autoloader'),
        ));
        if (! $fileScope && $packagesBeyondAutoloader === []) {
            return false;
        }

        return $this->consecutiveDeviations($check) >= self::CONSECUTIVE_RUNS;
    }

    /** Konsekutive Abweichungsläufe inklusive des aktuellen. */
    public function consecutiveDeviations(IntegrityCheck $check): int {
        $runs = IntegrityCheck::query()
            ->whereIn('status', [IntegrityCheckStatus::Ok->value, IntegrityCheckStatus::Deviation->value])
            ->where('id', '<=', $check->id)
            ->orderByDesc('ran_at')
            ->orderByDesc('id')
            ->limit(self::CONSECUTIVE_RUNS + 3)
            ->get();

        $count = 0;
        foreach ($runs as $run) {
            if ($run->status !== IntegrityCheckStatus::Deviation) {
                break;
            }
            $count++;
        }

        return $count;
    }

    /**
     * Entsperrung nach neuer gültiger Baseline oder manuellem Eingriff.
     * Idempotent; auditiert. Liefert false, wenn nichts zu tun war.
     */
    public function release(?User $actor = null, string $reason = 'manual'): bool {
        if (! $this->isDown()) {
            return false;
        }

        $this->liftMaintenance();

        // Audit-Anker ist der jüngste Prüflauf (audit_logs.auditable_id ist
        // NOT NULL); ohne Prüfhistorie bleibt nur das Application-Log.
        $latest = IntegrityCheck::query()->latest('ran_at')->latest('id')->first();
        if ($latest instanceof IntegrityCheck) {
            $this->audit('integrity.lockdown_released', $latest, ['reason' => $reason, 'by' => $actor?->id]);
        } else {
            Log::warning('integrity.lockdown_released_unanchored', ['reason' => $reason]);
        }

        return true;
    }

    public function isDown(): bool {
        return app()->isDownForMaintenance();
    }

    /** Gegenstück zu {@see engageMaintenance()} — ebenfalls Test-Naht. */
    protected function liftMaintenance(): void {
        Artisan::call('up');
    }

    /**
     * Wartungsmodus einschalten. Eigene Methode (protected) als Test-Naht:
     * ein echtes `artisan down` wirkt prozessübergreifend über
     * `storage/framework` und würde parallele Testprozesse mit sperren.
     */
    protected function engageMaintenance(): void {
        $secret = trim((string) config('integrity.lockdown.bypass_secret', ''));
        $options = [];
        if ($secret !== '') {
            $options['--secret'] = $secret;
        }

        try {
            Artisan::call('down', $options);
        } catch (\Throwable $e) {
            // Ein fehlgeschlagener Wartungsmodus darf den Prüflauf nicht
            // abbrechen — Alarm/Audit laufen trotzdem.
            Log::error('integrity.lockdown_down_failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * CrisisAlert (Feature 070): Sicherheitskrise in der Betreiber-Org
     * eröffnen und den Stab alarmieren. Ohne Krisenstab bleibt es beim
     * Krisenfall + Admin-Notification.
     *
     * @param  array<string, mixed>  $reason
     */
    private function raiseCrisis(IntegrityCheck $check, array $reason): void {
        $actor = User::query()->where('is_platform_admin', true)->orderBy('id')->first();
        if (! $actor instanceof User) {
            return;
        }
        $organization = $actor->organization ?? Organization::query()->orderBy('id')->first();
        if (! $organization instanceof Organization) {
            return;
        }

        try {
            $case = CrisisCase::query()->create([
                'organization_id' => $organization->id,
                'title' => (string) __('integrity.lockdown.crisis_title'),
                'category' => 'security',
                'severity' => 'critical',
                'status' => 'activated',
                'trigger_source' => 'integrity',
                'description' => (string) __('integrity.lockdown.crisis_description', [
                    'modified' => $reason['modified'],
                    'added' => $reason['added'],
                    'deleted' => $reason['deleted'],
                ]),
                'activated_at' => now(),
                'responsible_user_id' => $actor->id,
                'created_by' => $actor->id,
            ]);
            $case->audit('crisis.reported', ['trigger' => 'integrity.lockdown', 'check_id' => $check->id]);
            app(CrisisAlertService::class)->alert($case, $actor);
        } catch (\Throwable $e) {
            Log::error('integrity.lockdown_crisis_failed', ['error' => $e->getMessage()]);
        }
    }

    private function notifyPlatformAdmins(IntegrityCheck $check): void {
        $admins = User::query()->where('is_platform_admin', true)->get();
        if ($admins->isEmpty()) {
            return;
        }

        $params = [
            'modified' => $check->modified_count,
            'added' => $check->added_count,
            'deleted' => $check->deleted_count,
        ];
        $titleKey = 'notification.message.integrity_lockdown_title';
        $messageKey = 'notification.message.integrity_lockdown_message';

        Notification::send($admins, new GenericEventNotification(
            NotificationEvent::SecurityIntegrity,
            [
                'title' => (string) __($titleKey, $params),
                'title_key' => $titleKey,
                'title_params' => $params,
                'message' => (string) __($messageKey, $params),
                'message_key' => $messageKey,
                'message_params' => $params,
                'url' => route('admin.integrity.index'),
            ],
            ['database', 'mail'],
        ));
    }

    /** @param  array<string, mixed>  $changes */
    private function audit(string $event, IntegrityCheck $check, array $changes): void {
        try {
            AuditLog::query()->create([
                'organization_id' => null,
                'user_id' => null,
                'event' => $event,
                'auditable_type' => IntegrityCheck::class,
                'auditable_id' => $check->id,
                'changes' => $changes,
            ]);
        } catch (\Throwable $e) {
            Log::warning('integrity.lockdown_audit_failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Widerspricht die Release-Signatur der behaupteten Herkunft?
     *
     * Die Baseline behauptet über `source === 'release'`, aus einem Release zu
     * stammen — und diese Angabe steht in einer Datei, die der Web-Nutzer
     * schreiben kann (Sicherheitsscan 2026-08-23, S-52). Wo ein
     * Herausgeber-Schlüssel konfiguriert ist, wird die Behauptung deshalb
     * gegen die Signatur geprüft.
     *
     * **Ohne konfigurierten Schlüssel bleibt es beim bisherigen Verhalten.**
     * Die Sperre ist eine SCHÜTZENDE Maßnahme: verlangte man den Nachweis
     * absolut, könnte sie auf jeder Installation ohne `LICENSE_PUBLIC_KEY`
     * nie mehr greifen — der Fix würde den Schutz genau dort abschalten, wo er
     * am ehesten gebraucht wird. Strenger wird es, wo Strenge möglich ist;
     * nachsichtiger nirgends.
     */
    private function releaseSignatureVerified(): bool {
        $configured = (string) config('license.public_key', '') !== ''
            || \App\Services\Licensing\LicenseSeal::isSealed();

        if (! $configured) {
            return true;
        }

        try {
            $json = (string) Storage::disk('local')->get(ReleaseManifestService::STORAGE_PATH);
        } catch (\Throwable) {
            return false;
        }

        $document = json_decode($json, true);
        if (! is_array($document)) {
            return false;
        }

        return app(ReleaseVerifier::class)->verify($document)->signatureValid === true;
    }

}
