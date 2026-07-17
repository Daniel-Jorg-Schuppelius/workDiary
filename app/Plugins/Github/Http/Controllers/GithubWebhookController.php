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

use App\Models\Organization;
use App\Plugins\Github\Api\GithubClientFactory;
use App\Plugins\Github\{GithubConfig, GithubPlugin};
use App\Plugins\Github\Services\GithubIssueImporter;
use App\Plugins\Support\GitIssueImport\AbstractGitWebhookController;
use App\Plugins\Support\WebhookSignature;
use Illuminate\Http\Request;

/**
 * GitHub-Webhook (Feature 060, MVP-129): stößt einen idempotenten Issue-Import
 * an (Zammad-Muster). Autorisierung ausschließlich über die HMAC-Signatur
 * (`X-Hub-Signature-256: sha256=…`) des Raw-Bodys gegen das je Organisation
 * verschlüsselt hinterlegte Shared-Secret. GitHub liefert nicht automatisch
 * nach (kein Auto-Redelivery), das `since`-Polling
 * ({@see \App\Plugins\Github\Console\GithubSyncCommand}) schließt Lücken.
 */
class GithubWebhookController extends AbstractGitWebhookController {
    public function __construct(
        private readonly GithubClientFactory $factory,
        private readonly GithubIssueImporter $importer,
    ) {}

    protected function pluginId(): string {
        return GithubPlugin::ID;
    }

    protected function credentialKey(): string {
        return 'webhook_secret';
    }

    protected function isConfigured(int $organizationId): bool {
        return GithubConfig::isConfigured($organizationId);
    }

    /** GitHub-Signatur: sha256=HMAC-SHA256(body, secret), Konstantzeit-Vergleich. */
    protected function credentialValid(Request $request, string $credential): bool {
        return WebhookSignature::hmacValid((string) $request->getContent(), $credential, (string) $request->header('X-Hub-Signature-256', ''), 'sha256', 'sha256=');
    }

    protected function rejectionStatus(): string {
        return 'invalid_signature';
    }

    protected function import(Organization $organization): array {
        return $this->importer->import($organization, $this->factory->for((int) $organization->id));
    }
}
