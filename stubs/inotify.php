<?php
/*
 * Created on   : Wed Jul 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : inotify.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

/*
 * IDE-Stub für ext-inotify (PECL) — wird zur Laufzeit NIE geladen (kein
 * Autoload, kein include). Existiert nur, damit Intelephense/PHP Tools die
 * IN_*-Konstanten und inotify_*-Funktionen aus integrity:watch (Feature 097)
 * kennen; die echte Verfügbarkeit prüft WatchCommand::hasInotify().
 * Werte entsprechen linux/inotify.h.
 */

define('IN_ACCESS', 1);
define('IN_MODIFY', 2);
define('IN_ATTRIB', 4);
define('IN_CLOSE_WRITE', 8);
define('IN_CLOSE_NOWRITE', 16);
define('IN_OPEN', 32);
define('IN_MOVED_FROM', 64);
define('IN_MOVED_TO', 128);
define('IN_CREATE', 256);
define('IN_DELETE', 512);
define('IN_DELETE_SELF', 1024);
define('IN_MOVE_SELF', 2048);
define('IN_UNMOUNT', 8192);
define('IN_Q_OVERFLOW', 16384);
define('IN_IGNORED', 32768);
define('IN_CLOSE', IN_CLOSE_WRITE | IN_CLOSE_NOWRITE);
define('IN_MOVE', IN_MOVED_FROM | IN_MOVED_TO);
define('IN_ALL_EVENTS', 4095);
define('IN_ONLYDIR', 16777216);
define('IN_DONT_FOLLOW', 33554432);
define('IN_MASK_ADD', 536870912);
define('IN_ISDIR', 1073741824);
define('IN_ONESHOT', 2147483648);

// function_exists-Guard: mit geladener ext-inotify wäre die unbedingte
// Deklaration schon für `php -l` ein Redeclare-Fatal.
if (! function_exists('inotify_init')) {
    /** @return resource|false */
    function inotify_init() {
        throw new LogicException('IDE-Stub — ext-inotify ist nicht geladen.');
    }

    /**
     * @param  resource  $inotify_instance
     * @return int|false Watch-Deskriptor
     */
    function inotify_add_watch($inotify_instance, string $pathname, int $mask) {
        throw new LogicException('IDE-Stub — ext-inotify ist nicht geladen.');
    }

    /**
     * @param  resource  $inotify_instance
     * @return bool
     */
    function inotify_rm_watch($inotify_instance, int $watch_descriptor) {
        throw new LogicException('IDE-Stub — ext-inotify ist nicht geladen.');
    }

    /**
     * @param  resource  $inotify_instance
     * @return int
     */
    function inotify_queue_len($inotify_instance) {
        throw new LogicException('IDE-Stub — ext-inotify ist nicht geladen.');
    }

    /**
     * @param  resource  $inotify_instance
     * @return array<int, array{wd: int, mask: int, cookie: int, name: string}>|false
     */
    function inotify_read($inotify_instance) {
        throw new LogicException('IDE-Stub — ext-inotify ist nicht geladen.');
    }
}
