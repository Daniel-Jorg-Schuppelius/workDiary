<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContributesWhileInactive.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Contracts;

/**
 * Plugin liefert auch ohne aktive Verbindung Slot- und Menübeiträge (z. B.
 * Lexware-Ergänzungen ohne API-Schlüssel) und prüft seine Bedingungen selbst.
 */
interface ContributesWhileInactive {}
