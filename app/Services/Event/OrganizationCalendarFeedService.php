<?php
/*
 * Created on   : Sun Sep 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationCalendarFeedService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Event;

use App\Support\Auth\OrganizationAccessToken;

/**
 * Zugangstoken für den gemeinsamen Kalender-Feed einer Organisation
 * (Mandanten-Review 2026-09-13). Kalender-Apps rufen eine Abo-Adresse ohne
 * Sitzung ab; ohne Token gibt es keinen Feed — die Freischaltung IST der Token.
 */
class OrganizationCalendarFeedService extends OrganizationAccessToken {
    public const HASH_KEY = 'calendar_feed_token_hash';

    public const HINT_KEY = 'calendar_feed_token_hint';

    public const ISSUED_KEY = 'calendar_feed_token_issued_at';

    protected const TOKEN_LENGTH = 48;

    protected const ACTIVE_ONLY = true;
}
