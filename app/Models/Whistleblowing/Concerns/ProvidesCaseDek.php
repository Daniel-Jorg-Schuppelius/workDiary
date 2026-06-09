<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProvidesCaseDek.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Whistleblowing\Concerns;

/**
 * Liefert den (entpackten) Data Encryption Key des zugehoerigen Falls fuer den
 * {@see \App\Models\Whistleblowing\Casts\CaseEncrypted}-Cast. null bedeutet:
 * kein Schluessel verfuegbar (Crypto-Shredding) → Inhalt unwiederbringlich.
 */
interface ProvidesCaseDek {
    public function caseDek(): ?string;
}
