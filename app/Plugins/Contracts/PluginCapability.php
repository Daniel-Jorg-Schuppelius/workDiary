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
enum PluginCapability: string implements HasLabel, PluginCapabilityContract {
    use HasOptions;

    /** Kann Kundenkontakte in ein externes System pushen. */
    case ContactSync = 'contact_sync';

    /** Kann erfasste Zeiten (je Kunde/Projekt/Zeitraum) übertragen. */
    case TimeExport = 'time_export';

    /** Kann Zeiten aus einem externen System importieren (z. B. Toggl, Fernwartung). */
    case TimeImport = 'time_import';

    /**
     * Kann Zahlungs-/Abgleichdaten zurücklesen (Zahldatum/Status aus dem
     * Fremdsystem). Deklariert von Lexoffice; SevDesk/Easybill/orgaMAX lesen
     * bewusst KEINEN Zahlungsstatus zurück (dort ist die Marker-Reconciliation
     * reine Übergabe-Idempotenz).
     */
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

    /** Kann Kurznachrichten (SMS) über ein Gateway versenden (Feature 147, z. B. seven.io, sipgate). */
    case SmsGateway = 'sms_gateway';

    /** Kann Rechnungen online kassieren: Bezahlseite und Zahlungsstand (MVP-1067, z. B. Stripe, Mollie, SumUp). */
    case OnlinePayment = 'online_payment';

    /**
     * Kann Belege über einen zertifizierten Peppol-Access-Point-Provider
     * senden und empfangen (Feature 066, MVP-734). WorkDiary betreibt selbst
     * keinen Access Point — das Plugin ist reine Provider-Anbindung.
     */
    case PeppolTransport = 'peppol_transport';

    /**
     * Personenbeförderung (MVP-456): Taxameter-/Wegstreckenzähler-Import.
     * RESERVIERT — noch kein Plugin deklariert sie (Audit 2026-08, Welle 1.5:
     * geprüft und bewusst so belassen, bis ein Anbieter angebunden wird).
     */
    case FareMeter = 'fare_meter';

    /** Personenbeförderung (MVP-456): externe Fahrtvermittlung. RESERVIERT (siehe {@see self::FareMeter}). */
    case PassengerDispatch = 'passenger_dispatch';

    /** Personenbeförderung (MVP-456): Mobilitätsdaten nach § 3a PBefG/MDV. RESERVIERT (siehe {@see self::FareMeter}). */
    case MobilityData = 'mobility_data';

    /** Stabiler Maschinen-Identifier ({@see PluginCapabilityContract}). */
    public function identifier(): string {
        return $this->value;
    }

    /** Übersetztes UI-Label (Badge in der Plugin-Übersicht). */
    public function label(): string {
        return match ($this) {
            self::ContactSync => __('enums.plugin.plugin_capability.contact_sync'),
            self::TimeExport => __('enums.plugin.plugin_capability.time_export'),
            self::TimeImport => __('enums.plugin.plugin_capability.time_import'),
            self::PaymentSync => __('enums.plugin.plugin_capability.payment_sync'),
            self::TaskSync => __('enums.plugin.plugin_capability.task_sync'),
            self::CalendarPublish => __('enums.plugin.plugin_capability.calendar_publish'),
            self::ShippingProvider => __('enums.plugin.plugin_capability.shipping_provider'),
            self::DocumentIntake => __('enums.plugin.plugin_capability.document_intake'),
            self::BackupTarget => __('enums.plugin.plugin_capability.backup_target'),
            self::DomainRegistrar => __('enums.plugin.plugin_capability.domain_registrar'),
            self::AppointmentSync => __('enums.plugin.plugin_capability.appointment_sync'),
            self::SmsGateway => __('enums.plugin.plugin_capability.sms_gateway'),
            self::OnlinePayment => __('enums.plugin.plugin_capability.online_payment'),
            self::PeppolTransport => __('enums.plugin.plugin_capability.peppol_transport'),
            self::FareMeter => __('enums.plugin.plugin_capability.fare_meter'),
            self::PassengerDispatch => __('enums.plugin.plugin_capability.passenger_dispatch'),
            self::MobilityData => __('enums.plugin.plugin_capability.mobility_data'),
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
            self::SmsGateway => SmsProvider::class,
            self::OnlinePayment => OnlinePaymentProvider::class,
            self::PeppolTransport => PeppolTransportProvider::class,
            self::FareMeter => FareMeterProvider::class,
            self::PassengerDispatch => PassengerDispatchProvider::class,
            self::MobilityData => MobilityDataPublisher::class,
        };
    }
}
