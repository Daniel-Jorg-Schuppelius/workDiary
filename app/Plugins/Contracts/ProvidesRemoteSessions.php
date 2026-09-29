<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProvidesRemoteSessions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Contracts;

/** Plugin liefert Fernwartungssitzungen (`remote_pending_sessions`); die Suche zeigt sie nur bei aktivem Lieferanten (MVP-1044). */
interface ProvidesRemoteSessions {}
