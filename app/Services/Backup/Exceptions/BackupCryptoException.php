<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupCryptoException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Backup\Exceptions;

use RuntimeException;

/**
 * Ver-/Entschlüsselung eines Backup-Teils gescheitert — Manipulation
 * (Bitflip/Trunkierung/Teil-Vertauschung), falscher Schlüssel oder
 * beschädigte Datei. Der betroffene Teil ist NICHT verwendbar.
 */
class BackupCryptoException extends RuntimeException {}
