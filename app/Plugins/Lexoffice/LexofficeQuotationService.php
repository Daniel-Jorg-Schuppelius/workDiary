<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeQuotationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice;

/**
 * Angebot-Anbindung an Lexoffice (Feature 045/047): erzeugt aus einem
 * kundenbezogenen Fertigungsauftrag (typischerweise im Entwurf) ein
 * Lexoffice-Angebot (POST /v1/quotations). Gemeinsame Logik in
 * {@see LexofficeOrderDocumentService}.
 */
class LexofficeQuotationService extends LexofficeOrderDocumentService {
    public const EXT_TYPE_QUOTATION = 'quotation';

    protected function endpointPath(): string {
        return 'quotations';
    }

    public function extType(): string {
        return self::EXT_TYPE_QUOTATION;
    }

    protected function documentTitle(): string {
        return (string) __('Angebot');
    }

    protected function noCustomerErrorKey(): string {
        return 'finance.error.lexoffice_quote_no_customer';
    }

    protected function notLinkedErrorKey(): string {
        return 'finance.error.lexoffice_quote_not_linked';
    }
}
