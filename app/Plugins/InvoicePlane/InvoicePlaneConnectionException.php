<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicePlaneConnectionException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\InvoicePlane;

use RuntimeException;

/**
 * Unsichere/abgelehnte InvoicePlane-Verbindung (Feature 086, MVP-419).
 * Trägt bewusst KEINE Zugangsdaten/DSN im Text (Datensparsamkeit).
 */
class InvoicePlaneConnectionException extends RuntimeException {}
