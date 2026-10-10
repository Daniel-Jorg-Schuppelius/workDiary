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

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zentraler, typisierter Katalog der Kundenportal-Bereiche (MVP-511).
 * Navigation, Dashboard, Routen-Gates und Konfiguration entscheiden alle
 * über DIESEN Katalog — keine verteilten if-Abfragen in Views. Neue
 * Capabilities starten für Bestandskunden immer `deny`.
 */
enum PortalCapability: string implements HasLabel {
    use HasOptions;

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

    /** Offene Punkte (OpenIssueVisibility bleibt Gate). */
    case OpenIssues = 'open_issues';

    /** Tickets, Servicekatalog und bekannte Fehler (Feature 065). */
    case Tickets = 'tickets';

    /** Reklamationen (Feature 072). */
    case Claims = 'claims';

    /** Rücksendung anmelden (MVP-935): legt Reklamation und angekündigte RMA an. */
    case Returns = 'returns';

    /** Verleihvorgänge (Feature 073). */
    case Rentals = 'rentals';

    /** Rückfragen/Kommentare (MVP-512) — erweitert nur freigegebene Bereiche. */
    case Queries = 'queries';

    /** Online-Terminbuchung (Feature 087): Slots anfragen, nie direkt buchen. */
    case Appointments = 'appointments';

    /** Verleih-Anfrage (Feature 073, MVP-714): Zeitraum anfragen, nie direkt reservieren. */
    case RentalRequests = 'rental_requests';

    /** „Meine Abos" (Feature 152): Bestand der Abos des Kunden und seiner Endkunden — ohne Preise und Belege. */
    case Subscriptions = 'subscriptions';

    /** Kundenvereinbarungen (Feature 157): eigene AVV/NDA-Fassungen und freigegebene Abschlussnachweise. */
    case Agreements = 'agreements';

    /** Anfragen und Aufträge (Feature 162): Leistung anfragen, Dateien nachreichen, Angebot entscheiden. */
    case Intakes = 'intakes';

    public function label(): string {
        return (string) match ($this) {
            self::Diary => __('enums.customer_portal.portal_capability.diary'),
            self::TimeEntries => __('enums.customer_portal.portal_capability.time_entries'),
            self::Invoices => __('enums.customer_portal.portal_capability.invoices'),
            self::Documents => __('enums.customer_portal.portal_capability.documents'),
            self::Assets => __('enums.customer_portal.portal_capability.assets'),
            self::OpenIssues => __('enums.customer_portal.portal_capability.open_issues'),
            self::Tickets => __('enums.customer_portal.portal_capability.tickets'),
            self::Claims => __('enums.customer_portal.portal_capability.claims'),
            self::Returns => __('claims.portal_return.capability'),
            self::Rentals => __('enums.customer_portal.portal_capability.rentals'),
            self::Queries => __('enums.customer_portal.portal_capability.queries'),
            self::Appointments => __('enums.customer_portal.portal_capability.appointments'),
            self::RentalRequests => __('enums.customer_portal.portal_capability.rental_requests'),
            self::Subscriptions => __('enums.customer_portal.portal_capability.subscriptions'),
            self::Agreements => __('contract-signing.portal.capability'),
            self::Intakes => __('customer_intake.portal.capability'),
        };
    }

    /**
     * Lizenz-Voraussetzung: nur lizenzierte und tatsächlich verfügbare Module
     * können freigegeben werden. null = Kernfunktion ohne Modul-Gate.
     */
    public function moduleFlag(): ?string {
        return match ($this) {
            self::Tickets => 'module.helpdesk',
            self::Claims, self::Returns => 'module.claims',
            self::Rentals, self::RentalRequests => 'module.rental',
            self::Documents => 'module.documents',
            self::Appointments => 'module.planung',
            self::Subscriptions => 'module.reselling',
            self::Agreements => 'module.contracts',
            default => null,
        };
    }
}
