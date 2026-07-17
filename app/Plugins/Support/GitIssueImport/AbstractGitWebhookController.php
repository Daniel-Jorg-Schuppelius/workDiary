<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractGitWebhookController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\GitIssueImport;

use App\Http\Controllers\Controller;
use App\Models\{Organization, PluginSetting};
use Illuminate\Http\{JsonResponse, Request};
use Throwable;

/**
 * Gemeinsames Webhook-Skeleton des Git-Issue-Imports (Feature 060,
 * Konsolidierung C8): Settings-Zeile prüfen → Autorisierung (provider-
 * spezifisch: HMAC bzw. statischer Token) → Org-Kontext binden →
 * idempotenten Import anstoßen. Sessionlos und ohne CSRF; der Webhook ist
 * nur ein Anstoß, das Polling schließt Lücken. Es werden nie Aufgaben
 * gelöscht.
 */
abstract class AbstractGitWebhookController extends Controller {
    abstract protected function pluginId(): string;

    /** Settings-Schlüssel des Webhook-Geheimnisses (webhook_secret/-token). */
    abstract protected function credentialKey(): string;

    abstract protected function isConfigured(int $organizationId): bool;

    /** Autorisierung der Anfrage gegen das hinterlegte Geheimnis (Konstantzeit). */
    abstract protected function credentialValid(Request $request, string $credential): bool;

    /** JSON-`status` bei ungültiger Autorisierung (Bestandsverhalten je Plugin). */
    abstract protected function rejectionStatus(): string;

    /** @return array{created: int, updated: int, skipped: int, inbox: int} */
    abstract protected function import(Organization $organization): array;

    public function __invoke(Request $request, int $setting): JsonResponse {
        $row = PluginSetting::query()
            ->withoutGlobalScopes()
            ->whereKey($setting)
            ->where('plugin_id', $this->pluginId())
            ->first();

        $credential = is_string($row?->get($this->credentialKey())) ? trim((string) $row->get($this->credentialKey())) : '';
        if ($row === null || ! $row->enabled || $credential === '' || ! $this->isConfigured((int) $row->organization_id)) {
            // 404 statt 401: keine Auskunft über Existenz/Zustand der Anbindung.
            return response()->json(['status' => 'ignored'], 404);
        }

        if (! $this->credentialValid($request, $credential)) {
            return response()->json(['status' => $this->rejectionStatus()], 403);
        }

        // Org-Kontext für nachgelagerte (scoped) Operationen binden.
        $org = Organization::query()->whereKey($row->organization_id)->first();
        if (! $org instanceof Organization) {
            return response()->json(['status' => 'ignored'], 404);
        }
        app()->instance('currentOrganization', $org);

        try {
            $result = $this->import($org);
        } catch (Throwable) {
            // Kein Datenverlust: Polling holt nach. 202 = angenommen, aber nicht abgeschlossen.
            return response()->json(['status' => 'deferred'], 202);
        }

        return response()->json(['status' => 'ok'] + $result);
    }
}
