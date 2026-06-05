<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginCapability.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Contracts;

/**
 * Fähigkeiten, die ein Plugin über {@see Plugin::capabilities()} ankündigt.
 * Jede Fähigkeit ist an genau ein Contract-Interface gebunden ({@see interface()}):
 * Ein Plugin, das eine Fähigkeit ankündigt, MUSS das zugehörige Interface
 * implementieren — erzwungen von `plugin:doctor` und {@see \Tests\Unit\Architecture\PluginContractTest}.
 */
enum PluginCapability: string {
    /** Kann Kundenkontakte in ein externes System pushen. */
    case ContactSync = 'contact_sync';

    /** Kann erfasste Zeiten (je Kunde/Projekt/Zeitraum) übertragen. */
    case TimeExport = 'time_export';

    /** Kann Zeiten aus einem externen System importieren (z. B. Toggl, Fernwartung). */
    case TimeImport = 'time_import';

    /** Kann Zahlungs-/Abgleichdaten zurücklesen. */
    case PaymentSync = 'payment_sync';

    /**
     * Das Contract-Interface, das ein Plugin mit dieser Fähigkeit implementieren muss.
     *
     * @return class-string
     */
    public function interface(): string {
        return match ($this) {
            self::ContactSync => ContactSyncer::class,
            self::TimeExport => TimeExporter::class,
            self::TimeImport => TimeImporter::class,
            self::PaymentSync => PaymentSyncer::class,
        };
    }
}
