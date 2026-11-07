<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : einvoice-profiles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Empfängerprofile der E-Rechnung (Feature 196). Werte nach dem strengsten
 * gemeinsamen Nenner der Rechnungseingangsplattformen (Analyse
 * erechnung-arbeitsnachweise-2026-11-07, Abschnitt 6.3): OZG-RE per Mail
 * 10 MB je Datei, NRW 10 MB für alle Anhänge, ZeRN 10 MB für die XML.
 * Je Kunde lässt sich die Grenze überschreiben.
 */
return [
    'standard' => [
        'attachment_limit_mb' => 15,
        'xml_only_mail' => false,
        'buyer_reference_required' => false,
        'attach_activity_report' => false,
    ],
    'public_sector' => [
        'attachment_limit_mb' => 10,
        'xml_only_mail' => true,
        'buyer_reference_required' => true,
        'attach_activity_report' => true,
    ],
];
