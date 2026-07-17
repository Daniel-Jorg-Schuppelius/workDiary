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

use App\Models\Organization;
use App\Plugins\Gitlab\Api\GitlabClientFactory;
use App\Plugins\Gitlab\{GitlabConfig, GitlabPlugin};
use App\Plugins\Gitlab\Services\GitlabIssueImporter;
use App\Plugins\Support\GitIssueImport\AbstractGitWebhookController;
use App\Plugins\Support\WebhookSignature;
use Illuminate\Http\Request;

/**
 * GitLab-Webhook (Feature 060, MVP-129): stößt einen idempotenten Issue-Import
 * an (Zammad-Muster). Autorisierung ausschließlich über den statischen
 * `X-Gitlab-Token`-Header (Konstantzeit-Vergleich) gegen das je Organisation
 * verschlüsselt hinterlegte Secret. GitLab deaktiviert Hooks nach wiederholten
 * Fehlern selbst — das `updated_after`-Polling
 * ({@see \App\Plugins\Gitlab\Console\GitlabSyncCommand}) schließt jede Lücke.
 */
class GitlabWebhookController extends AbstractGitWebhookController {
    public function __construct(
        private readonly GitlabClientFactory $factory,
        private readonly GitlabIssueImporter $importer,
    ) {}

    protected function pluginId(): string {
        return GitlabPlugin::ID;
    }

    protected function credentialKey(): string {
        return 'webhook_token';
    }

    protected function isConfigured(int $organizationId): bool {
        return GitlabConfig::isConfigured($organizationId);
    }

    protected function credentialValid(Request $request, string $credential): bool {
        return WebhookSignature::tokenValid($credential, (string) $request->header('X-Gitlab-Token', ''));
    }

    protected function rejectionStatus(): string {
        return 'invalid_token';
    }

    protected function import(Organization $organization): array {
        return $this->importer->import($organization, $this->factory->for((int) $organization->id));
    }
}
