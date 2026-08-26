<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeppolAccessPointConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\PeppolAccessPoint;

use App\Plugins\Support\PluginSettingsResolver;
use ERechnungToolkit\Enums\SmlZone;

/**
 * Effektive Provider-Konfiguration: `plugin_settings` der gebundenen
 * Organisation vor `config('plugins.peppol-access-point.*')` (C10-Muster).
 *
 * @phpstan-type PeppolApSettings array{enabled: bool, base_url: string, api_key: string, auth_header: string, auth_scheme: string, send_path: string, receive_path: string, ack_path: string, health_path: string, payload_field: string, message_id_field: string, status_field: string, items_field: string, sender_participant_id: ?string, sender_country: string, sml_zone: SmlZone, lookup_ttl_hours: int}
 */
final class PeppolAccessPointConfig {
    /** @return PeppolApSettings */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(PeppolAccessPointPlugin::ID, $organizationId);

        return [
            'enabled' => $r->enabled(),
            'base_url' => rtrim((string) ($r->string('base_url', '', true) ?? ''), '/'),
            'api_key' => (string) ($r->string('api_key', '', true) ?? ''),
            'auth_header' => (string) ($r->string('auth_header', 'Authorization', true) ?? 'Authorization'),
            // Leerer Präfix ist zulässig (reine API-Key-Header wie `X-Api-Key`).
            'auth_scheme' => trim((string) ($r->settingString('auth_scheme') ?? config('plugins.peppol-access-point.auth_scheme', 'Bearer'))),
            'send_path' => (string) ($r->string('send_path', '/outbox', true) ?? '/outbox'),
            'receive_path' => (string) ($r->string('receive_path', '/inbox', true) ?? '/inbox'),
            'ack_path' => (string) ($r->string('ack_path', '/inbox/{messageId}/acknowledge', true) ?? '/inbox/{messageId}/acknowledge'),
            'health_path' => (string) ($r->string('health_path', '/status', true) ?? '/status'),
            'payload_field' => trim((string) ($r->settingString('payload_field') ?? config('plugins.peppol-access-point.payload_field', 'document'))),
            'message_id_field' => (string) ($r->string('message_id_field', 'messageId', true) ?? 'messageId'),
            'status_field' => (string) ($r->string('status_field', 'status', true) ?? 'status'),
            'items_field' => (string) ($r->string('items_field', 'documents', true) ?? 'documents'),
            'sender_participant_id' => $r->string('sender_participant_id', null, true),
            'sender_country' => strtoupper((string) ($r->string('sender_country', 'DE', true) ?? 'DE')),
            'sml_zone' => SmlZone::tryFrom((string) ($r->string('sml_zone', SmlZone::PRODUCTION->value, true) ?? '')) ?? SmlZone::PRODUCTION,
            'lookup_ttl_hours' => max(0, $r->int('lookup_ttl_hours', 24)),
        ];
    }

    /** Ohne Basis-URL und Schlüssel gibt es keinen Versandweg — nie blind senden. */
    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['base_url'] !== '' && $config['api_key'] !== '';
    }
}
