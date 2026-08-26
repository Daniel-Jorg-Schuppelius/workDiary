<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PartialUploadUnsupportedException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Webdav\Api;

use RuntimeException;

/**
 * Der WebDAV-Server lehnt `PUT` mit `Content-Range` ab (RFC 9110 §14.4 lässt
 * das ausdrücklich zu; SabreDAV/Nextcloud antworten 400). Kein Fehler des
 * Uploads — der Client fällt auf den einzelnen PUT zurück.
 */
final class PartialUploadUnsupportedException extends RuntimeException {}
