<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpiryScanner.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Operations\Expiry;

use App\Enums\Operations\{OperationsTaskSeverity, OperationsTaskStatus, OperationsTaskType};
use App\Models\{AttendanceTerminal, ChatWebhook, OperationsTask, TodoistConnection};
use App\Services\Licensing\{LicenseService, LicenseStatus};
use App\Services\Operations\{OperationsAlertService, OperationsSignal};
use App\Support\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, Log};

/**
 * Konsolidierte Ablauf- und Verbindungswarnungen (Feature 041, MVP-057):
 * Lizenz, API-Tokens, OAuth-Verbindungen, Webhooks, Terminals und
 * EOL-Komponenten melden über EINE Schiene ins Aufgabencenter.
 *
 * Fachliche Fristen-Scanner (events:check-certificates, ISMS,
 * notifications:scan-deadlines) bleiben unberührt — hier geht es um
 * BETRIEBSZUGÄNGE. Auto-Resolve: aktive Expiry-Aufgaben, deren
 * dedupe_key im aktuellen Lauf nicht mehr gemeldet wird (Token
 * rotiert, Verbindung repariert), werden automatisch geschlossen.
 */
class ExpiryScanner {
    /** @var list<string> Aufgabentypen, die dieser Scanner verantwortet */
    private const MANAGED_TYPES = [
        'license_expiring',
        'license_limit_near',
        'credential_expiring',
        'connection_failing',
        'component_eol',
    ];

    /** @var list<ExpiryProbe> */
    private array $probes = [];

    public function __construct(private readonly LicenseService $license) {}

    /** Erweiterung für Konnektor-Probes (067-P4). */
    public function extend(ExpiryProbe $probe): void {
        $this->probes[] = $probe;
    }

    public function scan(OperationsAlertService $alerts): void {
        $signals = [];
        foreach ($this->collectors() as $collector) {
            try {
                $signals = [...$signals, ...$collector()];
            } catch (\Throwable $e) {
                Log::warning('operations.expiry_probe_failed', ['message' => $e->getMessage()]);
            }
        }
        foreach ($this->probes as $probe) {
            try {
                $signals = [...$signals, ...$probe->signals()];
            } catch (\Throwable $e) {
                Log::warning('operations.expiry_probe_failed', ['message' => $e->getMessage()]);
            }
        }

        $reportedKeys = [];
        foreach ($signals as $signal) {
            $reportedKeys[] = $signal->dedupeKey;
            $alerts->report($signal);
        }

        // Auto-Resolve: verwaltete Aufgaben, deren Ursache verschwunden ist.
        OperationsTask::query()
            ->whereIn('type', self::MANAGED_TYPES)
            ->whereNotIn('status', [OperationsTaskStatus::Done->value, OperationsTaskStatus::Resolved->value])
            ->whereNotIn('dedupe_key', $reportedKeys === [] ? ['__none__'] : $reportedKeys)
            ->pluck('dedupe_key')
            ->each(fn(string $key) => $alerts->resolve($key));
    }

    /** @return list<callable(): list<OperationsSignal>> */
    private function collectors(): array {
        return [
            fn(): array => $this->licenseSignals(),
            fn(): array => $this->licenseLimitSignals(),
            fn(): array => $this->personalAccessTokenSignals(),
            fn(): array => $this->todoistSignals(),
            fn(): array => $this->chatWebhookSignals(),
            fn(): array => $this->terminalSignals(),
            fn(): array => $this->phpEolSignals(),
            fn(): array => $this->connectionHealthSignals(),
        ];
    }

    /**
     * Konnektoren mit HasConnectionHealth-Spalten (MVP-178): gestörte
     * oder auto-deaktivierte Verbindungen als Betriebsaufgabe.
     *
     * @return list<OperationsSignal>
     */
    private function connectionHealthSignals(): array {
        $models = [
            'email' => \App\Models\EmailConnection::class,
            'cti' => \App\Models\CtiConnection::class,
            'carrier' => \App\Models\CarrierConnection::class,
            'caldav' => \App\Models\CalDavConnection::class,
            'webdav' => \App\Models\WebdavConnection::class,
        ];

        $signals = [];
        foreach ($models as $kind => $model) {
            $rows = $model::query()
                ->withoutGlobalScopes()
                ->where(function ($query): void {
                    $query->whereNotNull('last_error')->orWhereNotNull('disabled_at');
                })
                ->get();
            foreach ($rows as $row) {
                $signals[] = new OperationsSignal(
                    type: OperationsTaskType::ConnectionFailing,
                    dedupeKey: "connection_failing:{$kind}:" . $row->getKey(),
                    severity: $row->getAttribute('disabled_at') !== null
                        ? OperationsTaskSeverity::Critical
                        : OperationsTaskSeverity::Warning,
                    titleKey: 'operations.task.connection_failing',
                    params: [
                        'name' => (string) ($row->getAttribute('name') ?? $row->getAttribute('username') ?? $kind . ' #' . $row->getKey()),
                        'kind' => $kind,
                        'error' => (string) ($row->getAttribute('last_error') ?? __('operations.hint.auto_disabled_after', ['failures' => (int) $row->getAttribute('consecutive_failures')])),
                    ],
                    organizationId: (int) $row->getAttribute('organization_id'),
                );
            }
        }

        return $signals;
    }

    /** @return list<OperationsSignal> */
    private function licenseSignals(): array {
        $result = $this->license->current();
        $expiresAt = $result->payload?->expiresAt;
        if ($expiresAt === null) {
            return [];
        }

        $leadDays = (int) Setting::get('operations.expiry.license_days', 30);
        $expiry = CarbonImmutable::parse($expiresAt);
        $daysLeft = (int) now()->diffInDays($expiry, false);
        if ($daysLeft > $leadDays) {
            return [];
        }

        $critical = $daysLeft <= 7 || $result->status === LicenseStatus::GracePeriod || $result->status === LicenseStatus::Expired;

        return [new OperationsSignal(
            type: OperationsTaskType::LicenseExpiring,
            dedupeKey: 'license_expiring',
            severity: $critical ? OperationsTaskSeverity::Critical : OperationsTaskSeverity::Warning,
            titleKey: 'operations.task.license_expiring',
            params: ['date' => $expiry->toDateString(), 'days' => max(0, $daysLeft)],
            linkRoute: 'admin.license.index',
        )];
    }

    /**
     * Vollaudit 2026-07 (N9): Frühwarnung, BEVOR das Nutzerlimit erreicht ist
     * (ab 90 % Belegung; kritisch bei Vollbelegung) — vorher warnte der Scan
     * nur vor Ablauf/Grace.
     *
     * @return list<OperationsSignal>
     */
    private function licenseLimitSignals(): array {
        $guard = app(\App\Services\Licensing\LimitGuard::class);
        $signals = [];

        foreach (\App\Models\Organization::query()->withoutGlobalScopes()->get() as $org) {
            $usage = $guard->userLimitUsage($org);
            if ($usage === null || $usage['max'] <= 0) {
                continue;
            }
            if ($usage['current'] / $usage['max'] < 0.9) {
                continue;
            }

            $signals[] = new OperationsSignal(
                type: OperationsTaskType::LicenseLimitNear,
                dedupeKey: 'license_limit_near:' . $org->id,
                severity: $usage['current'] >= $usage['max']
                    ? OperationsTaskSeverity::Critical
                    : OperationsTaskSeverity::Warning,
                titleKey: 'operations.task.license_limit_near',
                params: ['org' => (string) $org->name, 'current' => $usage['current'], 'max' => $usage['max']],
                linkRoute: 'admin.license.index',
            );
        }

        return $signals;
    }

    /** @return list<OperationsSignal> */
    private function personalAccessTokenSignals(): array {
        $leadDays = (int) Setting::get('operations.expiry.credential_days', 14);

        $signals = DB::table('personal_access_tokens')
            ->join('users', function ($join): void {
                $join->on('users.id', '=', 'personal_access_tokens.tokenable_id')
                    ->where('personal_access_tokens.tokenable_type', \App\Models\User::class);
            })
            ->whereNotNull('personal_access_tokens.expires_at')
            ->where('personal_access_tokens.expires_at', '>', now())
            ->where('personal_access_tokens.expires_at', '<=', now()->addDays($leadDays))
            ->get(['personal_access_tokens.id', 'personal_access_tokens.name', 'personal_access_tokens.expires_at', 'users.organization_id'])
            ->map(fn(object $row): OperationsSignal => new OperationsSignal(
                type: OperationsTaskType::CredentialExpiring,
                dedupeKey: 'credential_expiring:pat:' . $row->id,
                severity: OperationsTaskSeverity::Warning,
                titleKey: 'operations.task.credential_expiring',
                params: [
                    'kind' => 'API-Token',
                    'name' => (string) $row->name,
                    'date' => CarbonImmutable::parse((string) $row->expires_at)->toDateString(),
                ],
                organizationId: (int) $row->organization_id,
            ))
            ->all();

        return array_values($signals);
    }

    /** @return list<OperationsSignal> */
    private function todoistSignals(): array {
        $leadDays = (int) Setting::get('operations.expiry.credential_days', 14);
        $signals = [];

        foreach (TodoistConnection::query()->withoutGlobalScopes()->get() as $connection) {
            $orgId = (int) $connection->organization_id;
            if ($connection->token_expires_at !== null
                && $connection->token_expires_at->isFuture()
                && $connection->token_expires_at->lte(now()->addDays($leadDays))) {
                $signals[] = new OperationsSignal(
                    type: OperationsTaskType::CredentialExpiring,
                    dedupeKey: 'credential_expiring:todoist:' . $connection->id,
                    severity: OperationsTaskSeverity::Warning,
                    titleKey: 'operations.task.credential_expiring',
                    params: [
                        'kind' => 'OAuth-Token (Todoist)',
                        'name' => (string) ($connection->todoist_user_email ?? 'Todoist'),
                        'date' => $connection->token_expires_at->toDateString(),
                    ],
                    organizationId: $orgId,
                );
            }
            if ((string) $connection->status !== 'active' || $connection->last_error !== null) {
                $signals[] = new OperationsSignal(
                    type: OperationsTaskType::ConnectionFailing,
                    dedupeKey: 'connection_failing:todoist:' . $connection->id,
                    severity: OperationsTaskSeverity::Warning,
                    titleKey: 'operations.task.connection_failing',
                    params: [
                        'name' => (string) ($connection->todoist_user_email ?? 'Todoist'),
                        'kind' => 'Todoist',
                        'error' => (string) ($connection->last_error ?? $connection->status),
                    ],
                    organizationId: $orgId,
                );
            }
        }

        return $signals;
    }

    /** @return list<OperationsSignal> */
    private function chatWebhookSignals(): array {
        $signals = ChatWebhook::query()
            ->withoutGlobalScopes()
            ->whereNotNull('disabled_at')
            ->get()
            ->map(fn(ChatWebhook $webhook): OperationsSignal => new OperationsSignal(
                type: OperationsTaskType::ConnectionFailing,
                dedupeKey: 'connection_failing:chat_webhook:' . $webhook->id,
                severity: OperationsTaskSeverity::Warning,
                titleKey: 'operations.task.connection_failing',
                params: [
                    'name' => (string) $webhook->name,
                    'kind' => (string) $webhook->kind,
                    'error' => __('operations.hint.auto_disabled_after', ['failures' => (int) $webhook->consecutive_failures]),
                ],
                organizationId: (int) $webhook->organization_id,
                linkRoute: 'admin.notification-rules.index',
            ))
            ->all();

        return array_values($signals);
    }

    /** @return list<OperationsSignal> */
    private function terminalSignals(): array {
        $signals = AttendanceTerminal::query()
            ->withoutGlobalScopes()
            ->where('active', true)
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '<', now()->subDay())
            ->get()
            ->map(fn(AttendanceTerminal $terminal): OperationsSignal => new OperationsSignal(
                type: OperationsTaskType::ConnectionFailing,
                dedupeKey: 'connection_failing:terminal:' . $terminal->id,
                severity: OperationsTaskSeverity::Warning,
                titleKey: 'operations.task.connection_failing',
                params: [
                    'name' => (string) $terminal->name,
                    'kind' => 'Terminal',
                    'error' => __('operations.hint.no_contact_since', ['date' => $terminal->last_seen_at?->format('d.m.Y H:i') ?? '—']),
                ],
                organizationId: (int) $terminal->organization_id,
            ))
            ->all();

        return array_values($signals);
    }

    /** @return list<OperationsSignal> */
    private function phpEolSignals(): array {
        $leadDays = (int) Setting::get('operations.expiry.eol_lead_days', 90);
        $minor = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        $eolDate = config('eol.php.' . $minor);
        if (!is_string($eolDate)) {
            return [];
        }

        $eol = CarbonImmutable::parse($eolDate);
        if ($eol->greaterThan(now()->addDays($leadDays))) {
            return [];
        }

        return [new OperationsSignal(
            type: OperationsTaskType::ComponentEol,
            dedupeKey: 'component_eol:php:' . $minor,
            severity: $eol->isPast() ? OperationsTaskSeverity::Critical : OperationsTaskSeverity::Warning,
            titleKey: 'operations.task.component_eol',
            params: ['component' => 'PHP', 'version' => $minor, 'date' => $eol->toDateString()],
            linkRoute: 'admin.components.index',
        )];
    }
}
