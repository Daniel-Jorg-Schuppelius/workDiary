<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudIntakeRouteTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\CloudIntake;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zielbereich einer Ordnerregel (Feature 080): Eingangsrechnungen laufen in
 * die bestehende E-Rechnungs-Prüfpipeline, alles andere ins DMS. Feature 099
 * ergänzt den openTRANS-Bestelleingang (B2bOrder), Feature 108 (MVP-627) den
 * Paketeingang für Vergabeunterlagen — dort liefert die Vergabestelle ein ZIP,
 * das erst zerlegt werden muss, bevor überhaupt entscheidbar ist, was darin
 * ein Leistungsverzeichnis ist. Weitere Ziele bleiben bewusst NICHT vorgesehen
 * (Konzept §Datenmodell).
 */
enum CloudIntakeRouteTarget: string implements HasLabel {
    use HasOptions;

    case IncomingInvoice = 'incoming_invoice';
    case Document = 'document';
    case B2bOrder = 'b2b_order';
    case GaebPackage = 'gaeb_package';

    public function label(): string {
        return (string) __('enums.cloud_intake.route_target.' . $this->value);
    }
}
