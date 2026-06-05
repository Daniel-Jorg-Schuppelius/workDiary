<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficePlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice;

use App\Models\{Customer, ExternalReference, Supplier, TimeEntry};
use App\Plugins\Contracts\{ContactSyncer, Plugin, PluginCapability, TimeExporter};
use App\Plugins\{PluginDefaults, PluginHealth};
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Throwable;

/**
 * Lexoffice integration plugin.
 *
 * - Pushes workDiary customers as Lexoffice contacts (ContactSyncer)
 * - Aggregates billable time per customer + period and creates a Lexoffice
 *   sales invoice voucher representing the service transaction (TimeExporter)
 *
 * Mappings between local entities and Lexoffice ids are persisted in the
 * external_references table. The plugin id is "lexoffice".
 */
class LexofficePlugin implements ContactSyncer, Plugin, TimeExporter {
    use PluginDefaults;

    public const ID = 'lexoffice';

    public const SERVICE_PROVIDER = LexofficeServiceProvider::class;

    public const EXT_TYPE_CONTACT = 'contact';

    public const EXT_TYPE_VOUCHER = 'voucher';

    public function __construct(
        private readonly LexofficeService $service,
    ) {}

    public function id(): string {
        return self::ID;
    }

    public function name(): string {
        return 'Lexoffice';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function schemaVersion(): string {
        // Aktuell liefert das Lexoffice-Plugin keine eigenen Migrations.
        // Wird beim ersten Plugin-eigenen Schema-Wechsel hochgezogen.
        return '1.0.0';
    }

    /**
     * Kurzer Ping gegen den Lexoffice-/profile-Endpunkt. Antwortet die API,
     * gilt das Plugin als gesund; 401 → failing (Key ungültig).
     *
     * Transiente Zustände führen bewusst zu `degraded` (nicht `failing`):
     * `degraded` zählt NICHT auf den Auto-Disable-Zähler ein, damit ein
     * vorübergehendes Rate-Limit (429) oder ein Netz-Hänger das Plugin nicht
     * dauerhaft stilllegt:
     *   - 429 (Rate-Limit) → degraded
     *   - Netz-/Timeout-Fehler → degraded
     *   - sonstige Fehler → failing mit Message
     */
    public function healthCheck(): PluginHealth {
        if (! $this->service->isConfigured()) {
            return PluginHealth::degraded(__('Lexoffice ist nicht konfiguriert.'));
        }
        try {
            $status = $this->service->profileStatus();

            if ($status !== null && $status >= 200 && $status < 300) {
                return PluginHealth::ok(__('Lexoffice-API erreichbar.'));
            }
            if ($status === 401) {
                return PluginHealth::failing(__('Lexoffice lehnt den API-Schlüssel ab (401). Bitte prüfe, ob der hinterlegte Schlüssel gültig ist.'));
            }
            if ($status !== null && $status >= 500) {
                // Serverseitige 5xx sind transient → degraded (kein Auto-Disable).
                return PluginHealth::degraded(__('Lexoffice ist momentan nicht erreichbar (HTTP :status).', ['status' => $status]));
            }

            return PluginHealth::failing(__('Lexoffice-API antwortet nicht erwartungsgemäß (HTTP :status).', ['status' => $status ?? '—']));
        } catch (LexofficeRateLimitException $e) {
            return PluginHealth::degraded($e->getMessage());
        } catch (ConnectionException $e) {
            return PluginHealth::degraded(__('Lexoffice ist momentan nicht erreichbar (Netzwerk-/Timeout-Fehler).'));
        } catch (Throwable $e) {
            return PluginHealth::failing($e->getMessage());
        }
    }

    public function description(): string {
        return __('Synchronisiert Kunden mit Lexoffice und überträgt erfasste Zeiten als Beleg.');
    }

    public function isEnabled(): bool {
        // Wenn eine Organisation gebunden ist, entscheidet die DB; sonst Fallback auf config (Tests + Konsolen-Kontexte ohne Org).
        if (app()->bound('currentOrganization')) {
            $org = app('currentOrganization');
            if ($org instanceof \App\Models\Organization) {
                $row = \App\Models\PluginSetting::forOrganization($org->id, self::ID);
                if ($row->exists || ($row->enabled || ($row->settings['api_key'] ?? null) !== null)) {
                    return $row->enabled && (string) ($row->get('api_key') ?? '') !== '';
                }
            }
        }

        return (bool) config('plugins.lexoffice.enabled') && $this->service->isConfigured();
    }

    public function capabilities(): array {
        return [
            PluginCapability::CONTACT_SYNC,
            PluginCapability::TIME_EXPORT,
        ];
    }

    public function adminPanel(): ?array {
        return [
            'route' => 'admin.plugins.edit',
            'label' => __('Lexoffice-Einstellungen'),
            'icon' => 'cloud_sync',
        ];
    }

    public function serviceProvider(): ?string {
        return \App\Plugins\Lexoffice\LexofficeServiceProvider::class;
    }

    public function settingsSchema(): array {
        return [
            ['key' => 'api_key', 'label' => __('API-Key'), 'type' => 'password', 'required' => true, 'help' => __('Public API-Token aus dem Lexoffice-Account.')],
            ['key' => 'base_url', 'label' => __('API-Basis-URL'), 'type' => 'text', 'default' => 'https://api.lexoffice.io/v1'],
            ['key' => 'default_currency', 'label' => __('Standardwährung'), 'type' => 'text', 'default' => 'EUR'],
            ['key' => 'default_tax_type', 'label' => __('Steuerart'), 'type' => 'select', 'options' => ['net' => 'net', 'gross' => 'gross'], 'default' => 'net'],
            ['key' => 'default_vat_rate', 'label' => __('Standard-USt %'), 'type' => 'text', 'default' => '19'],
            ['key' => 'match_policy', 'label' => __('Konflikt-Strategie'), 'type' => 'select', 'options' => [
                'lexoffice_wins' => __('Lexoffice gewinnt'),
                'local_wins' => __('Lokal gewinnt'),
                'manual_review' => __('Manuelle Prüfung'),
            ], 'default' => 'manual_review'],
            ['key' => 'create_missing_local', 'label' => __('Fehlende Kunden aus Lexoffice neu anlegen'), 'type' => 'boolean', 'default' => false],
            ['key' => 'number_authority', 'label' => __('Nummernkreise von Lexoffice führen lassen (Kunde, Lieferant, Rechnung, Gutschrift)'), 'type' => 'boolean', 'default' => false],
        ];
    }

    public function pushContact(Customer $customer): string {
        $externalId = $this->service->createContact($customer);

        ExternalReference::updateOrCreate(
            [
                'plugin_id' => self::ID,
                'external_type' => self::EXT_TYPE_CONTACT,
                'referenceable_type' => $customer->getMorphClass(),
                'referenceable_id' => $customer->getKey(),
            ],
            [
                'organization_id' => $customer->organization_id,
                'external_id' => $externalId,
                'synced_at' => now(),
            ],
        );

        return $externalId;
    }

    /**
     * Pusht einen Lieferanten als Lexoffice-Kontakt (role=vendor) und legt
     * die ExternalReference an. Gegenstück zu {@see pushContact()} für
     * {@see Supplier}, ohne den ContactSyncer-Vertrag zu erweitern.
     */
    public function pushSupplierContact(Supplier $supplier): string {
        $externalId = $this->service->createContact($supplier);

        ExternalReference::updateOrCreate(
            [
                'plugin_id' => self::ID,
                'external_type' => self::EXT_TYPE_CONTACT,
                'referenceable_type' => $supplier->getMorphClass(),
                'referenceable_id' => $supplier->getKey(),
            ],
            [
                'organization_id' => $supplier->organization_id,
                'external_id' => $externalId,
                'synced_at' => now(),
            ],
        );

        return $externalId;
    }

    /**
     * View-Slot-Renderer. Wird vom Core über {@see \App\Plugins\PluginManager::renderSlot()}
     * aufgerufen; das Plugin entscheidet selbst, ob und welcher Button erscheinen soll.
     */
    public function renderActions(string $slot, mixed $context = null): ?string {
        if (! $this->isEnabled()) {
            return null;
        }

        if ($slot === 'invoice-show.actions' && $context instanceof \App\Models\Invoice && $context->status === \App\Models\Invoice::STATUS_DRAFT) {
            $url = route('invoices.lexoffice.publish', $context);
            $csrf = csrf_token();
            $label = __('An Lexoffice');
            $confirm = __('Rechnung an Lexoffice übertragen und dort finalisieren? Die Rechnungsnummer wird ggf. von Lexoffice gesetzt.');

            return <<<HTML
                <form method="POST" action="{$url}" class="inline"
                      data-confirm-dialog
                      data-confirm-message="{$confirm}"
                      data-confirm-icon="cloud_upload"
                      data-confirm-tone="primary"
                      data-confirm-label="{$label}">
                    <input type="hidden" name="_token" value="{$csrf}">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <span class="material-symbols-outlined" aria-hidden="true">cloud_upload</span>
                        <span>{$label}</span>
                    </button>
                </form>
            HTML;
        }

        return null;
    }

    public function exportCustomerTime(Customer $customer, CarbonImmutable $from, CarbonImmutable $to): array {
        $entries = TimeEntry::query()
            ->whereHas('project', fn($q) => $q->where('customer_id', $customer->id))
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where('billable', true)
            ->where('exported', false)
            ->with('project')
            ->get();

        if ($entries->isEmpty()) {
            return [
                'external_id' => '',
                'external_type' => self::EXT_TYPE_VOUCHER,
                'payload' => ['skipped' => true, 'reason' => 'no billable entries'],
            ];
        }

        $contactRef = ExternalReference::query()
            ->where('plugin_id', self::ID)
            ->where('external_type', self::EXT_TYPE_CONTACT)
            ->where('referenceable_type', $customer->getMorphClass())
            ->where('referenceable_id', $customer->getKey())
            ->first();

        $contactExternalId = $contactRef !== null ? $contactRef->external_id : $this->pushContact($customer);

        $result = $this->service->createTimeVoucher($customer, $entries, $from, $to, $contactExternalId);

        $voucherRef = ExternalReference::create([
            'organization_id' => $customer->organization_id,
            'plugin_id' => self::ID,
            'external_type' => self::EXT_TYPE_VOUCHER,
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->getKey(),
            'external_id' => $result['external_id'],
            'payload' => $result['payload'],
            'synced_at' => now(),
        ]);

        // Mark exported so the same entries are not transmitted twice.
        TimeEntry::query()
            ->whereIn('id', $entries->pluck('id'))
            ->update(['exported' => true]);

        return [
            'external_id' => $voucherRef->external_id,
            'external_type' => self::EXT_TYPE_VOUCHER,
            'payload' => $result['payload'],
        ];
    }
}
