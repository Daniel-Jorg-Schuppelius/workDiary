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
 * die bestehende E-Rechnungs-Prüfpipeline, alles andere ins DMS. Weitere
 * Ziele sind bewusst NICHT vorgesehen (Konzept §Datenmodell).
 */
enum CloudIntakeRouteTarget: string implements HasLabel {
    use HasOptions;

    case IncomingInvoice = 'incoming_invoice';
    case Document = 'document';

    public function label(): string {
        return (string) __('enums.cloud_intake.route_target.' . $this->value);
    }
}
