<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProvidesRecordDek.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Privacy\Concerns;

/**
 * Liefert den (entpackten) Data Encryption Key des Datensatzes fuer den
 * {@see \App\Models\Privacy\Casts\RecordEncrypted}-Cast. null = kein Schluessel
 * (Crypto-Shredding) → Inhalt bewusst unwiederbringlich.
 */
interface ProvidesRecordDek {
    public function recordDek(): ?string;
}
