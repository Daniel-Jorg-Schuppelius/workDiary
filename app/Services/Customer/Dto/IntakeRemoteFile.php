<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakeRemoteFile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Dto;

/**
 * Datei im Ordner eines Upload-Links (MVP-1078). `key` identifiziert genau
 * diese Fassung (Anbieter-ID + ETag): eine neu hochgeladene Fassung bekommt
 * einen neuen Schlüssel und wird ein neuer Anhang.
 */
final readonly class IntakeRemoteFile {
    public function __construct(
        public string $key,
        public string $path,
        public string $name,
        public int $size,
        public ?string $mime = null,
    ) {}
}
