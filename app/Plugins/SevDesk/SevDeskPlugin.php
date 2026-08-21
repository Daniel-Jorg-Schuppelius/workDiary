<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk;

use App\Models\Customer;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{ContactSyncer, Plugin};
use App\Plugins\SevDesk\Api\{SevDeskApiException, SevDeskClient, SevDeskClientFactory};
use App\Services\Finance\Accounting\ContactPushService;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/**
 * sevDesk-Plugin (MVP-125, Bauturbo A4): drittes API-Faktura-Ziel neben
 * Lexoffice und orgaMAX.
 *
 * - Auth: benutzergebundener API-Token (ohne Scopes) gegen
 *   https://my.sevdesk.de/api/v1 — verschlüsselt je Organisation in
 *   plugin_settings (Auto-Form in der Plugin-Karte, kein eigener
 *   Verbindungsfluss wie beim orgaMAX-iid-Callback nötig).
 * - Faktura-Übergabe über {@see \App\Services\Finance\Targets\SevDeskTarget}
 *   ({@see \App\Enums\Finance\TransferTarget::SevDesk}): sevDesk führt die
 *   Rechnung (Entwurf, Status 50) — keine parallele lokale Fakturierung,
 *   `enshrine` wird nie aufgerufen.
 * - „Update 2.0" ist Buchhaltungslogik je Account: der Healthcheck probt
 *   GET /Tools/bookkeepingSystemVersion und erneuert den Versions-Cache
 *   des Mandanten.
 */
class SevDeskPlugin extends AbstractPlugin implements ContactSyncer {
    public const ID = 'sevdesk';

    public const SERVICE_PROVIDER = SevDeskServiceProvider::class;

    public function name(): string {
        return 'sevDesk';
    }

    public function version(): string {
        return '1.0.0';
    }

    public function description(): string {
        return __('Übergibt bestätigte Abrechnungspositionen als Rechnungsentwurf an sevDesk (API-Token gegen my.sevdesk.de): Kontakt-Projektion, idempotente Übergabe mit Quellmarker, Erkennung der Buchhaltungs-Version 1.0/2.0 je Mandant.');
    }

    /** @return array<int, \App\Plugins\Contracts\PluginCapability> Fähigkeiten hängen am FacturationTarget-Vertrag. */
    /**
     * Bewusst leer: Die Fähigkeit dieses Plugins (Beleg-/Rechnungsübergabe) ist
     * ein {@see \App\Services\Finance\Targets\FacturationTarget} und wird über
     * die {@see \App\Services\Finance\Targets\FacturationTargetRegistry} geführt.
     * Ein Capability-Case dafür brächte nur eine zweite Registry bzw. eine dünne
     * Delegation der Plugin-Klasse auf den Target-Service (Audit 2026-08, W1.6).
     */
    public function capabilities(): array {
        return [];
    }

    /**
     * Kontakt-Push (Feature 122, MVP-611): anlegen oder aktualisieren, nie
     * löschen. Gefunden wird über die Kundennummer — sie ist das einzige
     * Feld, das beide Seiten stabil führen.
     */
    public function pushContact(Customer $customer): string {
        $client = app(SevDeskClientFactory::class)->for((int) $customer->organization_id);
        $number = trim((string) $customer->number);

        $existingId = '';
        if ($number !== '') {
            foreach ($client->contactsByCustomerNumber($number) as $row) {
                if (is_array($row) && (string) ($row['customerNumber'] ?? '') === $number && ! empty($row['id'] ?? null)) {
                    $existingId = (string) $row['id'];

                    break;
                }
            }
        }

        // Eigene USt-IdNr./E-Mail nie an einen Fremdkontakt (MVP-611).
        $fields = app(ContactPushService::class)->withoutOwnIdentity([
            'vat_id' => (string) ($customer->vat_id ?? ''),
            'email' => (string) ($customer->email ?? ''),
        ]);

        $payload = array_filter([
            'objectName' => 'Contact',
            'mapAll' => true,
            'name' => (string) $customer->name,
            'customerNumber' => $number !== '' ? $number : null,
            'vatNumber' => ($fields['vat_id'] ?? '') !== '' ? $fields['vat_id'] : null,
            'category' => ['id' => (int) config('plugins.sevdesk.contact_category_id', 3), 'objectName' => 'Category'],
        ], static fn ($value): bool => $value !== null);

        $result = $existingId !== ''
            ? $client->updateContact($existingId, $payload)
            : $client->createContact($payload);

        $contactId = (string) ($result['id'] ?? $existingId);
        if ($contactId === '') {
            throw new RuntimeException('sevDesk contact push returned no id.');
        }

        return $contactId;
    }

    /** @return array<int, array{key: string, label: string, type: string, options?: array<string, string>, help?: string, required?: bool, default?: mixed}> */
    public function settingsSchema(): array {
        return [
            ['key' => 'api_key', 'label' => __('API-Token'), 'type' => 'password', 'required' => true, 'help' => __('Benutzergebundener API-Token aus dem sevDesk-Account (Einstellungen → Benutzer).')],
            ['key' => 'base_url', 'label' => __('API-Basis-URL'), 'type' => 'text', 'default' => 'https://my.sevdesk.de/api/v1'],
            ['key' => 'default_vat_rate', 'label' => __('Standard-USt %'), 'type' => 'text', 'default' => '19'],
        ];
    }

    /**
     * Token-Wechsel invalidiert den Versions-Cache des Mandanten.
     *
     * @param array<string, mixed> $settings
     */
    public function onSettingsSaved(int $organizationId, array $settings): void {
        Cache::forget(SevDeskClient::versionCacheKey($organizationId));
    }

    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }

        $config = SevDeskConfig::resolve((int) $organization->id);
        if (empty($config['api_key'])) {
            return PluginHealth::degraded(__('sevDesk ist nicht konfiguriert (API-Token fehlt).'), 'not_configured');
        }

        try {
            $started = microtime(true);
            // Probe = dokumentierter Versions-Endpunkt; erneuert zugleich den
            // je Mandant gecachten bookkeepingSystemVersion-Wert.
            $version = app(SevDeskClientFactory::class)
                ->for((int) $organization->id)
                ->bookkeepingVersion(fresh: true);

            return PluginHealth::ok(__('sevDesk-API erreichbar (Buchhaltung :version).', ['version' => $version]))
                ->withLatency((int) ((microtime(true) - $started) * 1000));
        } catch (SevDeskApiException $e) {
            if ($e->isAuthError()) {
                return PluginHealth::failing(__('sevDesk lehnt den API-Token ab (401) — Token prüfen.'), 'auth');
            }

            return PluginHealth::degraded(__('sevDesk-API antwortet mit Fehlerstatus :status.', ['status' => $e->status]), 'api_error');
        } catch (Throwable) {
            return PluginHealth::failing(__('sevDesk-API nicht erreichbar.'), 'unreachable');
        }
    }
}
