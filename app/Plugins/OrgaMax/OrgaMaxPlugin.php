<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax;

use App\Models\{OrgaMaxConnection, Organization, PluginSetting};
use App\Plugins\Contracts\Plugin;
use App\Plugins\OrgaMax\Api\{OrgaMaxApiException, OrgaMaxClientFactory};
use App\Plugins\{PluginDefaults, PluginHealth};
use Throwable;

/**
 * orgaMAX-Buchhaltung-Plugin (Feature 077, MVP-305–315).
 *
 * Bindet ausschließlich orgaMAX **Buchhaltung** über die offizielle OpenAPI
 * an (https://api.orgamax.de/openapi, OAS 3.0.2) — die nicht unterstützte
 * orgaMAX-ERP-Variante wird sichtbar zurückgewiesen; keine Screen-Scraping-,
 * Datenbank- oder undokumentierten Anbindungen. Stammdaten laufen über
 * ExternalReference + Integrations-Inbox (keine Schattenstammdaten), die
 * Faktura-Übergabe über den {@see \App\Services\Finance\Targets\OrgaMaxTarget}
 * ({@see \App\Enums\Finance\TransferTarget::OrgaMax}). Wie JTL-Wawi ohne
 * eigene {@see \App\Plugins\Contracts\PluginCapability} — die Fähigkeiten
 * hängen an FacturationTarget-/Outbox-Verträgen.
 */
class OrgaMaxPlugin implements Plugin {
    use PluginDefaults;

    public const ID = 'orgamax';

    public const SERVICE_PROVIDER = OrgaMaxServiceProvider::class;

    public function id(): string {
        return self::ID;
    }

    public function name(): string {
        return 'orgaMAX Buchhaltung';
    }

    public function version(): string {
        return '1.0.0';
    }

    public function description(): string {
        return __('Bindet orgaMAX Buchhaltung über die offizielle OpenAPI an: Kunden-/Lieferanten-/Artikelprojektion, Faktura-Übergabe als orgaMAX-Auftrag sowie Rechnungs-, Zahlungs- und PDF-Projektion. Nicht für orgaMAX ERP.');
    }

    public function isEnabled(): bool {
        $organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        if ($organization instanceof Organization) {
            $setting = PluginSetting::forOrganization($organization->id, self::ID);
            if ($setting->exists) {
                return (bool) $setting->enabled;
            }
        }

        return (bool) config('plugins.' . self::ID . '.enabled', false);
    }

    /** @return array<int, \App\Plugins\Contracts\PluginCapability> */
    public function capabilities(): array {
        return [];
    }

    /** @return array{route: string, label: string, icon: string}|null */
    public function adminPanel(): ?array {
        return [
            'route' => 'admin.orgamax.index',
            'label' => __('orgaMAX Buchhaltung'),
            'icon' => 'calculator',
        ];
    }

    public function serviceProvider(): ?string {
        return self::SERVICE_PROVIDER;
    }

    /** @return array<int, array<string, mixed>> Eigenes Admin-Panel statt Auto-Form. */
    public function settingsSchema(): array {
        return [];
    }

    public function isPerOrganization(): bool {
        return true;
    }

    public function healthCheck(): PluginHealth {
        $organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        if (! $organization instanceof Organization) {
            return PluginHealth::ok(__('Keine Organisation im Kontext.'));
        }

        $connection = OrgaMaxConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof OrgaMaxConnection) {
            return PluginHealth::degraded(__('Keine orgaMAX-Verbindung hinterlegt.'), 'not_configured');
        }
        if ($connection->status === OrgaMaxConnection::STATUS_BLOCKED) {
            return PluginHealth::failing(__('Verbindung blockiert (:reason) — Details im orgaMAX-Admin.', ['reason' => (string) $connection->blocked_reason]), 'blocked');
        }
        if (in_array($connection->status, [OrgaMaxConnection::STATUS_PENDING_CALLBACK, OrgaMaxConnection::STATUS_PENDING_CONFIRMATION], true)) {
            return PluginHealth::degraded(__('Verbindung wartet auf Callback bzw. Kontobestätigung.'), 'pending');
        }
        if (! $connection->isActive()) {
            return PluginHealth::degraded(__('Verbindung unvollständig oder getrennt.'), 'inactive');
        }

        try {
            $started = microtime(true);
            app(OrgaMaxClientFactory::class)->for($connection)->accountSettings();

            return PluginHealth::ok(__('orgaMAX-API erreichbar.'))
                ->withLatency((int) ((microtime(true) - $started) * 1000));
        } catch (OrgaMaxApiException $e) {
            if ($e->isAuthError()) {
                return PluginHealth::failing(__('Token abgelaufen oder Scopes entzogen — Verbindung erneuern.'), 'auth');
            }

            return PluginHealth::degraded(__('orgaMAX-API antwortet mit Fehlerstatus :status.', ['status' => $e->status]), 'api_error');
        } catch (Throwable) {
            return PluginHealth::failing(__('orgaMAX-API nicht erreichbar.'), 'unreachable');
        }
    }
}
