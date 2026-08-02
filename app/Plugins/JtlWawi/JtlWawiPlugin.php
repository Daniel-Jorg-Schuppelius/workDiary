<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlWawiPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\JtlWawi;

use App\Models\{JtlConnection, Organization};
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\Plugin;
use App\Plugins\JtlWawi\Api\{JtlApiException, JtlGatewayFactory};
use App\Plugins\Support\PluginOrgContext;
use Throwable;

/**
 * JTL-Wawi-Warenwirtschaft-Plugin (Feature 078, MVP-316–325).
 *
 * Bindet JTL-Wawi über die offizielle REST-API (OnPremise + Cloud-Gateway)
 * als führende Warenwirtschaft an: Artikel-/Lagerprojektion, Bestandslesen
 * über den {@see \App\Contracts\Inventory\InventoryProvider}-Vertrag und
 * idempotente Bestandsbuchungen über die `inventory_outbox`. Keine eigene
 * {@see \App\Plugins\Contracts\PluginCapability} — die Bestandsfähigkeit
 * wird über die Provider-Registry des
 * {@see \App\Services\Inventory\InventoryProviderResolver} deklariert
 * (Lexoffice-Präzedenz: Capability-Enum nur für die dort gebundenen
 * Plugin-Klassen-Interfaces).
 */
class JtlWawiPlugin extends AbstractPlugin {
    public const ID = 'jtl_wawi';

    public const SERVICE_PROVIDER = JtlWawiServiceProvider::class;

    public function name(): string {
        return 'JTL-Wawi';
    }

    public function version(): string {
        return '1.0.0';
    }

    public function description(): string {
        return __('Bindet JTL-Wawi als führende Warenwirtschaft an: Artikel- und Lagerprojektion, Bestände lesen und Bestandsbuchungen idempotent übergeben (OnPremise und Cloud, API im Beta-Status).');
    }

    /** @return array<int, \App\Plugins\Contracts\PluginCapability> */
    public function capabilities(): array {
        return [];
    }

    /** @return array{route: string, label: string, icon: string}|null */
    public function adminPanel(): ?array {
        return [
            'route' => 'admin.jtl.index',
            'label' => __('JTL-Wawi'),
            'icon' => 'archive-box',
        ];
    }

    /** @return array<int, array<string, mixed>> Eigenes Admin-Panel statt Auto-Form. */
    public function settingsSchema(): array {
        return [];
    }

    public function healthCheck(): PluginHealth {
        $organization = PluginOrgContext::currentOrNull();
        if (! $organization instanceof Organization) {
            return PluginHealth::ok(__('Keine Organisation im Kontext.'));
        }

        $connection = JtlConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof JtlConnection) {
            return PluginHealth::degraded(__('Keine JTL-Wawi-Verbindung hinterlegt.'), 'not_configured');
        }

        if ($connection->status === JtlConnection::STATUS_PENDING_REGISTRATION) {
            return PluginHealth::degraded(__('App-Registrierung wartet auf Freigabe in JTL-Wawi.'), 'registration_pending');
        }

        if ($connection->status === JtlConnection::STATUS_BLOCKED) {
            return PluginHealth::failing(__('Verbindung blockiert (:reason) — Details im JTL-Admin.', ['reason' => (string) $connection->blocked_reason]), 'blocked');
        }

        if (! $connection->isActive()) {
            return PluginHealth::degraded(__('Verbindung unvollständig oder getrennt.'), 'inactive');
        }

        try {
            $info = app(JtlGatewayFactory::class)->for($connection)->info();
            $version = (string) ($info['version'] ?? '');
            if ($version !== '' && $version !== $connection->detected_version) {
                $connection->forceFill(['detected_version' => $version])->save();
            }

            return PluginHealth::ok(__('Verbunden (:mode, Wawi :version).', [
                'mode' => $connection->isOnPremise() ? 'OnPremise' : 'Cloud',
                'version' => $version !== '' ? $version : __('unbekannt'),
            ]));
        } catch (JtlApiException $e) {
            if ($e->isAuthError()) {
                return PluginHealth::failing(__('API lehnt die Anmeldung ab — Verbindung/Scopes erneuern.'), 'auth');
            }
            if ($e->status === 429) {
                return PluginHealth::degraded(__('API drosselt Anfragen (Rate-Limit).'), 'rate_limited');
            }

            return PluginHealth::failing(__('API-Fehler (HTTP :status).', ['status' => $e->status]), 'api_error');
        } catch (Throwable $e) {
            return PluginHealth::failing(__('API nicht erreichbar (:class).', ['class' => class_basename($e)]), 'unreachable');
        }
    }
}
