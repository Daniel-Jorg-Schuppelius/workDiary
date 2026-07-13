<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GitlabWebhookController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Gitlab\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{Organization, PluginSetting};
use App\Plugins\Gitlab\Api\GitlabClientFactory;
use App\Plugins\Gitlab\{GitlabConfig, GitlabPlugin};
use App\Plugins\Gitlab\Services\GitlabIssueImporter;
use Illuminate\Http\{JsonResponse, Request};
use Throwable;

/**
 * GitLab-Webhook (Feature 060, MVP-129): stößt einen idempotenten Issue-Import
 * an (Zammad-Muster). Sessionlos und ohne CSRF — Autorisierung ausschließlich
 * über den statischen `X-Gitlab-Token`-Header (Konstantzeit-Vergleich) gegen
 * das je Organisation verschlüsselt hinterlegte Secret. Der Webhook ist nur
 * ein Anstoß: GitLab deaktiviert Hooks nach wiederholten Fehlern selbst — das
 * `updated_after`-Polling ({@see \App\Plugins\Gitlab\Console\GitlabSyncCommand})
 * schließt jede Lücke. Es werden nie Aufgaben gelöscht.
 */
class GitlabWebhookController extends Controller {
    public function __invoke(Request $request, int $setting, GitlabClientFactory $factory, GitlabIssueImporter $importer): JsonResponse {
        $row = PluginSetting::query()
            ->withoutGlobalScopes()
            ->whereKey($setting)
            ->where('plugin_id', GitlabPlugin::ID)
            ->first();

        $token = is_string($row?->get('webhook_token')) ? trim((string) $row->get('webhook_token')) : '';
        if ($row === null || ! $row->enabled || $token === '' || ! GitlabConfig::isConfigured((int) $row->organization_id)) {
            // 404 statt 401: keine Auskunft über Existenz/Zustand der Anbindung.
            return response()->json(['status' => 'ignored'], 404);
        }

        if (! hash_equals($token, (string) $request->header('X-Gitlab-Token', ''))) {
            return response()->json(['status' => 'invalid_token'], 403);
        }

        // Org-Kontext für nachgelagerte (scoped) Operationen binden.
        $org = Organization::query()->whereKey($row->organization_id)->first();
        if (! $org instanceof Organization) {
            return response()->json(['status' => 'ignored'], 404);
        }
        app()->instance('currentOrganization', $org);

        try {
            $result = $importer->import($org, $factory->for((int) $org->id));
        } catch (Throwable) {
            // Kein Datenverlust: Polling holt nach. 202 = angenommen, aber nicht abgeschlossen.
            return response()->json(['status' => 'deferred'], 202);
        }

        return response()->json(['status' => 'ok'] + $result);
    }
}
