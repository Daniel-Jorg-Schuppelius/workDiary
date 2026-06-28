<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeOrderConfirmationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice;

/**
 * Auftragsbestätigung-Anbindung an Lexoffice (Feature 045/047): erzeugt aus
 * einem kundenbezogenen Fertigungsauftrag eine Lexoffice-Auftragsbestätigung
 * (POST /v1/order-confirmations). Gemeinsame Logik in
 * {@see LexofficeOrderDocumentService}.
 */
class LexofficeOrderConfirmationService extends LexofficeOrderDocumentService {
    public const EXT_TYPE_ORDER_CONFIRMATION = 'order_confirmation';

    protected function endpointPath(): string {
        return 'order-confirmations';
    }

    public function extType(): string {
        return self::EXT_TYPE_ORDER_CONFIRMATION;
    }

    protected function documentTitle(): string {
        return (string) __('Auftragsbestätigung');
    }

    protected function noCustomerErrorKey(): string {
        return 'finance.error.lexoffice_oc_no_customer';
    }

    protected function notLinkedErrorKey(): string {
        return 'finance.error.lexoffice_oc_not_linked';
    }
}
