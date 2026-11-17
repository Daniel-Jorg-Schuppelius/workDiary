<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UnmappableTaxCaseException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Ein Faktura- oder Buchhaltungsprogramm kann den Steuerfall nicht abbilden,
 * oder die Positionen ergeben mehrere Steuerfälle (MVP-1712). Die Meldung
 * nennt den Grund; übergeben wird nichts.
 */
final class UnmappableTaxCaseException extends RuntimeException {}
