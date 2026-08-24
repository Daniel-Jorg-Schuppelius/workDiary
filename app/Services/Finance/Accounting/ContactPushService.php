<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactPushService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Accounting;

use App\Models\{Customer, ExternalReference, Organization};
use App\Plugins\Contracts\ContactSyncer;
use App\Plugins\PluginManager;
use App\Support\Setting;
use RuntimeException;
use Throwable;

/**
 * Kontakt-Push in die Buchhaltung (Feature 122, MVP-611).
 *
 * Ein Lauf, keine Observer: Ein Observer am Kunden würde jeden Tippfehler
 * sofort zu einem API-Aufruf machen. Einzelne Kunden lassen sich zusätzlich
 * gezielt übertragen.
 *
 * Zwei Leitplanken:
 * 1. **Führungsrichtung.** Führt die Buchhaltung die Stammdaten, wird nicht
 *    gepusht — sonst überschreiben sich zwei Systeme gegenseitig.
 * 2. **Eigene Stammdaten.** USt-IdNr. und E-Mail der eigenen Organisation
 *    werden nie an einem Fremdkontakt mitgeschickt. Genau dieser Müll ist im
 *    Lexoffice-Betrieb aufgetreten und hat später Fehl-Matches erzeugt.
 */
class ContactPushService {
    public const AUTHORITY_KEY = 'finance.master_data_authority';

    public const AUTHORITY_WORKDIARY = 'workdiary';

    public const AUTHORITY_ACCOUNTING = 'accounting';

    public function __construct(private readonly PluginManager $plugins) {}

    /** Führt workDiary die Stammdaten? Nur dann darf gepusht werden. */
    public function pushAllowed(): bool {
        return (string) Setting::get(self::AUTHORITY_KEY, self::AUTHORITY_WORKDIARY) !== self::AUTHORITY_ACCOUNTING;
    }

    /**
     * Alle nicht archivierten Kunden an ein Buchhaltungs-Plugin übertragen.
     *
     * @return array{pushed: int, skipped: int, failed: int}
     */
    public function pushAll(Organization $organization, string $pluginId): array {
        $counters = ['pushed' => 0, 'skipped' => 0, 'failed' => 0];
        if (! $this->pushAllowed()) {
            return $counters;
        }

        $syncer = $this->syncer($pluginId);
        if ($syncer === null) {
            return $counters;
        }

        $customers = Customer::query()
            ->where('organization_id', $organization->id)
            ->whereNull('archived_at')
            ->orderBy('id')
            ->get();

        foreach ($customers as $customer) {
            try {
                $this->push($customer, $pluginId);
                $counters['pushed']++;
            } catch (Throwable) {
                // Ein einzelner Kontakt darf den Lauf nicht abbrechen; der
                // nächste Lauf versucht ihn erneut.
                $counters['failed']++;
            }
        }

        return $counters;
    }

    /** Einzelnen Kunden übertragen; gibt die externe ID zurück. */
    public function push(Customer $customer, string $pluginId): string {
        if (! $this->pushAllowed()) {
            throw new RuntimeException((string) __('accounting.error.accounting_leads'));
        }

        $syncer = $this->syncer($pluginId);
        if ($syncer === null) {
            throw new RuntimeException((string) __('accounting.error.no_syncer', ['plugin' => $pluginId]));
        }

        $externalId = $syncer->pushContact($customer);

        ExternalReference::query()->updateOrCreate(
            [
                'plugin_id' => $pluginId,
                'external_type' => 'contact',
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
     * Felder, die nie an einen Fremdkontakt gehören: die eigene USt-IdNr. und
     * die eigene E-Mail-Adresse. Ein Kunde, der sie trägt, trägt sie
     * versehentlich — mitschicken würde den Fehler in die Buchhaltung tragen.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public function withoutOwnIdentity(array $fields): array {
        $ownVat = $this->normalize((string) Setting::get('einvoice.vat_id', ''));
        $ownMail = $this->normalize((string) Setting::get('einvoice.contact_email', ''));

        if ($ownVat !== '' && $this->normalize((string) ($fields['vat_id'] ?? '')) === $ownVat) {
            unset($fields['vat_id']);
        }
        if ($ownMail !== '' && $this->normalize((string) ($fields['email'] ?? '')) === $ownMail) {
            unset($fields['email']);
        }

        return $fields;
    }

    private function normalize(string $value): string {
        return strtolower(str_replace([' ', '-', '.'], '', trim($value)));
    }

    private function syncer(string $pluginId): ?ContactSyncer {
        $plugin = $this->plugins->get($pluginId);

        return $plugin instanceof ContactSyncer ? $plugin : null;
    }

    /**
     * Einzelnen Lieferanten übertragen (B6): gleiche Leitplanken wie
     * {@see push()} — Führungsrichtung, Referenz-Nachweis mit synced_at.
     */
    public function pushSupplier(\App\Models\Supplier $supplier, string $pluginId): string {
        if (! $this->pushAllowed()) {
            throw new RuntimeException((string) __('accounting.error.accounting_leads'));
        }

        $plugin = $this->plugins->get($pluginId);
        if (! $plugin instanceof \App\Plugins\Contracts\SupplierContactSyncer) {
            throw new RuntimeException((string) __('accounting.error.no_syncer', ['plugin' => $pluginId]));
        }

        $externalId = $plugin->pushSupplierContact($supplier);

        ExternalReference::query()->updateOrCreate(
            [
                'plugin_id' => $pluginId,
                'external_type' => 'contact',
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
}
