<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalNoticeSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\CustomerPortal\Contracts;

use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use App\Services\CustomerPortal\Dto\PortalNotice;

/**
 * Erweiterungspunkt Hinweise im Kundenportal (MVP-915): ein Modul meldet
 * aktuelle Mitteilungen für die Startseite des Portals. Läuft mit gebundener
 * Organisation; die Quelle prüft ihre Modulfreischaltung selbst.
 */
interface PortalNoticeSource {
    /** @return list<PortalNotice> */
    public function portalNotices(Organization $organization, Customer $customer): array;
}
