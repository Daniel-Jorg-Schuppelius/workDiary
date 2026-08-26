<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportEntity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Import;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Vom MVP-049 CSV-Import unterstützte Entitäten.
 */
enum ImportEntity: string implements HasLabel {
    use HasOptions;

    case Customers = 'customers';
    case Suppliers = 'suppliers';
    case Articles = 'articles';
    case Projects = 'projects';
    case Users = 'users';
    case Materials = 'materials';
    case Vehicles = 'vehicles';
    case ScheduledShifts = 'scheduled_shifts';
    case RemoteSessions = 'remote_sessions';
    // MVP-438: Zeiterfassungs-Import (CSV/XLSX/iCal) über die MVP-049-Engine.
    case Attendances = 'attendances';
    case ProjectTimes = 'project_times';
    // MVP-707 (Vollscan H20): Altsystem-Übernahme — Rechnungen mit OP-Stand,
    // Angebote, Assets, Ansprechpartner (JSON am Kunden/Lieferanten), Dokument-ZIP.
    case Invoices = 'invoices';
    case Quotes = 'quotes';
    case Assets = 'assets';
    case ContactPersons = 'contact_persons';
    case Documents = 'documents';

    public function label(): string {
        return (string) __('import.entity.' . $this->value);
    }

    public function permission(): string {
        return match ($this) {
            self::Customers => 'customer.import',
            self::Suppliers => 'supplier.import',
            self::Articles => 'article.import',
            self::Projects => 'project.import',
            self::Users => 'user.import',
            self::Materials => 'material.import',
            self::Vehicles => 'vehicle.import',
            self::ScheduledShifts => 'schedule.import',
            self::RemoteSessions => 'remote-session.import',
            // MVP-438: Stempelungen streng (Admin/HR), Projektzeiten breiter vergebbar.
            self::Attendances => 'attendance.import',
            self::ProjectTimes => 'project-time.import',
            // MVP-707: keine neuen Rechte — Altrechnungen/Angebote wie Rechnungs-
            // anlage, Assets/Dokumente wie deren Anlage, Ansprechpartner wie Kunden-Import.
            self::Invoices, self::Quotes => 'invoice.create',
            self::Assets => 'asset.create',
            self::ContactPersons => 'customer.import',
            self::Documents => 'document.create',
        };
    }

    /**
     * Zielmodell der Entität (null = kein 1:1-Zielmodell, z. B.
     * Fernwartungs-Sitzungen, die Zeiteinträge erzeugen).
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>|null
     */
    public function modelClass(): ?string {
        return match ($this) {
            self::Customers => \App\Models\Customer::class,
            self::Suppliers => \App\Models\Supplier::class,
            self::Articles => \App\Models\Article::class,
            self::Projects => \App\Models\Project::class,
            self::Users => \App\Models\User::class,
            self::Materials => \App\Models\Material::class,
            self::Vehicles => \App\Models\Vehicle::class,
            self::ScheduledShifts => \App\Models\ScheduledShift::class,
            self::RemoteSessions => null,
            self::Attendances => \App\Models\Attendance::class,
            self::ProjectTimes => \App\Models\TimeEntry::class,
            self::Invoices => \App\Models\Invoice::class,
            self::Quotes => \App\Models\Quote::class,
            self::Assets => \App\Models\Asset::class,
            // Ansprechpartner leben als JSON-Liste am Kunden/Lieferanten (kein eigenes Modell).
            self::ContactPersons => null,
            self::Documents => \App\Models\Document::class,
        };
    }

    /**
     * A13 (MVP-049): Klassifikations-Ziele im Wert-Mapping nur für Entitäten,
     * deren Zielmodell Klassifikationen trägt ({@see \App\Models\Concerns\HasClassifications}).
     */
    /**
     * MVP-707: Dokumente kommen als ZIP (manifest.csv + Dateien) statt CSV/XLSX.
     */
    public function acceptsZip(): bool {
        return $this === self::Documents;
    }

    public function supportsClassifications(): bool {
        $model = $this->modelClass();

        return $model !== null
            && in_array(\App\Models\Concerns\HasClassifications::class, class_uses_recursive($model), true);
    }
}
