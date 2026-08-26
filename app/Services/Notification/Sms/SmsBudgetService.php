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
use App\Models\Notification\NotificationDispatchLog;
use App\Models\Organization;
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
            ->whereIn('status', [SmsDeliveryStatus::Sent->value, SmsDeliveryStatus::Delivered->value])
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
     * gerissen? Der Vergleich „vorher darunter, jetzt darüber" sorgt dafür,
     * dass genau einmal je Monat gewarnt wird, ohne dafür einen Merker zu
     * brauchen.
     */
    public function noteUsage(Organization $organization, int $segments): void {
        $limit = $this->limitFor($organization);
        $percent = $this->warnPercent($organization);
        if ($limit === null || $limit === 0 || $percent === 0 || $segments <= 0) {
            return;
        }

        $threshold = (int) ceil($limit * $percent / 100);
        $used = $this->usedThisPeriod($organization);
        if ($used < $threshold || $used - $segments >= $threshold) {
            return;
        }

        Log::warning('sms: budget warning threshold reached', [
            'organization_id' => $organization->id,
            'used_segments' => $used,
            'limit_segments' => $limit,
            'warn_percent' => $percent,
        ]);
        $organization->audit('sms.budget_warning', [
            'used_segments' => $used,
            'limit_segments' => $limit,
        ]);
    }
}
