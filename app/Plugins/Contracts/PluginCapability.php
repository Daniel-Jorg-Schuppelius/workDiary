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

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Fähigkeiten, die ein Plugin über {@see Plugin::capabilities()} ankündigt.
 * Jede Fähigkeit ist an genau ein Contract-Interface gebunden ({@see interface()}):
 * Ein Plugin, das eine Fähigkeit ankündigt, MUSS das zugehörige Interface
 * implementieren — erzwungen von `plugin:doctor` und {@see \Tests\Unit\Architecture\PluginContractTest}.
 */
enum PluginCapability: string implements HasLabel {
    use HasOptions;

    /** Kann Kundenkontakte in ein externes System pushen. */
    case ContactSync = 'contact_sync';

    /** Kann erfasste Zeiten (je Kunde/Projekt/Zeitraum) übertragen. */
    case TimeExport = 'time_export';

    /** Kann Zeiten aus einem externen System importieren (z. B. Toggl, Fernwartung). */
    case TimeImport = 'time_import';

    /** Kann Zahlungs-/Abgleichdaten zurücklesen. */
    case PaymentSync = 'payment_sync';

    /** Kann Aufgaben mit einem externen Aufgabensystem abgleichen (Feature 055). */
    case TaskSync = 'task_sync';

    /** Kann Termine/Dienstpläne in einen externen Kalender publizieren (Feature 058, z. B. CalDAV). */
    case CalendarPublish = 'calendar_publish';

    /** Kann Versandlabels erzeugen/stornieren und Sendungen verfolgen (Feature 059, z. B. DHL). */
    case ShippingProvider = 'shipping_provider';

    /** Kann Dokumente aus überwachten Cloud-Ordnern lesend übernehmen (Feature 080). */
    case DocumentIntake = 'document_intake';

    /** Kann verschlüsselte Backup-Generationen in einen eigenen Cloud-Bereich schreiben (Feature 017, Phase 32). */
    case BackupTarget = 'backup_target';

    /** Kann Domains bei einem Registrar-/Reseller-Provider projizieren und kontrolliert verwalten (Feature 083). */
    case DomainRegistrar = 'domain_registrar';

    /** Kann extern gebuchte Termine empfangen und Buchungslinks erzeugen (Feature 095, z. B. Calendly). */
    case AppointmentSync = 'appointment_sync';

    /** Personenbeförderung (MVP-456): Taxameter-/Wegstreckenzähler-Import. */
    case FareMeter = 'fare_meter';

    /** Personenbeförderung (MVP-456): externe Fahrtvermittlung. */
    case PassengerDispatch = 'passenger_dispatch';

    /** Personenbeförderung (MVP-456): Mobilitätsdaten nach § 3a PBefG/MDV. */
    case MobilityData = 'mobility_data';

    /** Übersetztes UI-Label (Badge in der Plugin-Übersicht). */
    public function label(): string {
        return match ($this) {
            self::ContactSync => __('Kontaktsynchronisierung'),
            self::TimeExport => __('Zeit-Export'),
            self::TimeImport => __('Zeit-Import'),
            self::PaymentSync => __('Zahlungsabgleich'),
            self::TaskSync => __('Aufgaben-Sync'),
            self::CalendarPublish => __('Kalender-Veröffentlichung'),
            self::ShippingProvider => __('Versanddienstleister'),
            self::DocumentIntake => __('Dokumenteingang'),
            self::BackupTarget => __('Backupziel'),
            self::DomainRegistrar => __('Domain-Registrar'),
            self::AppointmentSync => __('Terminsynchronisation'),
            self::FareMeter => __('Taxameter-Import'),
            self::PassengerDispatch => __('Fahrtvermittlung'),
            self::MobilityData => __('Mobilitätsdaten'),
        };
    }

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
            self::TaskSync => TaskSyncer::class,
            self::CalendarPublish => CalendarPublisher::class,
            self::ShippingProvider => ShippingProvider::class,
            self::DocumentIntake => DocumentIntakeSource::class,
            self::BackupTarget => BackupTarget::class,
            self::DomainRegistrar => DomainRegistrar::class,
            self::AppointmentSync => AppointmentSyncer::class,
            self::FareMeter => FareMeterProvider::class,
            self::PassengerDispatch => PassengerDispatchProvider::class,
            self::MobilityData => MobilityDataPublisher::class,
        };
    }
}
