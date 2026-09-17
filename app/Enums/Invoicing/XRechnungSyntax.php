<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : XRechnungSyntax.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Invoicing;

/**
 * Syntax einer XRechnung (MVP-805): UBL 2.1 ist der Standardweg und die
 * einzige Syntax für Peppol; UN/CEFACT CII nur, wenn der Empfänger sie
 * verlangt (Zustellformat {@see InvoiceDeliveryFormat::XRechnungCii}).
 */
enum XRechnungSyntax: string {
    case Ubl = 'ubl';
    case Cii = 'cii';
}
