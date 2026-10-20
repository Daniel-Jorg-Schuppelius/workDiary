<?php
/*
 * Created on   : Tue Oct 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalNavigation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\CustomerPortal;

use App\Enums\CustomerPortal\{PortalCapability, PortalTimeDetail};
use App\Models\Customer\Customer;

/**
 * Die Bereiche des Kundenportals als EINE Liste (Phase 154, E5): Kopfzeile,
 * Drawer und die Bereichskacheln der Startseite lesen dieselben Einträge in
 * derselben Reihenfolge. Jeder Eintrag hängt an seiner Bereichsfreigabe
 * ({@see PortalVisibility}, Default-Deny) und gegebenenfalls einer weiteren
 * Bedingung (Zeiten: Detailstufe, Abrechnungskonto: Kontoführung).
 *
 * @phpstan-type NavItem array{key: string, label: string, description: string, icon: string, route: string, routeIs: list<string>, always: bool}
 */
final class PortalNavigation {
    public function __construct(private readonly PortalVisibility $visibility) {}

    /**
     * @return list<NavItem>
     */
    public function items(Customer $customer): array {
        $items = [];
        $this->add($items, $customer, 'dashboard', null, (string) __('Übersicht'), 'home', 'customer.dashboard', ['customer.dashboard']);
        $this->add($items, $customer, 'search', null, (string) __('customer_search.nav'), 'search', 'customer.search', ['customer.search']);
        $this->add($items, $customer, 'diary', PortalCapability::Diary, (string) __('Auftragsbuch'), 'menu_book', 'customer.diary.index', ['customer.diary.*']);
        $this->add($items, $customer, 'assets', PortalCapability::Assets, (string) __('Objekte'), 'devices_other', 'customer.assets.index', ['customer.assets.*']);
        $this->add($items, $customer, 'documents', PortalCapability::Documents, (string) __('document.customer.portal.title'), 'folder_open', 'customer.documents.index', ['customer.documents.*']);
        $this->add($items, $customer, 'time', PortalCapability::TimeEntries, (string) __('Zeiten'), 'schedule', 'customer.time-entries.index', ['customer.time-entries.*'],
            $this->visibility->timeDetail($customer) !== PortalTimeDetail::None);
        $this->add($items, $customer, 'invoices', PortalCapability::Invoices, (string) __('Rechnungen'), 'receipt_long', 'customer.invoices.index', ['customer.invoices.*']);
        // Abrechnungskonto (Feature 098): nur bei aktivem Konto-Modus-Profil.
        $this->add($items, $customer, 'billing', PortalCapability::Invoices, (string) __('customer-billing.portal_title'), 'account_balance_wallet', 'customer.billing.index', ['customer.billing.*'],
            $customer->billingAgreement?->keepsLedger() === true);
        $this->add($items, $customer, 'insights', PortalCapability::Insights, (string) __('customer_portal.insights.title'), 'insights', 'customer.insights.index', ['customer.insights.*']);
        $this->add($items, $customer, 'open_issues', PortalCapability::OpenIssues, (string) __('open-issue.title.index'), 'flag', 'customer.open-issues.index', ['customer.open-issues.*']);
        $this->add($items, $customer, 'tickets', PortalCapability::Tickets, (string) __('Tickets'), 'confirmation_number', 'customer.tickets.index', ['customer.tickets.*']);
        $this->add($items, $customer, 'catalog', PortalCapability::Tickets, (string) __('Servicekatalog'), 'storefront', 'customer.catalog.index', ['customer.catalog.*']);
        $this->add($items, $customer, 'known_errors', PortalCapability::Tickets, (string) __('Bekannte Fehler'), 'bug_report', 'customer.known-errors.index', ['customer.known-errors.*']);
        $this->add($items, $customer, 'claims', PortalCapability::Claims, (string) __('Reklamationen'), 'report_problem', 'customer.claims.index', ['customer.claims.*']);
        $this->add($items, $customer, 'returns', PortalCapability::Returns, (string) __('claims.portal_return.nav'), 'assignment_return', 'customer.returns.create', ['customer.returns.*']);
        $this->add($items, $customer, 'rentals', PortalCapability::Rentals, (string) __('Verleih'), 'handyman', 'customer.rentals.index', ['customer.rentals.index', 'customer.rentals.show', 'customer.rentals.confirm']);
        $this->add($items, $customer, 'rental_requests', PortalCapability::RentalRequests, (string) __('Verleih-Anfrage'), 'event_available', 'customer.rentals.requests.index', ['customer.rentals.requests.*']);
        $this->add($items, $customer, 'subscriptions', PortalCapability::Subscriptions, (string) __('resale_portal.menu'), 'workspace_premium', 'customer.subscriptions.index', ['customer.subscriptions.*']);
        $this->add($items, $customer, 'agreements', PortalCapability::Agreements, (string) __('contract-signing.portal.menu'), 'history_edu', 'customer.agreements.index', ['customer.agreements.*']);
        $this->add($items, $customer, 'intakes', PortalCapability::Intakes, (string) __('customer_intake.portal.nav'), 'move_to_inbox', 'customer.intakes.index', ['customer.intakes.*']);
        $this->add($items, $customer, 'appointments', PortalCapability::Appointments, (string) __('Termin anfragen'), 'event', 'customer.appointments.index', ['customer.appointments.*']);
        // Schulungen: Bereichsfreigabe je Kunde (MVP-1236), das Lernmodul prüft allows() mit.
        $this->add($items, $customer, 'learning', PortalCapability::Learning, (string) __('learning.title.portal'), 'school', 'customer.learning.index', ['customer.learning.*']);
        $this->add($items, $customer, 'queries', PortalCapability::Queries, (string) __('Rückfragen'), 'forum', 'customer.queries.index', ['customer.queries.*']);

        return $items;
    }

    /**
     * @param  list<NavItem>  $items
     * @param  list<string>  $routeIs
     */
    private function add(array &$items, Customer $customer, string $key, ?PortalCapability $capability, string $label, string $icon, string $route, array $routeIs, bool $condition = true): void {
        if (! $condition || ($capability !== null && ! $this->visibility->allows($customer, $capability))) {
            return;
        }
        $items[] = [
            'key' => $key,
            'label' => $label,
            'description' => (string) __('customer_portal.nav.' . $key),
            'icon' => $icon,
            'route' => $route,
            'routeIs' => $routeIs,
            'always' => $capability === null,
        ];
    }

    /**
     * Bereiche ohne die immer vorhandenen Einträge (Übersicht, Suche) — die
     * Kacheln der Startseite.
     *
     * @return list<NavItem>
     */
    public function areas(Customer $customer): array {
        return array_values(array_filter($this->items($customer), static fn (array $item): bool => ! $item['always']));
    }

    /**
     * @param  NavItem  $item
     */
    public static function isActive(array $item): bool {
        return request()->routeIs(...$item['routeIs']);
    }
}
