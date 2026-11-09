<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnlinePrepaymentStatusChanged.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Invoicing;

use App\Models\Invoicing\OnlinePayment;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Stand einer Vorauszahlung ohne Rechnung hat sich beim Anbieter geändert
 * (MVP-1515) — der Gegenstand (etwa eine Verleihbuchung) entscheidet selbst,
 * was daraus folgt. Synchron: Webhook und Rückkehr warten auf die Bestätigung.
 */
final class OnlinePrepaymentStatusChanged {
    use Dispatchable;

    public function __construct(public readonly OnlinePayment $payment) {}
}
