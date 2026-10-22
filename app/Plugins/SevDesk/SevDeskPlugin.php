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

use APIToolkit\Exceptions\ApiException;
use App\Enums\Numbering\NumberScope;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{ContactNumberIssuer, ContactNumberPreview, ContactSyncer, Plugin, PluginCapability};
use App\Plugins\SevDesk\Api\{SevDeskClient, SevDeskClientFactory};
use App\Plugins\SevDesk\Exceptions\SevDeskApiException;
use App\Services\Finance\Accounting\ContactPushService;
use App\Services\Numbering\NumberSequenceService;
use App\Services\Stammdaten\{ContactIdentity, ContactNumberAdopter};
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
 * - Faktura-Übergabe über {@see \App\Plugins\SevDesk\Services\SevDeskTarget}
 *   ({@see \App\Enums\Finance\TransferTarget::SevDesk}): sevDesk führt die
 *   Rechnung (Entwurf, Status 100) — keine parallele lokale Fakturierung,
 *   `enshrine` wird nie aufgerufen.
 * - „Update 2.0" ist Buchhaltungslogik je Account: der Healthcheck probt
 *   GET /Tools/bookkeepingSystemVersion und erneuert den Versions-Cache
 *   des Mandanten.
 */
class SevDeskPlugin extends AbstractPlugin implements ContactNumberIssuer, ContactNumberPreview, ContactSyncer {
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

    /**
     * Angekündigt wird, was die Klasse selbst implementiert (Kontakt-Push).
     * Die Beleg-/Rechnungsübergabe bleibt ohne Capability: sie ist ein
     * {@see \App\Services\Finance\Targets\FacturationTarget} und wird über
     * die {@see \App\Services\Finance\Targets\FacturationTargetRegistry} geführt.
     * Ein Capability-Case dafür brächte nur eine zweite Registry bzw. eine dünne
     * Delegation der Plugin-Klasse auf den Target-Service (Audit 2026-08, W1.6).
     *
     * @return array<int, PluginCapability>
     */
    public function capabilities(): array {
        return [PluginCapability::ContactSync];
    }

    /**
     * Kontakt-Push (Feature 122, MVP-611): anlegen oder aktualisieren, nie
     * löschen. Ein verknüpfter Kontakt wird über seine ID aktualisiert; ein
     * Treffer über die Kundennummer nur, wenn er derselbe Kontakt ist
     * (MVP-1133). Vergibt sevDesk die Nummer, holt die App die nächste freie
     * und übernimmt sie (MVP-1131); eine Entwurfsnummer geht nie hinüber.
     */
    public function pushContact(Customer $customer): string {
        \App\Services\Stammdaten\CollectiveContacts::assertPushable($customer);
        $client = app(SevDeskClientFactory::class)->for((int) $customer->organization_id);
        $number = self::transferableNumber($customer);

        $existingId = (string) ExternalReference::query()
            ->forPlugin($customer->organization_id, self::ID, ExternalReference::TYPE_CONTACT)
            ->forReferenceable($customer)
            ->value('external_id');
        if ($existingId === '' && $number !== '') {
            $existingId = (string) ($this->contactByNumber($client, $customer, $number)['id'] ?? '');
            // Führt die Buchhaltung die Stammdaten, wird ein Treffer nur verknüpft, nicht überschrieben.
            if ($existingId !== '' && ! app(ContactPushService::class)->pushAllowed()) {
                app(ContactNumberAdopter::class)->linked($customer, self::ID, $existingId, $number);

                return $existingId;
            }
        }
        if ($existingId === '' && (string) $customer->number_source === self::ID) {
            $number = $client->nextCustomerNumber();
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

        // Anschrift und Kommunikationswege sind in sevDesk eigene Objekte mit
        // eigenem Endpunkt (MVP-731) — ohne sie bleibt der Kontakt ein Name.
        app(Services\SevDeskContactDetailPushService::class)->push($client, $customer, $contactId);
        app(ContactNumberAdopter::class)->linked($customer, self::ID, $contactId, (string) ($result['customerNumber'] ?? $number));

        return $contactId;
    }

    public function issuableNumberScopes(): array {
        return [NumberScope::Customer];
    }

    /** Die Seite ruft bei jedem Aufruf ab; kurz zwischengespeichert, Fehler als leere Nummer. */
    public function nextContactNumber(int $organizationId, NumberScope $scope): ?string {
        if ($scope !== NumberScope::Customer) {
            return null;
        }

        $number = (string) Cache::remember('sevdesk.next_customer_number.' . $organizationId, 60, static function () use ($organizationId): string {
            try {
                return app(SevDeskClientFactory::class)->for($organizationId)->nextCustomerNumber();
            } catch (ApiException|RuntimeException) {
                return '';
            }
        });

        return $number !== '' ? $number : null;
    }

    /**
     * Nummer, die nach sevDesk geht: keine Entwurfsnummer und keine, wenn
     * sevDesk selbst vergibt (MVP-1131).
     */
    private static function transferableNumber(Customer $customer): string {
        return (string) $customer->number_source === self::ID || NumberSequenceService::isDraft($customer->number)
            ? ''
            : trim((string) $customer->number);
    }

    /**
     * Kontakt mit dieser Kundennummer in sevDesk. Gehört die Nummer dort einem
     * anderen Kontakt, bricht die Übertragung ab, statt ihn zu überschreiben
     * oder ihm Rechnungen zuzuordnen (MVP-1133).
     *
     * @return array<string, mixed>|null
     */
    private function contactByNumber(SevDeskClient $client, Customer $customer, string $number): ?array {
        foreach ($client->contactsByCustomerNumber($number) as $row) {
            if (! is_array($row) || (string) ($row['customerNumber'] ?? '') !== $number || empty($row['id'] ?? null)) {
                continue;
            }
            app(ContactIdentity::class)->assertRemoteOwner($customer, $number, $this->name(), $row['name'] ?? null, $row['surename'] ?? null, $row['familyname'] ?? null, $row['vatNumber'] ?? null);

            return $row;
        }

        return null;
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

        if (! app(SevDeskClientFactory::class)->isConfigured((int) $organization->id)) {
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
