<?php
/*
 * Created on   : Mon Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalCapability.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\CustomerPortal;

/**
 * Zentraler, typisierter Katalog der Kundenportal-Bereiche (MVP-511).
 * Navigation, Dashboard, Routen-Gates und Konfiguration entscheiden alle
 * über DIESEN Katalog — keine verteilten if-Abfragen in Views. Neue
 * Capabilities starten für Bestandskunden immer `deny`.
 */
enum PortalCapability: string {
    /** Aufträge/Fallakte (Auftragsbuch, Foto-Bestätigung). */
    case Diary = 'diary';

    /** Projektzeiten — Detailtiefe zusätzlich über {@see PortalTimeDetail}. */
    case TimeEntries = 'time_entries';

    /** Rechnungen und Abrechnungskonto (Feature 098). */
    case Invoices = 'invoices';

    /** Freigegebene Dokumente (Marker documents.customer_visible bleibt Gate). */
    case Documents = 'documents';

    /** Objektakte (eigene Objekte des Kunden). */
    case Assets = 'assets';

    /** Offene Punkte und bekannte Fehler (OpenIssueVisibility bleibt Gate). */
    case OpenIssues = 'open_issues';

    /** Tickets und Servicekatalog (Feature 065). */
    case Tickets = 'tickets';

    /** Reklamationen (Feature 072). */
    case Claims = 'claims';

    /** Verleihvorgänge (Feature 073). */
    case Rentals = 'rentals';

    /** Rückfragen/Kommentare (MVP-512) — erweitert nur freigegebene Bereiche. */
    case Queries = 'queries';

    public function label(): string {
        return (string) match ($this) {
            self::Diary => __('Aufträge & Fallakte'),
            self::TimeEntries => __('Projektzeiten'),
            self::Invoices => __('Rechnungen & Abrechnungskonto'),
            self::Documents => __('Dokumente'),
            self::Assets => __('Objekte'),
            self::OpenIssues => __('Offene Punkte & bekannte Fehler'),
            self::Tickets => __('Tickets & Servicekatalog'),
            self::Claims => __('Reklamationen'),
            self::Rentals => __('Verleihvorgänge'),
            self::Queries => __('Rückfragen & Kommentare'),
        };
    }

    /**
     * Lizenz-Voraussetzung: nur lizenzierte und tatsächlich verfügbare Module
     * können freigegeben werden. null = Kernfunktion ohne Modul-Gate.
     */
    public function moduleFlag(): ?string {
        return match ($this) {
            self::Tickets => 'module.helpdesk',
            self::Claims => 'module.claims',
            self::Rentals => 'module.rental',
            self::Documents => 'module.documents',
            default => null,
        };
    }
}
