<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReadyConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Contracts;

/**
 * Verbindungszeile eines Plugins je Organisation, die sagt, ob ein Lauf
 * gegen die Quelle gerade möglich ist (geprüft, nicht getrennt, nicht
 * abgeschaltet) — die Grundlage des gemeinsamen Healthchecks
 * ({@see \App\Plugins\Support\ChecksConnectionHealth}).
 */
interface ReadyConnection {
    public function isReady(): bool;
}
