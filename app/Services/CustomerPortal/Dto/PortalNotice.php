<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalNotice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\CustomerPortal\Dto;

use Carbon\CarbonInterface;

/**
 * Ein Hinweis im Kundenportal bzw. auf einer öffentlichen Seite (MVP-915).
 * `tone` ist die Alert-Farbe (warning/info/success), `badge` ein optionaler
 * Zusatz neben dem Datum (z. B. „entwarnt“).
 */
final readonly class PortalNotice {
    public function __construct(
        public string $subject,
        public string $body,
        public CarbonInterface $publishedAt,
        public string $tone = 'warning',
        public ?string $badge = null,
    ) {}
}
