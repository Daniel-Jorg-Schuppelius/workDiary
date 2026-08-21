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

use App\Models\Customer;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{ContactSyncer, Plugin};
use App\Plugins\Easybill\Api\{EasybillApiException, EasybillClientFactory};
use App\Services\Finance\Accounting\ContactPushService;
use RuntimeException;
use Throwable;

/**
 * easybill-Plugin (MVP-431, Phase 40): weiteres API-Faktura-Ziel neben
 * Lexoffice, orgaMAX und sevDesk.
 *
 * - Auth: API-Key als `Authorization: Bearer <key>` gegen
 *   https://api.easybill.de/rest/v1 — verschlüsselt je Organisation in
 *   plugin_settings (Auto-Form der Plugin-Karte).
 * - Faktura-Übergabe über {@see \App\Services\Finance\Targets\EasybillTarget}
 *   ({@see \App\Enums\Finance\TransferTarget::Easybill}): easybill führt die
 *   Rechnung — Übergabe als Entwurf, `/documents/{id}/done` wird nie gerufen.
 * - Rate-Limits sind tarifabhängig (PLUS 10, BUSINESS 60 req/min) — das
 *   Intervall kommt aus dem Setting `rate_limit_per_minute`.
 */
class EasybillPlugin extends AbstractPlugin implements ContactSyncer {
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

    /** @return array<int, \App\Plugins\Contracts\PluginCapability> Fähigkeiten hängen am FacturationTarget-Vertrag. */
    /**
     * Bewusst leer: Übergabe läuft über die
     * {@see \App\Services\Finance\Targets\FacturationTargetRegistry}, der
     * Beleg-Pull über {@see \App\Plugins\Easybill\Services\EasybillDocumentPullService}
     * — beides eigene Registries/Services statt Plugin-Interfaces (Audit 2026-08, W1.6).
     */
    public function capabilities(): array {
        return [];
    }

    /** @return array<int, array{key: string, label: string, type: string, options?: array<string, string>, help?: string, required?: bool, default?: mixed}> */
    /**
     * Kontakt-Push (Feature 122, MVP-611): anlegen oder aktualisieren, nie
     * löschen. Gefunden wird über die Kundennummer.
     */
    public function pushContact(Customer $customer): string {
        $client = app(EasybillClientFactory::class)->for((int) $customer->organization_id);
        $number = trim((string) $customer->number);

        $existingId = '';
        if ($number !== '') {
            foreach ($client->customersByNumber($number) as $row) {
                if (is_array($row) && (string) ($row['number'] ?? '') === $number && ! empty($row['id'] ?? null)) {
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
            'company_name' => (string) $customer->name,
            'number' => $number !== '' ? $number : null,
            'street' => (string) ($customer->address_street ?? '') ?: null,
            'zip_code' => (string) ($customer->address_zip ?? '') ?: null,
            'city' => (string) ($customer->address_city ?? '') ?: null,
            'vat_identifier' => ($fields['vat_id'] ?? '') !== '' ? $fields['vat_id'] : null,
            'emails' => ($fields['email'] ?? '') !== '' ? [$fields['email']] : null,
        ], static fn ($value): bool => $value !== null);

        $result = $existingId !== ''
            ? $client->updateCustomer($existingId, $payload)
            : $client->createCustomer($payload);

        $customerId = (string) ($result['id'] ?? $existingId);
        if ($customerId === '') {
            throw new RuntimeException('easybill customer push returned no id.');
        }

        return $customerId;
    }

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

        $config = EasybillConfig::resolve((int) $organization->id);
        if (empty($config['api_key'])) {
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
