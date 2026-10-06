<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglWebhookController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Toggl\Http\Controllers;

use App\Plugins\Support\TimeTracking\TimeTrackingWebhookController;
use App\Plugins\Support\WebhookSignature;
use App\Plugins\Toggl\TogglPlugin;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Http\{JsonResponse, Request};

/**
 * Toggl-Webhook: HMAC-SHA256 über den Raw-Body, Zustell-ID `event_id`.
 * Ablauf und Reihenfolge stehen in der Basis.
 *
 * Toggl schickt beim Anlegen einer Subscription eine Prüfnachricht mit
 * `validation_code`; die wird unsigniert zurückgespiegelt — das ist der
 * dokumentierte Ablauf und der einzige Fall ohne Signaturprüfung.
 *
 * Der Webhook ERSETZT das Polling nicht. Toggl sichert keine Zustellung zu;
 * ein verlorener Aufruf würde sonst einen Zeiteintrag kosten.
 */
class TogglWebhookController extends TimeTrackingWebhookController {
    protected function pluginId(): string {
        return TogglPlugin::ID;
    }

    protected function handshake(array $payload): ?JsonResponse {
        $validation = trim((string) ($payload['validation_code'] ?? ''));

        return $validation !== '' ? response()->json(['validation_code' => $validation]) : null;
    }

    protected function workspaceId(array $payload): string {
        return (string) ($payload['metadata']['workspace_id'] ?? ($payload['payload']['workspace_id'] ?? ''));
    }

    protected function signatureValid(Request $request, string $raw, ?string $secret): bool {
        return WebhookSignature::hmacValid($raw, $secret, (string) $request->header('X-Webhook-Signature-256', ''), 'sha256', prefix: 'sha256=');
    }

    protected function deliveryId(Request $request, array $payload, string $raw): string {
        $eventId = trim((string) ($payload['event_id'] ?? ''));

        return $eventId !== '' ? $eventId : CryptoHelper::hash($raw);
    }

    protected function eventName(Request $request, array $payload): string {
        return (string) ($payload['metadata']['action'] ?? '');
    }
}
