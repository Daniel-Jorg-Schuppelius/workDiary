<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CtiDialer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Cti\Dial;

use App\Models\CtiConnection;

/**
 * Startet einen ausgehenden Anruf über die Telefonanlage (Click-to-Dial,
 * Feature 056/MVP-118; Audit 2026-08 W4.5).
 *
 * Der Ablauf ist bei allen Anlagen derselbe: Die Anlage ruft ZUERST die
 * eigene Durchwahl an und verbindet nach dem Abheben mit dem Ziel. Der
 * Adapter kapselt nur die providerspezifische Anfrage.
 */
interface CtiDialer {
    /**
     * Wählt $targetE164 von der Durchwahl der Anbindung aus.
     *
     * @throws CtiDialException wenn die Anlage den Auftrag ablehnt
     */
    public function dial(CtiConnection $connection, string $targetE164): void;
}
