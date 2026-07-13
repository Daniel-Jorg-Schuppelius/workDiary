<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GithubWebhookController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Github\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{Organization, PluginSetting};
use App\Plugins\Github\Api\GithubClientFactory;
use App\Plugins\Github\{GithubConfig, GithubPlugin};
use App\Plugins\Github\Services\GithubIssueImporter;
use Illuminate\Http\{JsonResponse, Request};
use Throwable;

/**
 * GitHub-Webhook (Feature 060, MVP-129): stößt einen idempotenten Issue-Import
 * an (Zammad-Muster). Sessionlos und ohne CSRF — Autorisierung ausschließlich
 * über die HMAC-Signatur (`X-Hub-Signature-256: sha256=…`) des Raw-Bodys gegen
 * das je Organisation verschlüsselt hinterlegte Shared-Secret. Der Webhook ist
 * nur ein Anstoß: GitHub liefert nicht automatisch nach (kein Auto-Redelivery),
 * das `since`-Polling ({@see \App\Plugins\Github\Console\GithubSyncCommand})
 * schließt Lücken. Es werden nie Aufgaben gelöscht.
 */
class GithubWebhookController extends Controller {
    public function __invoke(Request $request, int $setting, GithubClientFactory $factory, GithubIssueImporter $importer): JsonResponse {
        $row = PluginSetting::query()
            ->withoutGlobalScopes()
            ->whereKey($setting)
            ->where('plugin_id', GithubPlugin::ID)
            ->first();

        $secret = is_string($row?->get('webhook_secret')) ? trim((string) $row->get('webhook_secret')) : '';
        if ($row === null || ! $row->enabled || $secret === '' || ! GithubConfig::isConfigured((int) $row->organization_id)) {
            // 404 statt 401: keine Auskunft über Existenz/Zustand der Anbindung.
            return response()->json(['status' => 'ignored'], 404);
        }

        if (! $this->signatureValid($request, $secret)) {
            return response()->json(['status' => 'invalid_signature'], 403);
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

    /** Konstantzeit-Vergleich der GitHub-Signatur (sha256=HMAC-SHA256(body, secret)). */
    private function signatureValid(Request $request, string $secret): bool {
        $header = (string) $request->header('X-Hub-Signature-256', '');
        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }
        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $header);
    }
}
