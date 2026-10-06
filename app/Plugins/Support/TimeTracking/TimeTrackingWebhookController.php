<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeTrackingWebhookController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\TimeTracking;

use App\Http\Controllers\Controller;
use App\Models\Integration\TimeTrackingWebhookDelivery;
use App\Models\Platform\Organization;
use App\Plugins\Support\{PluginTenantGate, RecordsWebhookDeliveries};
use Illuminate\Http\{JsonResponse, Request};

/**
 * Sessionloser Webhook einer Zeiterfassung (Feature 124, MVP-613).
 *
 * Reihenfolge ist sicherheitsrelevant:
 *  1. Workspace lesen und Mandant auflösen — ohne ihn gibt es kein Geheimnis.
 *  2. Signatur über den UNVERÄNDERTEN Raw-Body prüfen, VOR jeder Verarbeitung.
 *  3. Dedup über die Zustell-ID, persistiert VOR der Verarbeitung.
 *
 * Der Webhook weckt nur; das Polling bleibt die verlässliche Quelle.
 */
abstract class TimeTrackingWebhookController extends Controller {
    use RecordsWebhookDeliveries;

    abstract protected function pluginId(): string;

    /** @param  array<string, mixed>  $payload */
    abstract protected function workspaceId(array $payload): string;

    abstract protected function signatureValid(Request $request, string $raw, ?string $secret): bool;

    /**
     * Kennung der Zustellung, nicht des Zeiteintrags: dieselbe Zustellung
     * ergibt dieselbe Kennung, eine spätere Änderung eine andere.
     *
     * @param  array<string, mixed>  $payload
     */
    abstract protected function deliveryId(Request $request, array $payload, string $raw): string;

    /** @param  array<string, mixed>  $payload */
    abstract protected function eventName(Request $request, array $payload): string;

    /**
     * Antwort auf eine Nachricht, die der Anbieter vor der Signaturprüfung
     * erwartet (Prüfnachricht beim Anlegen der Subscription).
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handshake(array $payload): ?JsonResponse {
        return null;
    }

    public function __invoke(Request $request, TimeTrackingWebhookGate $gate): JsonResponse {
        $raw = (string) $request->getContent();
        /** @var array<string, mixed> $payload */
        $payload = (array) json_decode($raw, true);

        $handshake = $this->handshake($payload);
        if ($handshake !== null) {
            return $handshake;
        }

        // Alle Kandidaten prüfen, nicht nur den ersten (Sicherheitsscan
        // 2026-08-23, S-57): teilen sich zwei Mandanten eine Workspace-ID,
        // schnitt die erste Zeile die andere still vom Webhook ab — deren
        // Geheimnis wurde nie geprüft. Entscheiden soll die Signatur.
        $candidates = $gate->organizationsFor($this->pluginId(), $this->workspaceId($payload));

        $organization = null;
        foreach ($candidates as $candidate) {
            if ($this->signatureValid($request, $raw, $gate->secretFor($this->pluginId(), (int) $candidate->id))) {
                $organization = $candidate;
                break;
            }
        }

        if (! $organization instanceof Organization) {
            // „Workspace unbekannt" (ignoriert) und „bekannt, aber Signatur
            // falsch" (401) bleiben unterschieden — das braucht der absendende
            // Dienst, um eine Fehlkonfiguration zu erkennen. Ein Orakel ist es
            // nicht: die Workspace-ID steht in der Konfiguration des Absenders.
            return $candidates->isEmpty()
                ? response()->json(['status' => 'ignored'])
                : response()->json(['message' => 'invalid signature'], 401);
        }
        // Gesperrter Mandant: nichts verarbeiten (Entscheidung 2026-10-05) — erst nach der Signaturprüfung, kein Rückschluss von außen.
        if (! $organization->publicSurfacesAvailable()) {
            return PluginTenantGate::refusal();
        }

        $delivery = $this->recordDelivery(fn (): TimeTrackingWebhookDelivery => TimeTrackingWebhookDelivery::query()->create([
            'plugin_id' => $this->pluginId(),
            'delivery_id' => $this->deliveryId($request, $payload, $raw),
            'event_name' => mb_substr($this->eventName($request, $payload), 0, 128) ?: null,
            'organization_id' => (int) $organization->id,
            'received_at' => now(),
        ]));
        if ($delivery === null) {
            return response()->json(['status' => 'duplicate']);
        }

        // Entprellt: Ein Lauf je Zeiteintrag würde genau die Quote sprengen,
        // die der Webhook entlasten soll.
        if (! $gate->shouldRun($this->pluginId(), (int) $organization->id)) {
            return response()->json(['status' => 'debounced']);
        }

        WebhookImportJob::dispatch($this->pluginId(), (int) $organization->id, (int) $delivery->id);

        return response()->json(['status' => 'queued']);
    }
}
