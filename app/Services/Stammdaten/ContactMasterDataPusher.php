<?php
/*
 * Created on   : Mon Jul 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactMasterDataPusher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Stammdaten;

use App\Models\{Customer, Supplier};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\PluginManager;
use Throwable;

/**
 * Überträgt korrigierte Stammdaten an das führende Fremdsystem.
 *
 * Hintergrund: Fehlerhafte USt-IdNrn. und Steuernummern stammen aus Lexoffice.
 * Wer sie hier korrigiert, muss sie nicht ein zweites Mal dort korrigieren —
 * sonst holt der nächste Abgleich den alten Wert zurück.
 *
 * Bewusst eng gefasst: nur bei geänderten Stammdatenfeldern, nur für bereits
 * verknüpfte Kontakte, und ein Fehler beim Übertragen darf das Speichern nicht
 * scheitern lassen (der lokale Stand ist dann trotzdem korrekt).
 */
class ContactMasterDataPusher {
    /** Felder, deren Änderung eine Übertragung rechtfertigt. */
    private const PUSHED_FIELDS = [
        'name', 'company', 'vat_id', 'tax_number', 'email', 'phone', 'mobile',
        'address_street', 'address_zip', 'address_city', 'country',
    ];

    public function __construct(
        private readonly PluginManager $plugins,
        private readonly \App\Services\Finance\Accounting\ContactPushService $pushService,
    ) {}

    /**
     * @param  list<string>  $changedFields
     * @return bool true, wenn übertragen wurde
     */
    public function pushIfLinked(Customer|Supplier $contact, array $changedFields): bool {
        if (array_intersect($changedFields, self::PUSHED_FIELDS) === []) {
            return false;
        }

        // Führungsrichtung (Vollscan 2026-08-23, B6): führt die Buchhaltung
        // die Stammdaten, wird NICHT gepusht — vorher fehlte das Gate hier.
        if (! $this->pushService->pushAllowed()) {
            return false;
        }

        $pushed = false;
        foreach ($this->linkedPluginIds($contact) as $pluginId) {
            $plugin = $this->plugins->get($pluginId);
            try {
                if ($contact instanceof Supplier) {
                    if ($plugin instanceof \App\Plugins\Contracts\SupplierContactSyncer) {
                        $this->pushService->pushSupplier($contact, $pluginId);
                        $pushed = true;
                    }
                } elseif ($plugin instanceof \App\Plugins\Contracts\ContactSyncer) {
                    $this->pushService->push($contact, $pluginId);
                    $pushed = true;
                }
            } catch (Throwable $e) {
                // Der lokale Stand bleibt gültig; die Übertragung holt der
                // nächste Abgleich nach. Nur protokollieren, nicht kippen.
                report($e);
            }
        }

        return $pushed;
    }

    /**
     * Plugin-IDs mit bestehender Kontakt-Referenz (B6: nicht mehr hart
     * Lexoffice — jedes verknüpfte Buchhaltungs-Plugin mit Syncer zieht mit).
     *
     * @return list<string>
     */
    private function linkedPluginIds(Customer|Supplier $contact): array {
        return array_values(array_unique(array_map(
            strval(...),
            \App\Models\ExternalReference::query()
                ->where('organization_id', $contact->organization_id)
                ->where('external_type', LexofficePlugin::EXT_TYPE_CONTACT)
                ->forReferenceable($contact)
                ->pluck('plugin_id')
                ->all(),
        )));
    }
}
