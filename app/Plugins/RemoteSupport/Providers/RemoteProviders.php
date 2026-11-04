<?php
/*
 * Created on   : Wed Nov 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteProviders.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\RemoteSupport\Providers;

use App\Plugins\RemoteSupport\Api\{AnyDeskClient, PcvisitClient, RustDeskApiServerClient, RustDeskProClient, SplashtopClient, TeamViewerClient};
use InvalidArgumentException;

/**
 * Register der Fernwartungs-Anbieter (Phase 171): die einzige Stelle, die
 * Anbieter kennt. Neue Anbieter kommen hier als {@see RemoteProviderDefinition}
 * hinzu; Konfiguration, Einstellungsschema, Geräte-Referenzen, Tags, Panel,
 * Inbox und Controller lesen das Register.
 *
 * RustDesk tritt als ein Anbieter auf: die Geräte-ID ist die RustDesk-ID des
 * Clients, unabhängig davon, ob Server Pro oder der Open-Source-API-Server
 * die Verbindungen speichert — die Variante ist eine Einstellung.
 */
final class RemoteProviders {
    public const ANYDESK = AnyDeskClient::ID;

    public const TEAMVIEWER = TeamViewerClient::ID;

    public const RUSTDESK = RustDeskProClient::ID;

    public const SPLASHTOP = SplashtopClient::ID;

    public const PCVISIT = PcvisitClient::ID;

    /** @var array<string, RemoteProviderDefinition>|null */
    private static ?array $definitions = null;

    /** @return array<string, RemoteProviderDefinition>  Kennung → Definition, in Anzeigereihenfolge */
    public static function all(): array {
        return self::$definitions ??= self::build();
    }

    /** @return list<string> */
    public static function ids(): array {
        return array_keys(self::all());
    }

    /** @return array<string, string>  Kennung → Bezeichnung */
    public static function labels(): array {
        return array_map(static fn (RemoteProviderDefinition $d): string => $d->label, self::all());
    }

    public static function find(string $id): ?RemoteProviderDefinition {
        return self::all()[$id] ?? null;
    }

    public static function get(string $id): RemoteProviderDefinition {
        return self::find($id) ?? throw new InvalidArgumentException("Unknown remote provider: {$id}");
    }

    public static function label(string $id): string {
        $definition = self::find($id);

        return $definition !== null ? $definition->label : ucfirst($id);
    }

    /** @return array<string, string>  Kennung → external_type der Geräte-Referenz */
    public static function deviceTypes(): array {
        return array_map(static fn (RemoteProviderDefinition $d): string => $d->deviceType(), self::all());
    }

    /** @return array<string, RemoteProviderDefinition> */
    private static function build(): array {
        $defs = [
            new RemoteProviderDefinition(
                id: self::ANYDESK,
                label: 'AnyDesk',
                placeholder: '123 456 789',
                configFields: [
                    ['key' => 'license_id', 'cast' => 'string', 'default' => null],
                    ['key' => 'api_key', 'cast' => 'string', 'default' => null],
                    ['key' => 'base_url', 'cast' => 'string', 'default' => AnyDeskClient::DEFAULT_BASE_URL],
                ],
                settings: static fn (): array => [
                    ['key' => 'anydesk_license_id', 'label' => (string) __('AnyDesk Lizenz-ID'), 'type' => 'text', 'help' => (string) __('Numerische Lizenz-ID (z. B. 1438129266231705) — nicht der Lizenz-Schlüssel aus Buchstaben/Ziffern.')],
                    ['key' => 'anydesk_api_key', 'label' => (string) __('AnyDesk API-Passwort'), 'type' => 'password', 'help' => (string) __('API-Passwort der AnyDesk-Lizenz (Request-Signierung).')],
                    ['key' => 'anydesk_base_url', 'label' => (string) __('AnyDesk API-Basis-URL'), 'type' => 'text', 'default' => AnyDeskClient::DEFAULT_BASE_URL],
                ],
                factory: static fn (array $c): RemoteProvider => new AnyDeskClient($c['license_id'], $c['api_key'], (string) $c['base_url']),
            ),
            new RemoteProviderDefinition(
                id: self::TEAMVIEWER,
                label: 'TeamViewer',
                placeholder: '123 456 789',
                configFields: [
                    ['key' => 'license_id', 'cast' => 'string', 'default' => null],
                    ['key' => 'api_key', 'cast' => 'string', 'default' => null],
                    ['key' => 'base_url', 'cast' => 'string', 'default' => TeamViewerClient::DEFAULT_BASE_URL],
                ],
                settings: static fn (): array => [
                    ['key' => 'teamviewer_api_key', 'label' => (string) __('TeamViewer Script-Token'), 'type' => 'password', 'help' => (string) __('Script-Token mit Connection-Report-Berechtigung.')],
                    ['key' => 'teamviewer_base_url', 'label' => (string) __('TeamViewer API-Basis-URL'), 'type' => 'text', 'default' => TeamViewerClient::DEFAULT_BASE_URL],
                ],
                factory: static fn (array $c): RemoteProvider => new TeamViewerClient($c['api_key'], (string) $c['base_url']),
            ),
            new RemoteProviderDefinition(
                id: self::RUSTDESK,
                label: 'RustDesk',
                placeholder: '123 456 789',
                configFields: [
                    ['key' => 'flavor', 'cast' => 'string', 'default' => RustDeskProClient::FLAVOR],
                    ['key' => 'base_url', 'cast' => 'string', 'default' => null],
                    ['key' => 'api_token', 'cast' => 'string', 'default' => null],
                    ['key' => 'username', 'cast' => 'string', 'default' => null],
                    ['key' => 'password', 'cast' => 'string', 'default' => null],
                    ['key' => 'timezone', 'cast' => 'string', 'default' => null],
                ],
                settings: static fn (): array => [
                    ['key' => 'rustdesk_flavor', 'label' => (string) __('RustDesk-Server'), 'type' => 'select', 'default' => RustDeskProClient::FLAVOR, 'options' => [
                        RustDeskProClient::FLAVOR => (string) __('RustDesk Server Pro (API-Token)'),
                        RustDeskApiServerClient::FLAVOR => (string) __('Open-Source-API-Server (Anmeldung mit Benutzername und Passwort)'),
                    ], 'help' => (string) __('Der kostenlose RustDesk-Server speichert keine Verbindungen. Sitzungen liefert nur ein API-Server, den die Clients unter „API-Server“ eingetragen haben: Server Pro oder der Open-Source-API-Server (rustdesk-api).')],
                    ['key' => 'rustdesk_base_url', 'label' => (string) __('RustDesk API-Basis-URL'), 'type' => 'url', 'help' => (string) __('Adresse des API-Servers, z. B. https://rustdesk.example.com — bei Server Pro die Web-Konsole, beim Open-Source-API-Server dessen Adresse mit Port.')],
                    ['key' => 'rustdesk_api_token', 'label' => (string) __('RustDesk API-Token (Server Pro)'), 'type' => 'password', 'help' => (string) __('Server Pro: Einstellungen → Tokens → Erstellen, Berechtigung „Audit Log“.')],
                    ['key' => 'rustdesk_username', 'label' => (string) __('RustDesk Benutzername (Open-Source-API-Server)'), 'type' => 'text', 'help' => (string) __('Administrator des API-Servers; die Verbindungsliste ist Administratoren vorbehalten.')],
                    ['key' => 'rustdesk_password', 'label' => (string) __('RustDesk Passwort (Open-Source-API-Server)'), 'type' => 'password'],
                    ['key' => 'rustdesk_timezone', 'label' => (string) __('RustDesk Serverzeitzone (Open-Source-API-Server)'), 'type' => 'text', 'help' => (string) __('Der Open-Source-API-Server speichert den Beginn in seiner Serverzeit ohne Zone. Leer = Zeitzone der Organisation.')],
                ],
                factory: static function (array $c): RemoteProvider {
                    return ($c['flavor'] ?? RustDeskProClient::FLAVOR) === RustDeskApiServerClient::FLAVOR
                        ? new RustDeskApiServerClient($c['base_url'], $c['username'], $c['password'], $c['timezone'])
                        : new RustDeskProClient($c['base_url'], $c['api_token']);
                },
            ),
            new RemoteProviderDefinition(
                id: self::SPLASHTOP,
                label: 'Splashtop',
                placeholder: '4a938402feabe3add440fc7cbf3eed14',
                configFields: [
                    ['key' => 'region', 'cast' => 'string', 'default' => SplashtopClient::DEFAULT_REGION],
                    ['key' => 'api_token', 'cast' => 'string', 'default' => null],
                    ['key' => 'team_id', 'cast' => 'string', 'default' => null],
                    ['key' => 'service_kinds', 'cast' => 'string', 'default' => SplashtopClient::DEFAULT_SERVICE_KINDS],
                ],
                settings: static fn (): array => [
                    ['key' => 'splashtop_region', 'label' => (string) __('Splashtop Region'), 'type' => 'select', 'default' => SplashtopClient::DEFAULT_REGION, 'options' => [
                        'eu' => 'EU (webapi.splashtop.eu)',
                        'com' => 'Global / US (webapi.splashtop.com)',
                        'nr' => 'Ozeanien (webapi.splashtop.nr)',
                    ], 'help' => (string) __('Region des Splashtop-Kontos; die Open API antwortet nur auf dem Server der eigenen Region.')],
                    ['key' => 'splashtop_api_token', 'label' => (string) __('Splashtop API-Token'), 'type' => 'password', 'help' => (string) __('Web-Konsole → Kontoeinstellungen → Web API Tokens (nur Team-Owner und Super-Admins, Splashtop Enterprise). Ein nur lesender Token genügt.')],
                    ['key' => 'splashtop_team_id', 'label' => (string) __('Splashtop Team-ID'), 'type' => 'text', 'help' => (string) __('Optional. Leer = Team des Tokens (aus den Kontoinformationen).')],
                    ['key' => 'splashtop_service_kinds', 'label' => (string) __('Splashtop Sitzungsarten'), 'type' => 'text', 'default' => SplashtopClient::DEFAULT_SERVICE_KINDS, 'help' => (string) __('Kommagetrennt: unattended_remote (unbeaufsichtigt), attended_remote (SOS), unattended_file, unattended_chat, unattended_cmpt, unattended_ssh, unattended_sftp.')],
                ],
                factory: static fn (array $c): RemoteProvider => new SplashtopClient($c['api_token'], (string) $c['region'], $c['team_id'], (string) $c['service_kinds']),
            ),
            new RemoteProviderDefinition(
                id: self::PCVISIT,
                label: 'pcvisit',
                placeholder: 'Computer-UUID aus pcvisit',
                configFields: [
                    ['key' => 'user_token', 'cast' => 'string', 'default' => null],
                    ['key' => 'integrator_token', 'cast' => 'string', 'default' => null],
                    ['key' => 'endpoint', 'cast' => 'string', 'default' => PcvisitClient::DEFAULT_ENDPOINT],
                ],
                settings: static fn (): array => [
                    ['key' => 'pcvisit_user_token', 'label' => (string) __('pcvisit Nutzer-API-Token'), 'type' => 'password', 'help' => (string) __('Jeder pcvisit-Benutzer erzeugt seinen Token in der pcvisit Web App unter Kontoeinstellungen.')],
                    ['key' => 'pcvisit_integrator_token', 'label' => (string) __('pcvisit Integrator-Token'), 'type' => 'password', 'help' => (string) __('Vergibt pcvisit an den Softwarehersteller. Leer = Token des Betreibers (PCVISIT_INTEGRATOR_TOKEN).')],
                ],
                factory: static fn (array $c, ?int $organizationId): RemoteProvider => new PcvisitClient($c['integrator_token'], $c['user_token'], (string) $c['endpoint'], $organizationId),
            ),
        ];

        $byId = [];
        foreach ($defs as $def) {
            $byId[$def->id] = $def;
        }

        return $byId;
    }
}
