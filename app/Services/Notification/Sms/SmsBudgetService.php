<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SmsBudgetService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\Sms;

use App\Enums\Notification\SmsDeliveryStatus;
use App\Enums\Operations\{OperationsTaskSeverity, OperationsTaskType};
use App\Models\Notification\NotificationDispatchLog;
use App\Models\Platform\Organization;
use App\Services\Operations\{OperationsAlertService, OperationsSignal};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Monatsdeckel und Kostenwarnschwelle des SMS-Kanals (Feature 147, MVP-730) —
 * Muster wie das KI-Budget ({@see \App\Services\Ai\AiBudgetService}).
 *
 * Warum überhaupt ein Deckel: SMS ist der einzige Kanal, der je Nachricht Geld
 * kostet (0,07–0,10 €). Eine Fehlkonfiguration — ein Ereignis, das in einer
 * Schleife feuert, oder eine zu große Empfängerrolle — wäre sonst eine
 * unbegrenzte Rechnung. Der Deckel ist deshalb ein Guard VOR dem Versand, kein
 * Bericht danach.
 *
 * Gezählt wird in **Segmenten** (nicht Nachrichten), weil der Anbieter so
 * abrechnet; die Zahlen kommen aus dem `notification_dispatch_log` — dieselbe
 * Zeile, die auch der Zustellnachweis ist. Ein zweiter Zähler könnte
 * auseinanderlaufen, ohne dass es jemandem auffiele.
 */
class SmsBudgetService {
    private const string DEDUPE_PREFIX = 'sms_budget:';

    public function __construct(private readonly OperationsAlertService $alerts) {}

    /** Monatsgrenze in Segmenten; null = unbegrenzt. */
    public function limitFor(Organization $organization): ?int {
        // Org-explizit lesen (nicht Setting::get) — Scheduler und Queue laufen
        // ohne gebundene Organisation; der config-Wert ist der globale Default.
        $limit = data_get($organization->settings, 'notifications.sms.monthly_limit')
            ?? config('notifications.sms.monthly_limit');

        if ($limit === null || $limit === '') {
            return null;
        }

        return max(0, (int) $limit);
    }

    /** Ab wie viel Prozent des Deckels wird gewarnt (0 = nie). */
    public function warnPercent(Organization $organization): int {
        $percent = data_get($organization->settings, 'notifications.sms.warn_percent')
            ?? config('notifications.sms.warn_percent', 80);

        return max(0, min(100, (int) $percent));
    }

    /** Verbrauchte Segmente des laufenden Monats (nur real versendete). */
    public function usedThisPeriod(Organization $organization): int {
        return (int) NotificationDispatchLog::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('channel', NotificationDispatchLog::CHANNEL_SMS)
            ->where('status', SmsDeliveryStatus::Sent->value)
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->sum('segments');
    }

    /** Ist noch Budget für $segments frei? Ohne Deckel immer ja. */
    public function allows(Organization $organization, int $segments = 1): bool {
        $limit = $this->limitFor($organization);

        return $limit === null || $this->usedThisPeriod($organization) + max(0, $segments) <= $limit;
    }

    /**
     * Nach einem erfolgreichen Versand: hat dieser Versand die Warnschwelle
     * oder den Deckel gerissen? Der Vergleich „vorher darunter, jetzt darüber"
     * meldet jede Schwelle genau einmal je Monat, ohne Merker.
     */
    public function noteUsage(Organization $organization, int $segments): void {
        $limit = $this->limitFor($organization);
        if ($limit === null || $limit === 0 || $segments <= 0) {
            return;
        }

        $used = $this->usedThisPeriod($organization);
        $percent = $this->warnPercent($organization);
        $crossed = static fn (int $threshold): bool => $used >= $threshold && $used - $segments < $threshold;

        if ($crossed($limit)) {
            $this->warn($organization, $used, $limit, true);
        } elseif ($percent > 0 && $crossed((int) ceil($limit * $percent / 100))) {
            $this->warn($organization, $used, $limit, false);
        }
    }

    /**
     * Ein Versand scheiterte am Deckel. Meldet „aufgebraucht“, falls das noch
     * nicht geschah (etwa nach Senken des Deckels mitten im Monat); weitere
     * Sperren benachrichtigen nicht erneut.
     */
    public function noteExhausted(Organization $organization): void {
        $limit = $this->limitFor($organization);
        if ($limit === null) {
            return;
        }

        Log::warning('sms: monthly budget exhausted', ['organization_id' => $organization->id]);
        $this->alerts->report($this->signal($organization, $this->usedThisPeriod($organization), $limit, true));
    }

    /**
     * Betriebsaufgabe je Organisation und Monat: die Warnschwelle meldet als
     * Warnung, der Deckel verschärft dieselbe Aufgabe auf kritisch — beides
     * benachrichtigt die Verwaltung genau einmal (Dedupe im Alarmdienst).
     */
    private function warn(Organization $organization, int $used, int $limit, bool $exhausted): void {
        Log::warning('sms: budget threshold reached', [
            'organization_id' => $organization->id,
            'used_segments' => $used,
            'limit_segments' => $limit,
            'exhausted' => $exhausted,
        ]);
        $organization->audit($exhausted ? 'sms.budget_exhausted' : 'sms.budget_warning', [
            'used_segments' => $used,
            'limit_segments' => $limit,
        ]);

        $this->alerts->resolve(self::DEDUPE_PREFIX . $organization->id . ':' . Carbon::now()->subMonthNoOverflow()->format('Y-m'));
        $this->alerts->report($this->signal($organization, $used, $limit, $exhausted));
    }

    private function signal(Organization $organization, int $used, int $limit, bool $exhausted): OperationsSignal {
        return new OperationsSignal(
            type: OperationsTaskType::SmsBudgetWarning,
            dedupeKey: self::DEDUPE_PREFIX . $organization->id . ':' . Carbon::now()->format('Y-m'),
            severity: $exhausted ? OperationsTaskSeverity::Critical : OperationsTaskSeverity::Warning,
            titleKey: $exhausted ? 'operations.task.sms_budget_exhausted' : 'operations.task.sms_budget_warning',
            params: [
                'month' => Carbon::now()->format('m/Y'),
                'used' => $used,
                'limit' => $limit,
                'percent' => (int) floor($used * 100 / max(1, $limit)),
            ],
            organizationId: (int) $organization->id,
            linkRoute: 'admin.settings.index',
        );
    }
}
