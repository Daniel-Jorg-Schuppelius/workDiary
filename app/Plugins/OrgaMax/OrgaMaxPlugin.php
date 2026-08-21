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

use APIToolkit\Entities\ID;
use APIToolkit\Exceptions\ApiException;
use App\Models\{Customer, OrgaMaxConnection};
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{ContactSyncer, Plugin};
use App\Plugins\OrgaMax\Api\OrgaMaxClientFactory;
use App\Services\Finance\Accounting\ContactPushService;
use Orgamax\API\Endpoints\CustomersEndpoint;
use Orgamax\API\Endpoints\Settings\AccountSettingEndpoint;
use Orgamax\Entities\Customers\Customer as OrgaMaxCustomer;
use RuntimeException;
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
class OrgaMaxPlugin extends AbstractPlugin implements ContactSyncer {
    public const ID = 'orgamax';

    public const SERVICE_PROVIDER = OrgaMaxServiceProvider::class;

    public function name(): string {
        return 'orgaMAX Buchhaltung';
    }

    public function version(): string {
        return '1.0.0';
    }

    public function description(): string {
        return __('Bindet orgaMAX Buchhaltung über die offizielle OpenAPI an: Kunden-/Lieferanten-/Artikelprojektion, Faktura-Übergabe als orgaMAX-Auftrag sowie Rechnungs-, Zahlungs- und PDF-Projektion. Nicht für orgaMAX ERP.');
    }

    /** @return array<int, \App\Plugins\Contracts\PluginCapability> */
    /**
     * Bewusst leer: Belegübergabe läuft über die
     * {@see \App\Services\Finance\Targets\FacturationTargetRegistry}
     * (Audit 2026-08, W1.6).
     */
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

    /** @return array<int, array<string, mixed>> Eigenes Admin-Panel statt Auto-Form. */
    /**
     * Kontakt-Push (Feature 122, MVP-611): anlegen oder aktualisieren, nie
     * löschen. Gefunden wird über die Kundennummer, die orgaMAX führt.
     */
    public function pushContact(Customer $customer): string {
        $connection = OrgaMaxConnection::query()
            ->where('organization_id', $customer->organization_id)
            ->first();
        if (! $connection instanceof OrgaMaxConnection) {
            throw new RuntimeException('orgaMAX connection is not configured.');
        }

        $endpoint = new CustomersEndpoint(app(OrgaMaxClientFactory::class)->for($connection));
        $number = trim((string) $customer->number);

        $existingId = '';
        if ($number !== '') {
            foreach (($endpoint->search(['number' => $number, 'limit' => 10])?->getValues() ?? []) as $row) {
                if ((string) $row->getNumber() === $number && (string) $row->getId() !== '') {
                    $existingId = (string) $row->getId();

                    break;
                }
            }
        }

        // Eigene USt-IdNr./E-Mail nie an einen Fremdkontakt (MVP-611).
        $fields = app(ContactPushService::class)->withoutOwnIdentity([
            'vat_id' => (string) ($customer->vat_id ?? ''),
            'email' => (string) ($customer->email ?? ''),
        ]);

        $payload = new OrgaMaxCustomer;
        $payload->setName((string) $customer->name);
        if ($number !== '') {
            $payload->setNumber($number);
        }
        if (($fields['email'] ?? '') !== '') {
            $payload->setEmail((string) $fields['email']);
        }
        if (($fields['vat_id'] ?? '') !== '') {
            $payload->setVatNumber((string) $fields['vat_id']);
        }

        if ($existingId !== '') {
            $endpoint->update(new ID($existingId), $payload);

            return $existingId;
        }

        $created = (string) ($endpoint->create($payload)->getData()?->getId() ?? '');
        if ($created === '') {
            throw new RuntimeException('orgaMAX customer push returned no id.');
        }

        return $created;
    }

    public function settingsSchema(): array {
        return [];
    }

    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
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
            (new AccountSettingEndpoint(app(OrgaMaxClientFactory::class)->for($connection)))->get();

            return PluginHealth::ok(__('orgaMAX-API erreichbar.'))
                ->withLatency((int) ((microtime(true) - $started) * 1000));
        } catch (ApiException $e) {
            if (in_array($e->getCode(), [401, 403], true)) {
                return PluginHealth::failing(__('Token abgelaufen oder Scopes entzogen — Verbindung erneuern.'), 'auth');
            }

            return PluginHealth::degraded(__('orgaMAX-API antwortet mit Fehlerstatus :status.', ['status' => $e->getCode()]), 'api_error');
        } catch (Throwable) {
            return PluginHealth::failing(__('orgaMAX-API nicht erreichbar.'), 'unreachable');
        }
    }
}
