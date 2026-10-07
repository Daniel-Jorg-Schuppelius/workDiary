<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakeUploadLinkData.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Dto;

/** Beim Kanal angelegter Upload-Link (MVP-1078): Freigabe-ID, öffentliche Adresse, Ordner. */
final readonly class IntakeUploadLinkData {
    public function __construct(
        public string $externalId,
        public string $url,
        public string $folder,
    ) {}
}
