<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaperlessPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Paperless;

use App\Enums\CloudIntake\{CloudIntakeConnectionStatus, CloudIntakeProvider};
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Models\Integration\ExternalReference;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{DocumentIntakeOrigins, DocumentIntakeSource, IntakeSourceConnector, LinksExternalRecords, PluginCapability};
use App\Plugins\Paperless\Api\PaperlessClientFactory;
use App\Plugins\Paperless\Services\PaperlessIntake;
use App\Plugins\Support\ApiHealthProbe;
use App\Plugins\Support\Intake\{IntakeAccount, IntakeChangePage, IntakeItem};
use App\Support\Ui\UiAction;
use Illuminate\Database\Eloquent\Model;
use Psr\Http\Message\StreamInterface;

/**
 * Paperless-ngx als Quelle des Cloud-Dokumenteingangs (Feature 170, Phase 158):
 * Dokumente einer gewählten Quelle (alle, Tag, Korrespondent oder Dokumenttyp)
 * laufen über die vorhandene Eingangspipeline in Rechnungseingang oder
 * Dokumentablage, nur lesend. Basis-URL und Token stehen in den
 * Plugin-Einstellungen; die Verwaltung läuft über die Seite Cloud-Dokumenteingang.
 */
class PaperlessPlugin extends AbstractPlugin implements DocumentIntakeOrigins, DocumentIntakeSource, IntakeSourceConnector, LinksExternalRecords {
    public const ID = 'paperless';

    public const SERVICE_PROVIDER = PaperlessServiceProvider::class;

    public function name(): string {
        return 'Paperless-ngx';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return (string) __('paperless::paperless.plugin.description');
    }

    public function settingsSchema(): array {
        return [
            ['key' => 'base_url', 'label' => __('paperless::paperless.settings.base_url'), 'type' => 'text', 'required' => true, 'help' => __('paperless::paperless.settings.base_url_help')],
            ['key' => 'api_token', 'label' => __('paperless::paperless.settings.api_token'), 'type' => 'password', 'required' => true, 'help' => __('paperless::paperless.settings.api_token_help')],
            ['key' => 'allow_private_network', 'label' => __('paperless::paperless.settings.allow_private_network'), 'type' => 'boolean', 'default' => false, 'help' => __('paperless::paperless.settings.allow_private_network_help')],
        ];
    }

    public function capabilities(): array {
        return [PluginCapability::DocumentIntake];
    }

    public function intakeAccount(CloudDocumentConnection $connection): IntakeAccount {
        return app(PaperlessIntake::class)->account($connection);
    }

    public function intakeContainers(CloudDocumentConnection $connection, ?string $search = null): array {
        return app(PaperlessIntake::class)->containers($connection, $search);
    }

    public function intakeChanges(CloudDocumentConnection $connection, ?string $checkpoint): IntakeChangePage {
        return app(PaperlessIntake::class)->changes($connection, $checkpoint);
    }

    public function intakeDownload(CloudDocumentConnection $connection, IntakeItem $item): StreamInterface {
        return app(PaperlessIntake::class)->download($connection, $item);
    }

    public function intakeImported(CloudDocumentConnection $connection, IntakeItem $item, Model $imported): void {
        app(PaperlessIntake::class)->recordOrigin($connection, $item, $imported);
    }

    public function intakeConnectAction(): UiAction {
        return new UiAction('add', (string) __('paperless::paperless.action.connect'), route('admin.cloud-intake.paperless.connect-form'), modal: true);
    }

    public function externalRecordUrl(ExternalReference $reference): ?string {
        return $reference->plugin_id === self::ID ? app(PaperlessIntake::class)->sourceUrl($reference) : null;
    }

    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }
        if (! PaperlessConfig::isConfigured((int) $organization->id)) {
            return PluginHealth::degraded((string) __('paperless::paperless.health.not_configured'), 'not_configured');
        }
        $connection = CloudDocumentConnection::query()
            ->where('organization_id', $organization->id)
            ->where('provider', CloudIntakeProvider::Paperless->value)
            ->first();
        if (! $connection instanceof CloudDocumentConnection) {
            return PluginHealth::degraded((string) __('paperless::paperless.health.not_connected'), 'not_connected');
        }
        if (in_array($connection->status, [CloudIntakeConnectionStatus::ReauthRequired, CloudIntakeConnectionStatus::Blocked], true) || $connection->last_error !== null) {
            return PluginHealth::degraded((string) __('paperless::paperless.health.attention'), 'connection');
        }

        return ApiHealthProbe::probe($this->name(), fn () => app(PaperlessClientFactory::class)->for((int) $organization->id)->probe());
    }
}
