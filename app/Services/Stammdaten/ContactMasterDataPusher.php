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

    public function __construct(private readonly PluginManager $plugins) {}

    /**
     * @param  list<string>  $changedFields
     * @return bool true, wenn übertragen wurde
     */
    public function pushIfLinked(Customer|Supplier $contact, array $changedFields): bool {
        if (array_intersect($changedFields, self::PUSHED_FIELDS) === []) {
            return false;
        }

        $plugin = $this->plugins->get(LexofficePlugin::ID);
        if (!$plugin instanceof LexofficePlugin || !$this->isLinked($contact)) {
            return false;
        }

        try {
            $contact instanceof Supplier
                ? $plugin->pushSupplierContact($contact)
                : $plugin->pushContact($contact);

            return true;
        } catch (Throwable $e) {
            // Der lokale Stand bleibt gültig; die Übertragung holt der nächste
            // Abgleich nach. Nur protokollieren, nicht den Request kippen.
            report($e);

            return false;
        }
    }

    private function isLinked(Customer|Supplier $contact): bool {
        return \App\Models\ExternalReference::query()
            ->forPlugin($contact->organization_id, LexofficePlugin::ID, LexofficePlugin::EXT_TYPE_CONTACT)
            ->forReferenceable($contact)
            ->exists();
    }
}
