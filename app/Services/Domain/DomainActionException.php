<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainActionException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Domain;

use RuntimeException;

/**
 * Fachlicher Preflight-/Aktionsfehler des Domain-Moduls (Feature 083):
 * fehlende Preisbestätigung, fehlende Kontakte/Nameserver, verletztes
 * Vier-Augen-Prinzip usw. Der Controller übersetzt die Meldung in einen
 * Flash-Fehler.
 */
class DomainActionException extends RuntimeException {}
