<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeppolAccessPointPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\PeppolAccessPoint;

use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{PeppolTransportProvider, PluginCapability, SettingsField};
use App\Plugins\PeppolAccessPoint\Api\RestAccessPointClient;
use App\Services\Peppol\PeppolParticipantService;
use ERechnungToolkit\Contracts\AccessPointClientInterface;
use ERechnungToolkit\Enums\SmlZone;

/**
 * Anbindung an einen zertifizierten Peppol-Access-Point-Provider
 * (Feature 066, MVP-734).
 *
 * **Abgrenzung:** WorkDiary betreibt keinen eigenen AS4-Access-Point.
 * Zertifizierung, PKI und Betrieb sind kein Produktziel; angebunden wird ein
 * Provider, der genau das leistet. Alles Fachneutrale — Teilnehmerkennungen,
 * SBDH-Umschlag, SML/SMP-Auflösung, Peppol-BIS-Prüfung — kommt aus dem
 * php-erechnung-toolkit.
 *
 * **Generische Naht:** Ohne konkreten Providervertrag wären feste Endpunkte
 * und Feldnamen geraten. Stattdessen konfiguriert die Organisation Basis-URL,
 * Pfade, Auth-Header und JSON-Feldnamen; die Feinabstimmung mit dem gewählten
 * Provider ist ein Pilotschritt.
 */
class PeppolAccessPointPlugin extends AbstractPlugin implements PeppolTransportProvider {
    public const ID = 'peppol-access-point';

    public const SERVICE_PROVIDER = PeppolAccessPointServiceProvider::class;

    public function name(): string {
        return 'Peppol Access Point';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return (string) __('peppol.plugin.description');
    }

    public function capabilities(): array {
        return [
            PluginCapability::PeppolTransport,
        ];
    }

    public function peppolAccessPoint(?int $organizationId = null): ?AccessPointClientInterface {
        $config = PeppolAccessPointConfig::resolve($organizationId);
        if ($config['base_url'] === '' || $config['api_key'] === '') {
            return null;
        }

        return new RestAccessPointClient($config);
    }

    public function peppolSenderId(?int $organizationId = null): ?string {
        return PeppolAccessPointConfig::resolve($organizationId)['sender_participant_id'];
    }

    /** @return list<array<string, mixed>> */
    public function settingsSchema(): array {
        $zones = [];
        foreach (SmlZone::cases() as $zone) {
            $zones[$zone->value] = $zone->label();
        }

        return [
            SettingsField::url('base_url', (string) __('peppol.settings.base_url'), required: true,
                help: (string) __('peppol.settings.base_url_help'))->toArray(),
            SettingsField::password('api_key', (string) __('peppol.settings.api_key'), required: true,
                help: (string) __('peppol.settings.api_key_help'))->toArray(),
            SettingsField::text('auth_header', (string) __('peppol.settings.auth_header'), default: 'Authorization',
                help: (string) __('peppol.settings.auth_header_help'))->toArray(),
            SettingsField::text('auth_scheme', (string) __('peppol.settings.auth_scheme'), default: 'Bearer',
                help: (string) __('peppol.settings.auth_scheme_help'))->toArray(),
            SettingsField::text('send_path', (string) __('peppol.settings.send_path'), default: '/outbox')->toArray(),
            SettingsField::text('receive_path', (string) __('peppol.settings.receive_path'), default: '/inbox')->toArray(),
            SettingsField::text('ack_path', (string) __('peppol.settings.ack_path'), default: '/inbox/{messageId}/acknowledge',
                help: (string) __('peppol.settings.ack_path_help'))->toArray(),
            SettingsField::text('health_path', (string) __('peppol.settings.health_path'), default: '/status')->toArray(),
            SettingsField::text('payload_field', (string) __('peppol.settings.payload_field'), default: 'document',
                help: (string) __('peppol.settings.payload_field_help'))->toArray(),
            SettingsField::text('message_id_field', (string) __('peppol.settings.message_id_field'), default: 'messageId')->toArray(),
            SettingsField::text('status_field', (string) __('peppol.settings.status_field'), default: 'status')->toArray(),
            SettingsField::text('items_field', (string) __('peppol.settings.items_field'), default: 'documents')->toArray(),
            SettingsField::text('sender_participant_id', (string) __('peppol.settings.sender_participant_id'), required: true,
                help: (string) __('peppol.settings.sender_participant_id_help'))->toArray(),
            SettingsField::text('sender_country', (string) __('peppol.settings.sender_country'), default: 'DE',
                help: (string) __('peppol.settings.sender_country_help'))->toArray(),
            SettingsField::select('sml_zone', (string) __('peppol.settings.sml_zone'), $zones, default: SmlZone::PRODUCTION->value,
                help: (string) __('peppol.settings.sml_zone_help'))->toArray(),
            SettingsField::number('lookup_ttl_hours', (string) __('peppol.settings.lookup_ttl_hours'), default: 24,
                help: (string) __('peppol.settings.lookup_ttl_hours_help'))->toArray(),
        ];
    }

    /**
     * Health-Check je Organisation: Zugangsdaten und eigene Teilnehmer-ID
     * vorhanden, danach der Status-Endpunkt des Providers.
     */
    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }

        $config = PeppolAccessPointConfig::resolve((int) $organization->id);
        if ($config['base_url'] === '' || $config['api_key'] === '') {
            return PluginHealth::degraded(__('peppol.health.not_configured'), code: 'not_configured');
        }

        if (PeppolParticipantService::parse($config['sender_participant_id']) === null) {
            return PluginHealth::degraded(__('peppol.health.sender_invalid'), code: 'sender_invalid');
        }

        $client = new RestAccessPointClient($config);

        return PluginHealth::pingHealth(
            ping: static fn (): bool => $client->isAvailable(),
            unreachableMessage: (string) __('peppol.health.unreachable'),
            okMessage: (string) __('peppol.health.ok', ['url' => $config['base_url']]),
            errorStatus: PluginHealth::STATUS_FAILING,
        );
    }
}
