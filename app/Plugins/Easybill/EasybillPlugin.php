<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Easybill;

use App\Enums\Numbering\NumberScope;
use App\Enums\Stammdaten\ContactSalutation;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{ContactNumberIssuer, ContactSyncer, Plugin, PluginCapability};
use App\Plugins\Easybill\Api\{EasybillClient, EasybillClientFactory};
use App\Plugins\Easybill\Exceptions\EasybillApiException;
use App\Services\Finance\Accounting\ContactPushService;
use App\Services\Numbering\NumberSequenceService;
use App\Services\Stammdaten\{ContactIdentity, ContactNumberAdopter, ContactPersons};
use RuntimeException;
use Throwable;

/**
 * easybill-Plugin (MVP-431, Phase 40): weiteres API-Faktura-Ziel neben
 * Lexoffice, orgaMAX und sevDesk.
 *
 * - Auth: API-Key als `Authorization: Bearer <key>` gegen
 *   https://api.easybill.de/rest/v1 — verschlüsselt je Organisation in
 *   plugin_settings (Auto-Form der Plugin-Karte).
 * - Faktura-Übergabe über {@see \App\Plugins\Easybill\Services\EasybillTarget}
 *   ({@see \App\Enums\Finance\TransferTarget::Easybill}): easybill führt die
 *   Rechnung — Übergabe als Entwurf, `/documents/{id}/done` wird nie gerufen.
 * - Rate-Limits sind tarifabhängig (PLUS 10, BUSINESS 60 req/min) — das
 *   Intervall kommt aus dem Setting `rate_limit_per_minute`.
 */
class EasybillPlugin extends AbstractPlugin implements ContactNumberIssuer, ContactSyncer {
    public const ID = 'easybill';

    public const SERVICE_PROVIDER = EasybillServiceProvider::class;

    public function name(): string {
        return 'easybill';
    }

    public function version(): string {
        return '1.0.0';
    }

    public function description(): string {
        return __('Übergibt bestätigte Abrechnungspositionen als Rechnungsentwurf an easybill (Bearer-API-Key gegen api.easybill.de): Kunden-Projektion, idempotente Übergabe mit Quellmarker, optionaler Rückabruf fertiggestellter Belege (PDF/E-Rechnung) ins DMS.');
    }

    /**
     * Angekündigt wird, was die Klasse selbst implementiert (Kontakt-Push).
     * Die Übergabe läuft über die
     * {@see \App\Services\Finance\Targets\FacturationTargetRegistry}, der
     * Beleg-Pull über {@see \App\Plugins\Easybill\Services\EasybillDocumentPullService}
     * — eigene Registries/Services statt Plugin-Interfaces (Audit 2026-08, W1.6).
     *
     * @return array<int, PluginCapability>
     */
    public function capabilities(): array {
        return [PluginCapability::ContactSync];
    }

    /**
     * Kontakt-Push (Feature 122, MVP-611): anlegen oder aktualisieren, nie
     * löschen. Ein verknüpfter Kunde wird über seine ID aktualisiert; ein
     * Treffer über die Kundennummer nur, wenn er derselbe Kunde ist
     * (MVP-1133). Vergibt easybill die Nummer, wird ohne Nummer angelegt und
     * die vergebene übernommen (MVP-1131); eine Entwurfsnummer geht nie hinüber.
     */
    public function pushContact(Customer $customer): string {
        \App\Services\Stammdaten\CollectiveContacts::assertPushable($customer);
        $client = app(EasybillClientFactory::class)->for((int) $customer->organization_id);
        $issues = (string) $customer->number_source === self::ID;
        $number = self::transferableNumber($customer);

        $existingId = (string) ExternalReference::query()
            ->forPlugin($customer->organization_id, self::ID, ExternalReference::TYPE_CONTACT)
            ->forReferenceable($customer)
            ->value('external_id');
        if ($existingId === '' && $number !== '') {
            $existingId = (string) ($this->customerByNumber($client, $customer, $number)['id'] ?? '');
            // Führt die Buchhaltung die Stammdaten, wird ein Treffer nur verknüpft, nicht überschrieben.
            if ($existingId !== '' && ! app(ContactPushService::class)->pushAllowed()) {
                app(ContactNumberAdopter::class)->linked($customer, self::ID, $existingId, $number);

                return $existingId;
            }
        }

        // Eigene USt-IdNr./E-Mail nie an einen Fremdkontakt (MVP-611).
        $fields = app(ContactPushService::class)->withoutOwnIdentity([
            'vat_id' => (string) ($customer->vat_id ?? ''),
            'email' => (string) ($customer->email ?? ''),
        ]);

        // Hauptansprechpartner in die Namensfelder des easybill-Kunden (MVP-1138).
        $contact = ContactPersons::primaryOf($customer);

        $payload = array_filter([
            'company_name' => (string) $customer->name,
            'number' => $number !== '' ? $number : null,
            'salutation' => match ($contact['salutation'] ?? null) {
                ContactSalutation::Mr->value => 1,
                ContactSalutation::Ms->value => 2,
                default => null,
            },
            'title' => $contact['title'] ?? null,
            'first_name' => trim(($contact['first_name'] ?? '') . ' ' . ($contact['middle_names'] ?? '')) ?: null,
            'last_name' => $contact['last_name'] ?? null,
            'street' => (string) ($customer->address_street ?? '') ?: null,
            'zip_code' => (string) ($customer->address_zip ?? '') ?: null,
            'city' => (string) ($customer->address_city ?? '') ?: null,
            'vat_identifier' => ($fields['vat_id'] ?? '') !== '' ? $fields['vat_id'] : null,
            'emails' => ($fields['email'] ?? '') !== '' ? [$fields['email']] : null,
        ], static fn ($value): bool => $value !== null);

        $result = $existingId !== ''
            ? $client->updateCustomer($existingId, $payload)
            : $client->createCustomer($payload, $issues);

        $customerId = (string) ($result['id'] ?? $existingId);
        if ($customerId === '') {
            throw new RuntimeException('easybill customer push returned no id.');
        }
        app(ContactNumberAdopter::class)->linked($customer, self::ID, $customerId, (string) ($result['number'] ?? $number));

        return $customerId;
    }

    public function issuableNumberScopes(): array {
        return [NumberScope::Customer];
    }

    /**
     * Nummer, die nach easybill geht: keine Entwurfsnummer und keine, wenn
     * easybill selbst vergibt (MVP-1131).
     */
    private static function transferableNumber(Customer $customer): string {
        return (string) $customer->number_source === self::ID || NumberSequenceService::isDraft($customer->number)
            ? ''
            : trim((string) $customer->number);
    }

    /**
     * Kunde mit dieser Kundennummer in easybill. Gehört die Nummer dort einem
     * anderen Kunden, bricht die Übertragung ab, statt ihn zu überschreiben
     * oder ihm Rechnungen zuzuordnen (MVP-1133).
     *
     * @return array<string, mixed>|null
     */
    private function customerByNumber(EasybillClient $client, Customer $customer, string $number): ?array {
        foreach ($client->customersByNumber($number) as $row) {
            if (! is_array($row) || (string) ($row['number'] ?? '') !== $number || empty($row['id'] ?? null)) {
                continue;
            }
            app(ContactIdentity::class)->assertRemoteOwner($customer, $number, $this->name(), $row['company_name'] ?? null, $row['first_name'] ?? null, $row['last_name'] ?? null, $row['vat_identifier'] ?? null);

            return $row;
        }

        return null;
    }

    /** @return array<int, array{key: string, label: string, type: string, options?: array<string, string>, help?: string, required?: bool, default?: mixed}> */
    public function settingsSchema(): array {
        return [
            ['key' => 'api_key', 'label' => __('API-Key'), 'type' => 'password', 'required' => true, 'help' => __('easybill-API-Key (easybill → Einstellungen → API).')],
            ['key' => 'base_url', 'label' => __('API-Basis-URL'), 'type' => 'text', 'default' => 'https://api.easybill.de/rest/v1'],
            ['key' => 'default_vat_rate', 'label' => __('Standard-USt %'), 'type' => 'text', 'default' => '19'],
            ['key' => 'rate_limit_per_minute', 'label' => __('API-Limit (Requests/Minute)'), 'type' => 'text', 'default' => '10', 'help' => __('Tarifabhängig: PLUS 10, BUSINESS 60. Zu hohe Werte führen zu 429-Antworten.')],
            ['key' => 'einvoice_format', 'label' => __('E-Rechnungs-Format'), 'type' => 'select', 'default' => '', 'options' => [
                '' => __('Kein E-Rechnungs-Format (Standard-PDF)'),
                'xrechnung3_0_xml' => 'XRechnung 3.0 (XML)',
                'zugferd2_5_en16931' => 'ZUGFeRD 2.5 (EN 16931)',
            ], 'help' => __('file_format_config des easybill-Belegs; bestimmt das Format des Rückabrufs.')],
            ['key' => 'pull_documents', 'label' => __('Fertiggestellte Belege ins DMS zurückholen'), 'type' => 'boolean', 'default' => true],
        ];
    }

    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }

        if (! app(EasybillClientFactory::class)->isConfigured((int) $organization->id)) {
            return PluginHealth::degraded(__('easybill ist nicht konfiguriert (API-Key fehlt).'), 'not_configured');
        }

        try {
            $started = microtime(true);
            // Billigste dokumentierte Probe: eine Belegliste mit limit=1.
            app(EasybillClientFactory::class)
                ->for((int) $organization->id)
                ->documents(['limit' => 1]);

            return PluginHealth::ok(__('easybill-API erreichbar.'))
                ->withLatency((int) ((microtime(true) - $started) * 1000));
        } catch (EasybillApiException $e) {
            if ($e->isAuthError()) {
                return PluginHealth::failing(__('easybill lehnt den API-Key ab (401) — Key prüfen.'), 'auth');
            }
            if ($e->status === 429) {
                return PluginHealth::degraded(__('easybill drosselt (429) — Request-Limit im Setting prüfen.'), 'rate_limited');
            }

            return PluginHealth::degraded(__('easybill-API antwortet mit Fehlerstatus :status.', ['status' => $e->status]), 'api_error');
        } catch (Throwable) {
            return PluginHealth::failing(__('easybill-API nicht erreichbar.'), 'unreachable');
        }
    }
}
